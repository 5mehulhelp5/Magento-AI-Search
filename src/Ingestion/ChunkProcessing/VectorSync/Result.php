<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync;

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
     * @return array<int, int>
     */
    public function getSuccessfulBacklogVersions(): array
    {
        return $this->getBacklogVersions($this->successfulItems);
    }

    /**
     * @return array<int, int>
     */
    public function getFailedBacklogVersions(): array
    {
        return $this->getBacklogVersions($this->failedItems);
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

    /**
     * @param list<Item> $items
     * @return array<int, int>
     */
    private function getBacklogVersions(array $items): array
    {
        $backlogVersions = [];

        foreach ($items as $item) {
            $backlogVersions[$item->backlogId] = $item->backlogVersion;
        }

        return $backlogVersions;
    }
}
