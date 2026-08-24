<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Ingestion\ChunkProcessing;

use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingBatch;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingItem;
use InvalidArgumentException;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingState;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class ProcessingStateTest extends TestCase
{
    public function testTracksBatchesAndSuccessfulVersions(): void
    {
        $firstBatch = self::createStub(ProcessingBatch::class);
        $secondBatch = self::createStub(ProcessingBatch::class);
        $state = new ProcessingState();

        $state->addBatch(1, $firstBatch);
        $state->addBatch(2, $secondBatch);
        self::assertSame($firstBatch, $state->getBatch(1));
        self::assertSame($secondBatch, $state->getBatch(2));

        $state->recordSuccesses([10 => 1, 20 => 2]);
        $state->recordSuccesses([10 => 3, 30 => 1]);

        self::assertSame([10 => 3, 20 => 2, 30 => 1], $state->getSuccessfulBacklogVersions());
        self::assertSame(4, $state->getProcessedCount());

        $state->removeBatch(1);
        $this->expectException(UnexpectedValueException::class);
        $state->getBatch(1);
    }

    public function testHonorsRuntimeLimit(): void
    {
        $state = new ProcessingState();

        self::assertTrue($state->isWithinRuntime(PHP_INT_MAX));
        self::assertFalse($state->isWithinRuntime(0));
    }

    public function testStopsAcceptingWork(): void
    {
        $state = new ProcessingState();

        $state->stopAcceptingWork();

        self::assertFalse($state->isWithinRuntime(PHP_INT_MAX));
    }

    public function testProcessingBatchRejectsEmptyItems(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ProcessingBatch([]);
    }

    public function testProcessingBatchReportsIndexAndUniqueSourceEntities(): void
    {
        $first = $this->processingItem(10, 50);
        $duplicate = $this->processingItem(20, 50);
        $second = $this->processingItem(30, 60);
        $batch = new ProcessingBatch([$first, $duplicate, $second]);

        self::assertSame(7, $batch->getIndexVersion());
        self::assertSame([50, 60], $batch->getSourceEntityIds());
        self::assertSame([$first, $duplicate, $second], $batch->getItems());
    }

    private function processingItem(int $backlogId, int $sourceEntityId): ProcessingItem
    {
        return new ProcessingItem(
            $backlogId,
            1,
            '2026-08-24 10:00:00',
            $backlogId,
            'product',
            $sourceEntityId,
            1,
            'description',
            0,
            'content',
            'hash',
            null,
            7
        );
    }
}
