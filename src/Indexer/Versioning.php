<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer;

use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndexProvider;
use DavidBel\AiSearch\Indexer\Versioning\ProductIndexerInvalidationFactory;
use DavidBel\AiSearch\Indexer\Versioning\Target\ActivationFactory;
use DavidBel\AiSearch\Indexer\Versioning\Target\PreparationFactory;

class Versioning
{
    public function __construct(
        private readonly PreparationFactory $targetPreparationFactory,
        private readonly ActivationFactory $targetActivationFactory,
        private readonly ProductIndexerInvalidationFactory $productIndexerInvalidationFactory,
        private readonly PhysicalIndexProvider $physicalIndexProvider
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

    public function invalidateProductIndexerWhenNeeded(): void
    {
        $this->productIndexerInvalidationFactory->create()->execute();
    }

    public function hasTargetOrActiveForCurrentConfiguration(): bool
    {
        return $this->physicalIndexProvider->getTargetForCurrentConfiguration() !== null
            || $this->physicalIndexProvider->getActiveForCurrentConfiguration() !== null;
    }

    public function getActiveVersion(): ?PhysicalIndex
    {
        return $this->physicalIndexProvider->getActive();
    }

    public function getActiveVersionForCurrentConfiguration(): ?PhysicalIndex
    {
        return $this->physicalIndexProvider->getActiveForCurrentConfiguration();
    }

    public function activateTargetWhenReady(): bool
    {
        return $this->targetActivationFactory->create()->execute();
    }
}
