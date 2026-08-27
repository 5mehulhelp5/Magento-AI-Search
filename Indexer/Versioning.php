<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer;

use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndexDeleteFactory;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndexProvider;
use DavidBel\AiSearch\Indexer\Versioning\ProductIndexerInvalidationFactory;
use DavidBel\AiSearch\Indexer\Versioning\Target\ActivationFactory;
use DavidBel\AiSearch\Indexer\Versioning\Target\PreparationFactory;
use RuntimeException;

class Versioning
{
    public function __construct(
        private readonly PreparationFactory $targetPreparationFactory,
        private readonly ActivationFactory $targetActivationFactory,
        private readonly ProductIndexerInvalidationFactory $productIndexerInvalidationFactory,
        private readonly PhysicalIndexProvider $physicalIndexProvider,
        private readonly PhysicalIndexDeleteFactory $physicalIndexDeleteFactory
    ) {
    }

    public function prepareTargetForFullReindex(): void
    {
        $this->targetPreparationFactory->create()->prepare();
    }

    public function markTargetDocumentProcessingComplete(): void
    {
        $this->targetPreparationFactory->create()->markDocumentProcessingComplete();
    }

    public function getTargetIndexVersion(): int
    {
        $target = $this->physicalIndexProvider->getTarget();

        if ($target === null) {
            throw new RuntimeException('A target search index version is not available.');
        }

        return $target->number;
    }

    public function getIngestionIndexVersion(): int
    {
        $physicalIndex = $this->physicalIndexProvider->getForIngestion();

        if ($physicalIndex === null) {
            throw new RuntimeException('An ingestion search index version is not available.');
        }

        return $physicalIndex->number;
    }

    public function hasTargetIndexVersion(): bool
    {
        return $this->physicalIndexProvider->getTarget() !== null;
    }

    public function hasIngestionIndexVersion(): bool
    {
        return $this->physicalIndexProvider->getForIngestion() !== null;
    }

    public function invalidateProductIndexerWhenNeeded(): void
    {
        $this->productIndexerInvalidationFactory->create()->execute();
    }

    public function hasTargetOrActiveForCurrentConfiguration(): bool
    {
        return $this->physicalIndexProvider->getTargetForCurrentConfiguration() !== null
            || $this->physicalIndexProvider->getActiveForCurrentConfiguration() !== null;
    }

    public function getSearchIndex(bool $usePreviousDuringRebuild): ?PhysicalIndex
    {
        return $this->physicalIndexProvider->getForSearch($usePreviousDuringRebuild);
    }

    public function activateTargetWhenReady(): bool
    {
        return $this->targetActivationFactory->create()->execute();
    }

    public function deleteObsoletePhysicalIndexes(): int
    {
        return $this->physicalIndexDeleteFactory->create()->execute();
    }
}
