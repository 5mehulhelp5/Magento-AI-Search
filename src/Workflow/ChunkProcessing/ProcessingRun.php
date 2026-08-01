<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow\ChunkProcessing;

use Throwable;
use UnexpectedValueException;

class ProcessingRun
{
    /**
     * @var array<int, ProcessingBatch>
     */
    private array $batches = [];

    /**
     * @var list<int>
     */
    private array $successfulBacklogIds = [];

    private bool $acceptNewWork = true;
    private int $processedCount = 0;
    private ?Throwable $failure = null;
    private readonly int $startedAt;

    public function __construct()
    {
        $this->startedAt = hrtime(true);
    }

    public function canAcceptWork(int $maxRuntimeNanoseconds): bool
    {
        return $this->acceptNewWork
            && hrtime(true) - $this->startedAt < $maxRuntimeNanoseconds;
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

    public function stop(Throwable $failure): void
    {
        $this->acceptNewWork = false;
        $this->failure ??= $failure;
    }

    public function getResult(): int
    {
        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }

        return $this->processedCount;
    }
}
