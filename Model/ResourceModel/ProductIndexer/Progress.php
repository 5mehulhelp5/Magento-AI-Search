<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Model\ResourceModel\ProductIndexer;

use DavidBel\AiSearch\Indexer\ProductIndexer;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Sql\Expression;

class Progress
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function getQueuedProductCount(): int
    {
        $connection = $this->resourceConnection->getConnection();
        $processedVersion = $this->getProcessedVersion($connection);
        $select = $connection->select()
            ->from(
                $this->resourceConnection->getTableName(ProductIndexer::VIEW_ID . '_cl'),
                [new Expression('COUNT(DISTINCT entity_id)')]
            )
            ->where('version_id > ?', $processedVersion);

        return (int) $connection->fetchOne($select);
    }

    private function getProcessedVersion(AdapterInterface $connection): int
    {
        $select = $connection->select()
            ->from(
                $this->resourceConnection->getTableName('mview_state'),
                ['version_id']
            )
            ->where('view_id = ?', ProductIndexer::VIEW_ID)
            ->limit(1);

        return (int) $connection->fetchOne($select);
    }
}
