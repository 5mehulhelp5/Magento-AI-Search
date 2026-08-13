<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer\Versioning\Target\Activation;

use DavidBel\AiSearch\Indexer\ProductIndexer;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Mview\View\ChangelogTableNotExistsException;

class Readiness
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly IndexerRegistry $indexerRegistry
    ) {
    }

    public function isReady(): bool
    {
        if (!$this->isProductIndexerReady()) {
            return false;
        }

        return !$this->collectionFactory
            ->create()
            ->getResourceModel()
            ->hasPendingOrFailedItems();
    }

    private function isProductIndexerReady(): bool
    {
        $indexer = $this->indexerRegistry->get(ProductIndexer::ID);

        if (!$indexer->isValid()) {
            return false;
        }

        $view = $indexer->getView();

        if (!$view->isIdle()) {
            return false;
        }

        if (!$view->isEnabled()) {
            return true;
        }

        try {
            $currentVersion = $view->getChangelog()->getVersion();
        } catch (ChangelogTableNotExistsException) {
            return true;
        }

        return (int) $view->getState()->getVersionId() >= $currentVersion;
    }
}
