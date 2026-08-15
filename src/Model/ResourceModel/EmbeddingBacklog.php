<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Model\ResourceModel;

use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Model\EmbeddingBacklog\FullReindexStatus;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Operation;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Status;
use InvalidArgumentException;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class EmbeddingBacklog extends AbstractDb
{
    public function saveByChunkId(
        int $chunkId,
        string $sourceEntityType,
        int $sourceEntityId,
        int $indexVersion,
        FullReindexStatus $fullReindexStatus
    ): void {
        $this->upsert(
            $chunkId,
            $sourceEntityType,
            $sourceEntityId,
            Operation::Upsert,
            $indexVersion,
            $fullReindexStatus
        );
    }

    public function deleteByChunkId(
        int $chunkId,
        string $sourceEntityType,
        int $sourceEntityId,
        int $indexVersion,
        FullReindexStatus $fullReindexStatus
    ): void {
        $this->upsert(
            $chunkId,
            $sourceEntityType,
            $sourceEntityId,
            Operation::Delete,
            $indexVersion,
            $fullReindexStatus
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPendingUpsertsForEmbedding(
        int $indexVersion,
        int $limit,
        ?string $cursorUpdatedAt = null,
        ?int $cursorBacklogId = null
    ): array {
        $this->validateIndexVersion($indexVersion);

        if ($limit < 1) {
            throw new InvalidArgumentException('The embedding backlog batch limit must be positive.');
        }

        if (($cursorUpdatedAt === null) !== ($cursorBacklogId === null)) {
            throw new InvalidArgumentException('Both embedding backlog cursor values must be provided together.');
        }

        if ($cursorBacklogId !== null && $cursorBacklogId < 0) {
            throw new InvalidArgumentException('The embedding backlog cursor ID must be non-negative.');
        }

        /** @var AdapterInterface $connection */
        $connection = $this->getConnection();
        /** @var list<array<string, mixed>> $rows */
        $rows = $connection->fetchAll(
            $this->createUpsertSelect(
                $connection,
                $indexVersion,
                $limit,
                $cursorUpdatedAt,
                $cursorBacklogId
            )
        );

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getItemsToDelete(
        int $indexVersion,
        int $limit,
        int $upsertAttemptThreshold,
        ?string $cursorUpdatedAt = null,
        ?int $cursorBacklogId = null
    ): array {
        $this->validateIndexVersion($indexVersion);

        if ($limit < 1) {
            throw new InvalidArgumentException('The delete backlog batch limit must be positive.');
        }

        if ($upsertAttemptThreshold < 1) {
            throw new InvalidArgumentException('The upsert attempt threshold must be positive.');
        }

        if (($cursorUpdatedAt === null) !== ($cursorBacklogId === null)) {
            throw new InvalidArgumentException('Both delete backlog cursor values must be provided together.');
        }

        if ($cursorBacklogId !== null && $cursorBacklogId < 0) {
            throw new InvalidArgumentException('The delete backlog cursor ID must be non-negative.');
        }

        /** @var AdapterInterface $connection */
        $connection = $this->getConnection();
        /** @var list<array<string, mixed>> $rows */
        $rows = $connection->fetchAll(
            $this->createDeleteSelect(
                $connection,
                $indexVersion,
                $limit,
                $upsertAttemptThreshold,
                $cursorUpdatedAt,
                $cursorBacklogId
            )
        );

        return $rows;
    }

    /**
     * @param array<int, int> $backlogVersions
     */
    public function markDoneByVersions(array $backlogVersions): void
    {
        if ($backlogVersions === []) {
            return;
        }

        $this->updateItems(
            $backlogVersions,
            [
                EmbeddingBacklogInterface::STATUS => Status::Done->value,
                EmbeddingBacklogInterface::LAST_ERROR_CATEGORY => null,
            ]
        );
    }

    /**
     * @param array<int, int> $backlogVersions
     */
    public function markFailedByVersions(array $backlogVersions, string $errorCategory): void
    {
        if ($backlogVersions === []) {
            return;
        }

        $this->updateItems(
            $backlogVersions,
            [
                EmbeddingBacklogInterface::STATUS => Status::Failed->value,
                EmbeddingBacklogInterface::ATTEMPT_COUNT => new Expression(
                    EmbeddingBacklogInterface::ATTEMPT_COUNT . ' + 1'
                ),
                EmbeddingBacklogInterface::LAST_ERROR_CATEGORY => $errorCategory,
            ]
        );
    }

    protected function _construct(): void
    {
        $this->_init('davidbel_ai_search_embedding_backlog', 'backlog_id');
    }

    private function createUpsertSelect(
        AdapterInterface $connection,
        int $indexVersion,
        int $limit,
        ?string $cursorUpdatedAt,
        ?int $cursorBacklogId
    ): Select {
        $select = $this->createEmbeddingSelect($connection)
            ->where('backlog.index_version = ?', $indexVersion)
            ->where('backlog.operation = ?', Operation::Upsert->value)
            ->where('backlog.status = ?', Status::Pending->value)
            ->order([
                'backlog.updated_at ASC',
                'backlog.backlog_id ASC',
            ])
            ->limit($limit);

        if ($cursorUpdatedAt !== null && $cursorBacklogId !== null) {
            $select->where(
                $this->createCursorCondition(
                    $connection,
                    $cursorUpdatedAt,
                    $cursorBacklogId
                )
            );
        }

        return $select;
    }

    private function createDeleteSelect(
        AdapterInterface $connection,
        int $indexVersion,
        int $limit,
        int $upsertAttemptThreshold,
        ?string $cursorUpdatedAt,
        ?int $cursorBacklogId
    ): Select {
        $blockingUpsertSelect = $this->createBlockingUpsertSelect(
            $connection,
            $upsertAttemptThreshold
        );
        $select = $connection->select()
            ->from(
                ['backlog' => $this->getMainTable()],
                [
                    EmbeddingBacklogInterface::BACKLOG_ID,
                    EmbeddingBacklogInterface::BACKLOG_VERSION,
                    EmbeddingBacklogInterface::INDEX_VERSION,
                    EmbeddingBacklogInterface::CHUNK_ID,
                    EmbeddingBacklogInterface::SOURCE_ENTITY_TYPE,
                    EmbeddingBacklogInterface::SOURCE_ENTITY_ID,
                    EmbeddingBacklogInterface::UPDATED_AT,
                ]
            )
            ->where('backlog.index_version = ?', $indexVersion)
            ->where('backlog.operation = ?', Operation::Delete->value)
            ->where('backlog.status = ?', Status::Pending->value)
            ->where(
                sprintf('NOT EXISTS (%s)', $blockingUpsertSelect->assemble())
            )
            ->order([
                'backlog.updated_at ASC',
                'backlog.backlog_id ASC',
            ])
            ->limit($limit);

        if ($cursorUpdatedAt !== null && $cursorBacklogId !== null) {
            $select->where(
                $this->createCursorCondition(
                    $connection,
                    $cursorUpdatedAt,
                    $cursorBacklogId
                )
            );
        }

        return $select;
    }

    private function createBlockingUpsertSelect(
        AdapterInterface $connection,
        int $attemptThreshold
    ): Select {
        $pendingStatus = $connection->quoteInto(
            'blocking_upsert.status = ?',
            Status::Pending->value
        );
        $failedStatus = $connection->quoteInto(
            'blocking_upsert.status = ?',
            Status::Failed->value
        );
        $attemptLimit = $connection->quoteInto(
            'blocking_upsert.attempt_count < ?',
            $attemptThreshold
        );

        return $connection->select()
            ->from(['blocking_upsert' => $this->getMainTable()], [])
            ->columns([new Expression('1')])
            ->where('blocking_upsert.chunk_id = backlog.chunk_id')
            ->where('blocking_upsert.index_version = backlog.index_version')
            ->where('blocking_upsert.operation = ?', Operation::Upsert->value)
            ->where(
                sprintf('(%s OR (%s AND %s))', $pendingStatus, $failedStatus, $attemptLimit)
            );
    }

    private function createEmbeddingSelect(AdapterInterface $connection): Select
    {
        return $connection->select()
            ->from(
                ['backlog' => $this->getMainTable()],
                [
                    EmbeddingBacklogInterface::BACKLOG_ID,
                    EmbeddingBacklogInterface::BACKLOG_VERSION,
                    EmbeddingBacklogInterface::INDEX_VERSION,
                    EmbeddingBacklogInterface::CHUNK_ID,
                    EmbeddingBacklogInterface::UPDATED_AT,
                ]
            )
            ->join(
                ['chunk' => $this->getTable('davidbel_ai_search_chunk')],
                'chunk.chunk_id = backlog.chunk_id',
                [
                    ChunkInterface::CHUNK_INDEX,
                    ChunkInterface::CONTENT,
                    ChunkInterface::CONTENT_HASH,
                ]
            )
            ->join(
                ['document' => $this->getTable('davidbel_ai_search_document')],
                'document.document_id = chunk.document_id',
                [
                    DocumentInterface::SOURCE_ENTITY_TYPE,
                    DocumentInterface::SOURCE_ENTITY_ID,
                    DocumentInterface::STORE_ID,
                    DocumentInterface::SOURCE_CODE,
                    DocumentInterface::TITLE,
                ]
            );
    }

    private function createCursorCondition(
        AdapterInterface $connection,
        string $cursorUpdatedAt,
        int $cursorBacklogId
    ): string {
        $afterTimestamp = $connection->quoteInto('backlog.updated_at > ?', $cursorUpdatedAt);
        $atTimestamp = $connection->quoteInto('backlog.updated_at = ?', $cursorUpdatedAt);
        $afterId = $connection->quoteInto('backlog.backlog_id > ?', $cursorBacklogId);

        return sprintf('(%s OR (%s AND %s))', $afterTimestamp, $atTimestamp, $afterId);
    }

    private function upsert(
        int $chunkId,
        string $sourceEntityType,
        int $sourceEntityId,
        Operation $operation,
        int $indexVersion,
        FullReindexStatus $fullReindexStatus
    ): void {
        $this->validateIndexVersion($indexVersion);

        /** @var AdapterInterface $connection */
        $connection = $this->getConnection();

        $connection->insertOnDuplicate(
            $this->getMainTable(),
            $this->createInsertData(
                $chunkId,
                $sourceEntityType,
                $sourceEntityId,
                $operation,
                $indexVersion,
                $fullReindexStatus
            ),
            $this->createDuplicateFields($connection)
        );
    }

    /**
     * @return array<string, int|string|null>
     */
    private function createInsertData(
        int $chunkId,
        string $sourceEntityType,
        int $sourceEntityId,
        Operation $operation,
        int $indexVersion,
        FullReindexStatus $fullReindexStatus
    ): array {
        return [
            EmbeddingBacklogInterface::CHUNK_ID => $chunkId,
            EmbeddingBacklogInterface::SOURCE_ENTITY_TYPE => $sourceEntityType,
            EmbeddingBacklogInterface::SOURCE_ENTITY_ID => $sourceEntityId,
            EmbeddingBacklogInterface::OPERATION => $operation->value,
            EmbeddingBacklogInterface::STATUS => Status::Pending->value,
            EmbeddingBacklogInterface::INDEX_VERSION => $indexVersion,
            EmbeddingBacklogInterface::FULL_REINDEX_STATUS => $fullReindexStatus->value,
            EmbeddingBacklogInterface::BACKLOG_VERSION => 1,
            EmbeddingBacklogInterface::ATTEMPT_COUNT => 0,
            EmbeddingBacklogInterface::LAST_ERROR_CATEGORY => null,
        ];
    }

    /**
     * @return array<int|string, string|Expression>
     */
    private function createDuplicateFields(AdapterInterface $connection): array
    {
        $indexVersionField = $connection->quoteIdentifier(
            EmbeddingBacklogInterface::INDEX_VERSION
        );
        $fullReindexStatusField = $connection->quoteIdentifier(
            EmbeddingBacklogInterface::FULL_REINDEX_STATUS
        );

        return [
            EmbeddingBacklogInterface::SOURCE_ENTITY_TYPE,
            EmbeddingBacklogInterface::SOURCE_ENTITY_ID,
            EmbeddingBacklogInterface::OPERATION,
            EmbeddingBacklogInterface::STATUS,
            EmbeddingBacklogInterface::FULL_REINDEX_STATUS => new Expression(sprintf(
                'IF(%1$s <> VALUES(%1$s), VALUES(%2$s), GREATEST(%2$s, VALUES(%2$s)))',
                $indexVersionField,
                $fullReindexStatusField
            )),
            EmbeddingBacklogInterface::INDEX_VERSION,
            EmbeddingBacklogInterface::BACKLOG_VERSION => new Expression(
                EmbeddingBacklogInterface::BACKLOG_VERSION . ' + 1'
            ),
            EmbeddingBacklogInterface::ATTEMPT_COUNT,
            EmbeddingBacklogInterface::LAST_ERROR_CATEGORY,
        ];
    }

    /**
     * @param array<int, int> $backlogVersions
     * @param array<string, mixed> $values
     */
    private function updateItems(array $backlogVersions, array $values): void
    {
        /** @var AdapterInterface $connection */
        $connection = $this->getConnection();

        foreach (array_chunk($backlogVersions, 1_000, true) as $backlogVersionBatch) {
            $connection->update(
                $this->getMainTable(),
                $values,
                [
                    $this->createBacklogVersionCondition($connection, $backlogVersionBatch),
                    EmbeddingBacklogInterface::STATUS . ' IN (?)' => [
                        Status::Pending->value,
                        Status::Failed->value,
                    ],
                ]
            );
        }
    }

    /**
     * @param array<int, int> $backlogVersions
     */
    private function createBacklogVersionCondition(
        AdapterInterface $connection,
        array $backlogVersions
    ): string {
        $pairs = array_map(
            static fn (int $backlogId, int $version): string => sprintf(
                '(%d, %d)',
                $backlogId,
                $version
            ),
            array_keys($backlogVersions),
            array_values($backlogVersions)
        );

        return sprintf(
            '(%s, %s) IN (%s)',
            $connection->quoteIdentifier(EmbeddingBacklogInterface::BACKLOG_ID),
            $connection->quoteIdentifier(EmbeddingBacklogInterface::BACKLOG_VERSION),
            implode(', ', $pairs)
        );
    }

    private function validateIndexVersion(int $indexVersion): void
    {
        if ($indexVersion < 1) {
            throw new InvalidArgumentException('The OpenSearch index version must be positive.');
        }
    }
}
