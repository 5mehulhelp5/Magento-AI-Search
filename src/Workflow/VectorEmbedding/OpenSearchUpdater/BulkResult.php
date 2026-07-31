<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow\VectorEmbedding\OpenSearchUpdater;

class BulkResult
{
    /**
     * @param list<ChunkDocument> $successfulDocuments
     * @param list<ChunkDocument> $failedDocuments
     */
    public function __construct(
        private readonly array $successfulDocuments,
        private readonly array $failedDocuments
    ) {
    }

    /**
     * @return list<int>
     */
    public function getSuccessfulBacklogIds(): array
    {
        return array_map(
            static fn (ChunkDocument $document): int => $document->backlogId,
            $this->successfulDocuments
        );
    }

    /**
     * @return list<int>
     */
    public function getFailedBacklogIds(): array
    {
        return array_map(
            static fn (ChunkDocument $document): int => $document->backlogId,
            $this->failedDocuments
        );
    }

    /**
     * @return list<int>
     */
    public function getSuccessfulSourceEntityIds(string $sourceEntityType): array
    {
        $sourceEntityIds = [];

        foreach ($this->successfulDocuments as $document) {
            if ($document->sourceEntityType !== $sourceEntityType) {
                continue;
            }

            $sourceEntityIds[$document->sourceEntityId] = true;
        }

        return array_map('intval', array_keys($sourceEntityIds));
    }

    public function getSuccessfulCount(): int
    {
        return count($this->successfulDocuments);
    }
}
