<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync;

class Result
{
    /**
     * @param list<Item> $successfulItems
     * @param list<Item> $failedItems
     */
    public function __construct(
        private readonly array $successfulItems,
        private readonly array $failedItems
    ) {
    }

    /**
     * @return list<int>
     */
    public function getSuccessfulBacklogIds(): array
    {
        return array_map(
            static fn (Item $item): int => $item->backlogId,
            $this->successfulItems
        );
    }

    /**
     * @return list<int>
     */
    public function getFailedBacklogIds(): array
    {
        return array_map(
            static fn (Item $item): int => $item->backlogId,
            $this->failedItems
        );
    }

    /**
     * @return array<string, list<int>>
     */
    public function getSuccessfulSourceEntities(): array
    {
        $sourceEntities = [];

        foreach ($this->successfulItems as $item) {
            $sourceEntities[$item->sourceEntityType][$item->sourceEntityId] = true;
        }

        foreach ($sourceEntities as $entityType => $entityIds) {
            $sourceEntities[$entityType] = array_map('intval', array_keys($entityIds));
        }

        return $sourceEntities;
    }

    public function getSuccessfulCount(): int
    {
        return count($this->successfulItems);
    }
}
