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

    public function getCurrent(): ?PhysicalIndex
    {
        $physicalIndex = $this->get();

        if ($physicalIndex === null) {
            return null;
        }

        if ($physicalIndex->configurationFingerprint !== $this->configurationFingerprint->get()) {
            return null;
        }

        return $physicalIndex;
    }

    public function get(): ?PhysicalIndex
    {
        $state = $this->stateFlag->get();

        return $state->target->physicalIndex ?? $state->active;
    }

    public function getActive(): ?PhysicalIndex
    {
        return $this->stateFlag->get()->active;
    }

    public function getCurrentActive(): ?PhysicalIndex
    {
        $activeIndex = $this->getActive();

        if ($activeIndex === null
            || $activeIndex->configurationFingerprint !== $this->configurationFingerprint->get()
        ) {
            return null;
        }

        return $activeIndex;
    }
}
