<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow\ChunkProcessing;

use UnexpectedValueException;

class ProcessingState
{
    /**
     * @var array<int, ProcessingBatch>
     */
    private array $batches = [];

    /**
     * @var list<int>
     */
    private array $successfulBacklogIds = [];

    private int $processedCount = 0;
    private readonly int $startedAt;

    public function __construct()
    {
        $this->startedAt = hrtime(true);
    }

    public function isWithinRuntime(int $maxRuntimeNanoseconds): bool
    {
        return hrtime(true) - $this->startedAt < $maxRuntimeNanoseconds;
    }

    public function addBatch(int $batchId, ProcessingBatch $batch): void
    {
        $this->batches[$batchId] = $batch;
    }

    public function getBatch(int $batchId): ProcessingBatch
    {
        if (!isset($this->batches[$batchId])) {
            throw new UnexpectedValueException('The completed processing batch is unknown.');
        }

        return $this->batches[$batchId];
    }

    public function removeBatch(int $batchId): void
    {
        unset($this->batches[$batchId]);
    }

    /**
     * @param list<int> $backlogIds
     */
    public function recordSuccesses(array $backlogIds): void
    {
        $this->successfulBacklogIds = array_merge(
            $this->successfulBacklogIds,
            $backlogIds
        );
        $this->processedCount += count($backlogIds);
    }

    /**
     * @return list<int>
     */
    public function getSuccessfulBacklogIds(): array
    {
        return $this->successfulBacklogIds;
    }

    public function getProcessedCount(): int
    {
        return $this->processedCount;
    }
}
