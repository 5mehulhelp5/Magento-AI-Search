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
    public function saveByChunkId(int $chunkId): void
    {
        $this->upsert($chunkId, Operation::Upsert);
    }

    public function deleteByChunkId(int $chunkId): void
    {
        $this->upsert($chunkId, Operation::Deletion);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getUpsertsForEmbedding(int $limit): array
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('The embedding backlog batch limit must be positive.');
        }

        /** @var AdapterInterface $connection */
        $connection = $this->getConnection();
        /** @var list<array<string, mixed>> $rows */
        $rows = $connection->fetchAll($this->createUpsertSelect($connection, $limit));

        return $rows;
    }

    /**
     * @param list<int> $backlogIds
     */
    public function markEmbeddedByIds(array $backlogIds): void
    {
        if ($backlogIds === []) {
            return;
        }

        $this->updateUpserts(
            $backlogIds,
            [
                EmbeddingBacklogInterface::STATUS => Status::Embedded->value,
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

        $this->updateUpserts(
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

    protected function _construct(): void
    {
        $this->_init('davidbel_ai_search_embedding_backlog', 'backlog_id');
    }

    private function createUpsertSelect(AdapterInterface $connection, int $limit): Select
    {
        return $connection->select()
            ->from(
                ['backlog' => $this->getMainTable()],
                [
                    EmbeddingBacklogInterface::BACKLOG_ID,
                    EmbeddingBacklogInterface::CHUNK_ID,
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
            )
            ->where('backlog.operation = ?', Operation::Upsert->value)
            ->where(
                'backlog.status IN (?)',
                [Status::Pending->value, Status::Failed->value]
            )
            ->order([
                'backlog.updated_at ASC',
                'backlog.backlog_id ASC',
            ])
            ->limit($limit);
    }

    private function upsert(int $chunkId, Operation $operation): void
    {
        /** @var AdapterInterface $connection */
        $connection = $this->getConnection();

        $connection->insertOnDuplicate(
            $this->getMainTable(),
            [
                EmbeddingBacklogInterface::CHUNK_ID => $chunkId,
                EmbeddingBacklogInterface::OPERATION => $operation->value,
                EmbeddingBacklogInterface::STATUS => Status::Pending->value,
                EmbeddingBacklogInterface::ATTEMPT_COUNT => 0,
                EmbeddingBacklogInterface::LAST_ERROR_CATEGORY => null,
            ],
            [
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
    private function updateUpserts(array $backlogIds, array $values): void
    {
        /** @var AdapterInterface $connection */
        $connection = $this->getConnection();
        $connection->update(
            $this->getMainTable(),
            $values,
            [
                EmbeddingBacklogInterface::BACKLOG_ID . ' IN (?)' => $backlogIds,
                EmbeddingBacklogInterface::OPERATION . ' = ?' => Operation::Upsert->value,
                EmbeddingBacklogInterface::STATUS . ' IN (?)' => [
                    Status::Pending->value,
                    Status::Failed->value,
                ],
            ]
        );
    }
}
