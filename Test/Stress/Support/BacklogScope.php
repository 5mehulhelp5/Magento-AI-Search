<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Stress\Support;

use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use RuntimeException;

class BacklogScope
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @param list<int> $productIds
     */
    public function keepOnlyProductIds(array $productIds): void
    {
        if ($productIds === []) {
            return;
        }

        $resource = $this->collectionFactory->create()->getResourceModel();
        $connection = $resource->getConnection();

        if (!$connection instanceof AdapterInterface) {
            throw new RuntimeException('The AI search database connection is unavailable.');
        }

        $connection->delete(
            $resource->getMainTable(),
            ['source_entity_id NOT IN (?)' => $productIds]
        );
    }
}
