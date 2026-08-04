<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Cron;

use DavidBel\AiSearch\Cron\ChunkDeletion as ChunkDeletionCron;
use DavidBel\AiSearch\Cron\ChunkProcessing as ChunkProcessingCron;
use DavidBel\AiSearch\Cron\ChunkProcessingCleanup as ChunkProcessingCleanupCron;
use DavidBel\AiSearch\Cron\ChunkProcessingRetry as ChunkProcessingRetryCron;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use DavidBel\AiSearch\Workflow\ChunkDeletion;
use DavidBel\AiSearch\Workflow\ChunkDeletionFactory;
use DavidBel\AiSearch\Workflow\ChunkProcessing;
use DavidBel\AiSearch\Workflow\ChunkProcessingCleanup;
use DavidBel\AiSearch\Workflow\ChunkProcessingFactory;
use DavidBel\AiSearch\Workflow\ChunkProcessingRetry;
use PHPUnit\Framework\TestCase;

class ChunkWorkflowsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(
            ChunkProcessingFactory::class,
            ChunkDeletionFactory::class
        );
    }

    public function testRunsAFactoryCreatedChunkProcessingWorkflow(): void
    {
        $workflow = $this->createMock(ChunkProcessing::class);
        $workflow->expects(self::once())->method('execute');
        $factory = $this->createMock(ChunkProcessingFactory::class);
        $factory->expects(self::once())->method('create')->willReturn($workflow);

        (new ChunkProcessingCron($factory))->execute();
    }

    public function testRunsAFactoryCreatedChunkDeletionWorkflow(): void
    {
        $workflow = $this->createMock(ChunkDeletion::class);
        $workflow->expects(self::once())->method('execute');
        $factory = $this->createMock(ChunkDeletionFactory::class);
        $factory->expects(self::once())->method('create')->willReturn($workflow);

        (new ChunkDeletionCron($factory))->execute();
    }

    public function testRunsChunkProcessingRetryWorkflow(): void
    {
        $workflow = $this->createMock(ChunkProcessingRetry::class);
        $workflow->expects(self::once())->method('execute');

        (new ChunkProcessingRetryCron($workflow))->execute();
    }

    public function testRunsChunkProcessingCleanupWorkflow(): void
    {
        $workflow = $this->createMock(ChunkProcessingCleanup::class);
        $workflow->expects(self::once())->method('execute');

        (new ChunkProcessingCleanupCron($workflow))->execute();
    }
}
