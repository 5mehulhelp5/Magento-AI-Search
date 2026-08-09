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

    public function getCurrentWriteVersion(): ?PhysicalIndex
    {
        return $this->physicalIndexProvider->getCurrent();
    }

    public function getWriteVersion(): ?PhysicalIndex
    {
        return $this->physicalIndexProvider->get();
    }

    public function getActiveVersion(): ?PhysicalIndex
    {
        return $this->physicalIndexProvider->getActive();
    }

    public function getCurrentActiveVersion(): ?PhysicalIndex
    {
        return $this->physicalIndexProvider->getCurrentActive();
    }

    public function activateTargetWhenReady(): bool
    {
        return $this->targetActivationFactory->create()->execute();
    }
}
