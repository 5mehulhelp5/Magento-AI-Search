<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer\ProductAttributeOptionIndexer;

use DavidBel\AiSearch\Indexer\ProductIndexer;
use Magento\Framework\Indexer\IndexerRegistry;

class ProductIndexerPublisher
{
    public function __construct(
        private readonly IndexerRegistry $indexerRegistry
    ) {
    }

    /**
     * @param list<int> $productIds
     */
    public function publishProductIds(array $productIds): void
    {
        if ($productIds === []) {
            return;
        }

        $productIndexer = $this->indexerRegistry->get(ProductIndexer::ID);

        if ($productIndexer->isScheduled()) {
            /** @var \Magento\Framework\Mview\View\Changelog $productChangelog */
            $productChangelog = $productIndexer->getView()->getChangelog();
            $productChangelog->addList($productIds);

            return;
        }

        $productIndexer->reindexList($productIds);
    }

    public function invalidateProductIndexer(): void
    {
        $this->indexerRegistry->get(ProductIndexer::ID)->invalidate();
    }
}
