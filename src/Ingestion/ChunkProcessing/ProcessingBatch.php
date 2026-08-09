<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\ChunkProcessing;

use InvalidArgumentException;

class ProcessingBatch
{
    /**
     * @var non-empty-list<ProcessingItem>
     */
    private readonly array $items;

    /**
     * @param list<ProcessingItem> $items
     */
    public function __construct(array $items)
    {
        if ($items === []) {
            throw new InvalidArgumentException('A processing batch must contain at least one item.');
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

    /**
     * @return list<string>
     */
    public function getContents(): array
    {
        return array_map(
            static fn (ProcessingItem $item): string => $item->content,
            $this->items
        );
    }

    public function getLastItem(): ProcessingItem
    {
        return $this->items[array_key_last($this->items)];
    }

    /**
     * @return non-empty-list<ProcessingItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
