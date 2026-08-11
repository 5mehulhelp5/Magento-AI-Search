<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer\Versioning;

use DavidBel\AiSearch\Indexer\Versioning\State\Flag;

class PhysicalIndexProvider
{
    public function __construct(
        private readonly Flag $stateFlag,
        private readonly ConfigurationFingerprint $configurationFingerprint
    ) {
    }

    public function getTarget(): ?PhysicalIndex
    {
        return $this->stateFlag->get()->target?->physicalIndex;
    }

    public function getTargetForCurrentConfiguration(): ?PhysicalIndex
    {
        $physicalIndex = $this->getTarget();

        if ($physicalIndex === null) {
            return null;
        }

        if ($physicalIndex->configurationFingerprint !== $this->configurationFingerprint->get()) {
            return null;
        }

        return $physicalIndex;
    }

    public function getActive(): ?PhysicalIndex
    {
        return $this->stateFlag->get()->active;
    }

    public function getActiveForCurrentConfiguration(): ?PhysicalIndex
    {
        $activeIndex = $this->getActive();

        if ($activeIndex === null
            || $activeIndex->configurationFingerprint !== $this->configurationFingerprint->get()
        ) {
            return null;
        }

        return $activeIndex;
    }

    public function getForSearch(bool $usePreviousDuringRebuild): ?PhysicalIndex
    {
        $state = $this->stateFlag->get();

        if (!$usePreviousDuringRebuild && $state->target !== null) {
            return null;
        }

        return $state->active;
    }
}
