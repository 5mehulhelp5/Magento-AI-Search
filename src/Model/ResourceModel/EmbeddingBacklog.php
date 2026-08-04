<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Model\ResourceModel;

use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
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
        int $sourceEntityId
    ): void {
        $this->upsert(
            $chunkId,
            $sourceEntityType,
            $sourceEntityId,
            Operation::Upsert
        );
    }

    public function deleteByChunkId(
        int $chunkId,
        string $sourceEntityType,
        int $sourceEntityId
    ): void {
        $this->upsert(
            $chunkId,
            $sourceEntityType,
            $sourceEntityId,
            Operation::Deletion
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPendingUpsertsForEmbedding(
        int $limit,
        ?string $cursorUpdatedAt = null,
        ?int $cursorBacklogId = null
    ): array {
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
    public function getItemsForDeletion(
        int $limit,
        int $upsertAttemptThreshold,
        ?string $cursorUpdatedAt = null,
        ?int $cursorBacklogId = null
    ): array {
        if ($limit < 1) {
            throw new InvalidArgumentException('The deletion backlog batch limit must be positive.');
        }

        if ($upsertAttemptThreshold < 1) {
            throw new InvalidArgumentException('The upsert attempt threshold must be positive.');
        }

        if (($cursorUpdatedAt === null) !== ($cursorBacklogId === null)) {
            throw new InvalidArgumentException('Both deletion backlog cursor values must be provided together.');
        }

        if ($cursorBacklogId !== null && $cursorBacklogId < 0) {
            throw new InvalidArgumentException('The deletion backlog cursor ID must be non-negative.');
        }

        /** @var AdapterInterface $connection */
        $connection = $this->getConnection();
        /** @var list<array<string, mixed>> $rows */
        $rows = $connection->fetchAll(
            $this->createDeletionSelect(
                $connection,
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

    public function markFailedAsPending(int $attemptThreshold): int
    {
        if ($attemptThreshold < 1) {
            throw new InvalidArgumentException('The retry attempt threshold must be positive.');
        }

        /** @var AdapterInterface $connection */
        $connection = $this->getConnection();

        return $connection->update(
            $this->getMainTable(),
            [EmbeddingBacklogInterface::STATUS => Status::Pending->value],
            [
                EmbeddingBacklogInterface::STATUS . ' = ?' => Status::Failed->value,
                EmbeddingBacklogInterface::ATTEMPT_COUNT . ' < ?' => $attemptThreshold,
            ]
        );
    }

    public function markMissingChunkUpsertsOutdated(): int
    {
        /** @var AdapterInterface $connection */
        $connection = $this->getConnection();
        $select = $connection->select()
            ->joinLeft(
                ['chunk' => $this->getTable('davidbel_ai_search_chunk')],
                'chunk.chunk_id = backlog.chunk_id',
                []
            )
            ->columns([
                EmbeddingBacklogInterface::STATUS => new Expression(
                    $connection->quoteInto('?', Status::Outdated->value)
                ),
                EmbeddingBacklogInterface::LAST_ERROR_CATEGORY => new Expression('NULL'),
            ])
            ->where('backlog.operation = ?', Operation::Upsert->value)
            ->where('backlog.status = ?', Status::Pending->value)
            ->where('chunk.chunk_id IS NULL');
        $query = $connection->updateFromSelect(
            $select,
            ['backlog' => $this->getMainTable()]
        );

        return $connection->query($query)->rowCount();
    }

    public function deleteExhaustedUpsertsOrExpiredResults(
        int $attemptThreshold,
        string $expiredBefore
    ): int {
        if ($attemptThreshold < 1) {
            throw new InvalidArgumentException('The cleanup attempt threshold must be positive.');
        }

        if ($expiredBefore === '') {
            throw new InvalidArgumentException('The backlog expiration cutoff must not be empty.');
        }

        /** @var AdapterInterface $connection */
        $connection = $this->getConnection();

        return $connection->delete(
            $this->getMainTable(),
            $this->createCleanupCondition(
                $connection,
                $attemptThreshold,
                $expiredBefore
            )
        );
    }

    protected function _construct(): void
    {
        $this->_init('davidbel_ai_search_embedding_backlog', 'backlog_id');
    }

    private function createCleanupCondition(
        AdapterInterface $connection,
        int $attemptThreshold,
        string $expiredBefore
    ): string {
        $failedStatus = $connection->quoteInto(
            EmbeddingBacklogInterface::STATUS . ' = ?',
            Status::Failed->value
        );
        $attemptLimit = $connection->quoteInto(
            EmbeddingBacklogInterface::ATTEMPT_COUNT . ' >= ?',
            $attemptThreshold
        );
        $upsertOperation = $connection->quoteInto(
            EmbeddingBacklogInterface::OPERATION . ' = ?',
            Operation::Upsert->value
        );
        $resultStatus = $connection->quoteInto(
            EmbeddingBacklogInterface::STATUS . ' IN (?)',
            [
                Status::Done->value,
                Status::Outdated->value,
            ]
        );
        $expirationCutoff = $connection->quoteInto(
            EmbeddingBacklogInterface::UPDATED_AT . ' < ?',
            $expiredBefore
        );

        return sprintf(
            '((%s AND %s AND %s) OR (%s AND %s))',
            $upsertOperation,
            $failedStatus,
            $attemptLimit,
            $resultStatus,
            $expirationCutoff
        );
    }

    private function createUpsertSelect(
        AdapterInterface $connection,
        int $limit,
        ?string $cursorUpdatedAt,
        ?int $cursorBacklogId
    ): Select {
        $select = $this->createEmbeddingSelect($connection)
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

    private function createDeletionSelect(
        AdapterInterface $connection,
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
                    EmbeddingBacklogInterface::VERSION,
                    EmbeddingBacklogInterface::CHUNK_ID,
                    EmbeddingBacklogInterface::SOURCE_ENTITY_TYPE,
                    EmbeddingBacklogInterface::SOURCE_ENTITY_ID,
                    EmbeddingBacklogInterface::UPDATED_AT,
                ]
            )
            ->where('backlog.operation = ?', Operation::Deletion->value)
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
                    EmbeddingBacklogInterface::VERSION,
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
        Operation $operation
    ): void {
        /** @var AdapterInterface $connection */
        $connection = $this->getConnection();

        $connection->insertOnDuplicate(
            $this->getMainTable(),
            [
                EmbeddingBacklogInterface::CHUNK_ID => $chunkId,
                EmbeddingBacklogInterface::SOURCE_ENTITY_TYPE => $sourceEntityType,
                EmbeddingBacklogInterface::SOURCE_ENTITY_ID => $sourceEntityId,
                EmbeddingBacklogInterface::OPERATION => $operation->value,
                EmbeddingBacklogInterface::STATUS => Status::Pending->value,
                EmbeddingBacklogInterface::VERSION => 1,
                EmbeddingBacklogInterface::ATTEMPT_COUNT => 0,
                EmbeddingBacklogInterface::LAST_ERROR_CATEGORY => null,
            ],
            [
                EmbeddingBacklogInterface::SOURCE_ENTITY_TYPE,
                EmbeddingBacklogInterface::SOURCE_ENTITY_ID,
                EmbeddingBacklogInterface::OPERATION,
                EmbeddingBacklogInterface::STATUS,
                EmbeddingBacklogInterface::VERSION => new Expression(
                    EmbeddingBacklogInterface::VERSION . ' + 1'
                ),
                EmbeddingBacklogInterface::ATTEMPT_COUNT,
                EmbeddingBacklogInterface::LAST_ERROR_CATEGORY,
            ]
        );
    }

    /**
     * @param array<int, int> $backlogVersions
     * @param array<string, mixed> $values
     */
    private function updateItems(array $backlogVersions, array $values): void
    {
        /** @var AdapterInterface $connection */
        $connection = $this->getConnection();

        foreach (array_chunk($backlogVersions, 1_000, true) as $versionBatch) {
            $connection->update(
                $this->getMainTable(),
                $values,
                [
                    $this->createVersionCondition($connection, $versionBatch),
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
    private function createVersionCondition(
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
            $connection->quoteIdentifier(EmbeddingBacklogInterface::VERSION),
            implode(', ', $pairs)
        );
    }
}
