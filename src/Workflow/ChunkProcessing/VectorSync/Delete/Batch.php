<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync\Delete;

use DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync\Item;
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
     * @return list<int>
     */
    public function getBacklogIds(): array
    {
        return array_map(
            static fn (Item $item): int => $item->backlogId,
            $this->items
        );
    }

    /**
     * @return non-empty-list<Item>
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
