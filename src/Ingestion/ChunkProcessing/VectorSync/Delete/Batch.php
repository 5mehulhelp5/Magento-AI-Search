<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Delete;

use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Item;
use InvalidArgumentException;

class Batch
{
    /**
     * @var non-empty-list<Item>
     */
    private readonly array $items;

    /**
     * @param list<Item> $items
     */
    public function __construct(array $items)
    {
        if ($items === []) {
            throw new InvalidArgumentException('A deletion batch must contain at least one item.');
        }

        $this->items = $items;
    }

    /**
     * @return array<int, int>
     */
    public function getBacklogVersions(): array
    {
        $backlogVersions = [];

        foreach ($this->items as $item) {
            $backlogVersions[$item->backlogId] = $item->backlogVersion;
        }

        return $backlogVersions;
    }

    public function getLastItem(): Item
    {
        return $this->items[array_key_last($this->items)];
    }

    /**
     * @return non-empty-list<Item>
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
