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
    public function getItemsForDeletion(int $limit): array
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('The deletion backlog batch limit must be positive.');
        }

        /** @var AdapterInterface $connection */
        $connection = $this->getConnection();
        /** @var list<array<string, mixed>> $rows */
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    ['backlog' => $this->getMainTable()],
                    [
                        EmbeddingBacklogInterface::BACKLOG_ID,
                        EmbeddingBacklogInterface::CHUNK_ID,
                        EmbeddingBacklogInterface::SOURCE_ENTITY_TYPE,
                        EmbeddingBacklogInterface::SOURCE_ENTITY_ID,
                    ]
                )
                ->where('backlog.operation = ?', Operation::Deletion->value)
                ->where('backlog.status = ?', Status::Pending->value)
                ->order([
                    'backlog.updated_at ASC',
                    'backlog.backlog_id ASC',
                ])
                ->limit($limit)
        );

        return $rows;
    }

    /**
     * @param list<int> $backlogIds
     */
    public function markDoneByIds(array $backlogIds): void
    {
        if ($backlogIds === []) {
            return;
        }

        $this->updateItems(
            $backlogIds,
            [
                EmbeddingBacklogInterface::STATUS => Status::Done->value,
                EmbeddingBacklogInterface::LAST_ERROR_CATEGORY => null,
            ]
        );
    }

    /**
     * @param list<int> $backlogIds
     */
    public function markFailedByIds(array $backlogIds, string $errorCategory): void
    {
        if ($backlogIds === []) {
            return;
        }

        $this->updateItems(
            $backlogIds,
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

    public function deleteFailedAtThresholdOrDoneBefore(
        int $attemptThreshold,
        string $doneBefore
    ): int {
        if ($attemptThreshold < 1) {
            throw new InvalidArgumentException('The cleanup attempt threshold must be positive.');
        }

        if ($doneBefore === '') {
            throw new InvalidArgumentException('The completed backlog cutoff must not be empty.');
        }

        /** @var AdapterInterface $connection */
        $connection = $this->getConnection();
        $failedStatus = $connection->quoteInto(
            EmbeddingBacklogInterface::STATUS . ' = ?',
            Status::Failed->value
        );
        $attemptLimit = $connection->quoteInto(
            EmbeddingBacklogInterface::ATTEMPT_COUNT . ' >= ?',
            $attemptThreshold
        );
        $doneStatus = $connection->quoteInto(
            EmbeddingBacklogInterface::STATUS . ' = ?',
            Status::Done->value
        );
        $doneCutoff = $connection->quoteInto(
            EmbeddingBacklogInterface::UPDATED_AT . ' < ?',
            $doneBefore
        );

        return $connection->delete(
            $this->getMainTable(),
            sprintf(
                '((%s AND %s) OR (%s AND %s))',
                $failedStatus,
                $attemptLimit,
                $doneStatus,
                $doneCutoff
            )
        );
    }

    protected function _construct(): void
    {
        $this->_init('davidbel_ai_search_embedding_backlog', 'backlog_id');
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

    private function createEmbeddingSelect(AdapterInterface $connection): Select
    {
        return $connection->select()
            ->from(
                ['backlog' => $this->getMainTable()],
                [
                    EmbeddingBacklogInterface::BACKLOG_ID,
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
                EmbeddingBacklogInterface::ATTEMPT_COUNT => 0,
                EmbeddingBacklogInterface::LAST_ERROR_CATEGORY => null,
            ],
            [
                EmbeddingBacklogInterface::SOURCE_ENTITY_TYPE,
                EmbeddingBacklogInterface::SOURCE_ENTITY_ID,
                EmbeddingBacklogInterface::OPERATION,
                EmbeddingBacklogInterface::STATUS,
                EmbeddingBacklogInterface::ATTEMPT_COUNT,
                EmbeddingBacklogInterface::LAST_ERROR_CATEGORY,
            ]
        );
    }

    /**
     * @param list<int> $backlogIds
     * @param array<string, mixed> $values
     */
    private function updateItems(array $backlogIds, array $values): void
    {
        /** @var AdapterInterface $connection */
        $connection = $this->getConnection();
        $connection->update(
            $this->getMainTable(),
            $values,
            [
                EmbeddingBacklogInterface::BACKLOG_ID . ' IN (?)' => $backlogIds,
                EmbeddingBacklogInterface::STATUS . ' IN (?)' => [
                    Status::Pending->value,
                    Status::Failed->value,
                ],
            ]
        );
    }
}
