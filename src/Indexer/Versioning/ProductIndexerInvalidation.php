<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer\Versioning;

use DavidBel\AiSearch\Indexer\ProductIndexer;
use Magento\Framework\Indexer\IndexerRegistry;

class ProductIndexerInvalidation
{
    public function __construct(
        private readonly PhysicalIndexProvider $physicalIndexProvider,
        private readonly IndexerRegistry $indexerRegistry
    ) {
    }

    public function execute(): void
    {
        if ($this->physicalIndexProvider->getCurrent() !== null) {
            return;
        }

        $indexer = $this->indexerRegistry->get(ProductIndexer::ID);

        if ($indexer->isInvalid() || $indexer->isWorking()) {
            return;
        }

        $indexer->invalidate();
    }
}
