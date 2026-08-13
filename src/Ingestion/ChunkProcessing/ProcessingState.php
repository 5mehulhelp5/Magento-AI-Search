<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\ChunkProcessing;

use UnexpectedValueException;

class ProcessingState
{
    /**
     * @var array<int, ProcessingBatch>
     */
    private array $batches = [];

    /**
     * @var array<int, int>
     */
    private array $successfulBacklogVersions = [];

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
     * @param array<int, int> $backlogVersions
     */
    public function recordSuccesses(array $backlogVersions): void
    {
        $this->successfulBacklogVersions = array_replace(
            $this->successfulBacklogVersions,
            $backlogVersions
        );
        $this->processedCount += count($backlogVersions);
    }

    /**
     * @return array<int, int>
     */
    public function getSuccessfulBacklogVersions(): array
    {
        return $this->successfulBacklogVersions;
    }

    public function getProcessedCount(): int
    {
        return $this->processedCount;
    }
}
