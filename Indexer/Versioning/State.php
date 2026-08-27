<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer\Versioning;

use DavidBel\AiSearch\Indexer\Versioning\State\CacheStatus;

readonly class State
{
    public function __construct(
        public ?PhysicalIndex $active = null,
        public ?Target $target = null,
        public CacheStatus $cacheStatus = CacheStatus::Clean
    ) {
    }

    /**
     * @return array<string, array<string, mixed>|string|null>
     */
    public function toArray(): array
    {
        return [
            'active' => $this->active?->toArray(),
            'target' => $this->target?->toArray(),
            'cache_status' => $this->cacheStatus->value,
        ];
    }
}
