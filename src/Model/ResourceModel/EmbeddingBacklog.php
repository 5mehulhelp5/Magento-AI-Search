<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Model\ResourceModel;

use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Operation;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Status;
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

    protected function _construct(): void
    {
        $this->_init('davidbel_ai_search_embedding_backlog', 'backlog_id');
    }

    private function upsert(int $chunkId, Operation $operation): void
    {
        /** @var \Magento\Framework\DB\Adapter\AdapterInterface $connection */
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
}
