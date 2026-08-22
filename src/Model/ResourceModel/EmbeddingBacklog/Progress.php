<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog;

use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Status;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as EmbeddingBacklogResource;
use Magento\Framework\DB\Sql\Expression;

class Progress
{
    private const array STATUSES = [
        Status::Pending->value,
        Status::Failed->value,
        Status::Outdated->value,
        Status::Done->value,
    ];

    private ?EmbeddingBacklogResource $resource = null;

    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @return list<array{
     *     operation: string,
     *     index_version: int,
     *     full_reindex_status: int,
     *     status: string,
     *     item_count: int
     * }>
     */
    public function getItemCounts(): array
    {
        $resource = $this->getResource();
        /** @var \Magento\Framework\DB\Adapter\AdapterInterface $connection */
        $connection = $resource->getConnection();
        $select = $connection->select()
            ->from(
                $resource->getMainTable(),
                [
                    'operation' => EmbeddingBacklogInterface::OPERATION,
                    'index_version' => EmbeddingBacklogInterface::INDEX_VERSION,
                    'full_reindex_status' => EmbeddingBacklogInterface::FULL_REINDEX_STATUS,
                    'status' => EmbeddingBacklogInterface::STATUS,
                    'item_count' => new Expression('COUNT(*)'),
                ]
            )
            ->where(EmbeddingBacklogInterface::STATUS . ' IN (?)', self::STATUSES)
            ->group([
                EmbeddingBacklogInterface::INDEX_VERSION,
                EmbeddingBacklogInterface::OPERATION,
                EmbeddingBacklogInterface::FULL_REINDEX_STATUS,
                EmbeddingBacklogInterface::STATUS,
            ])
            ->order([
                EmbeddingBacklogInterface::INDEX_VERSION . ' DESC',
                EmbeddingBacklogInterface::OPERATION . ' ASC',
                EmbeddingBacklogInterface::FULL_REINDEX_STATUS . ' ASC',
                EmbeddingBacklogInterface::STATUS . ' ASC',
            ]);

        /** @var list<array{
         *     operation: string,
         *     index_version: int|string,
         *     full_reindex_status: int|string,
         *     status: string,
         *     item_count: int|string
         * }> $rows
         */
        $rows = $connection->fetchAll($select);

        return $this->convertRowsToItemCounts($rows);
    }

    /**
     * @param list<array{
     *     operation: string,
     *     index_version: int|string,
     *     full_reindex_status: int|string,
     *     status: string,
     *     item_count: int|string
     * }> $rows
     * @return list<array{
     *     operation: string,
     *     index_version: int,
     *     full_reindex_status: int,
     *     status: string,
     *     item_count: int
     * }>
     */
    private function convertRowsToItemCounts(array $rows): array
    {
        $itemCounts = [];

        foreach ($rows as $row) {
            $itemCounts[] = [
                'operation' => $row['operation'],
                'index_version' => (int) $row['index_version'],
                'full_reindex_status' => (int) $row['full_reindex_status'],
                'status' => $row['status'],
                'item_count' => (int) $row['item_count'],
            ];
        }

        return $itemCounts;
    }

    private function getResource(): EmbeddingBacklogResource
    {
        $this->resource ??= $this->collectionFactory->create()->getResourceModel();

        return $this->resource;
    }
}
