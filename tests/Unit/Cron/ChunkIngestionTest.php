<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Cron;

use DavidBel\AiSearch\Cron\ChunkDelete as ChunkDeleteCron;
use DavidBel\AiSearch\Cron\ChunkProcessing as ChunkProcessingCron;
use DavidBel\AiSearch\Cron\ChunkProcessingCleanup as ChunkProcessingCleanupCron;
use DavidBel\AiSearch\Cron\ChunkProcessingRetry as ChunkProcessingRetryCron;
use DavidBel\AiSearch\Indexer\Versioning;
use DavidBel\AiSearch\Ingestion\ChunkDelete;
use DavidBel\AiSearch\Ingestion\ChunkDeleteFactory;
use DavidBel\AiSearch\Ingestion\ChunkProcessing;
use DavidBel\AiSearch\Ingestion\ChunkProcessingCleanup;
use DavidBel\AiSearch\Ingestion\ChunkProcessingFactory;
use DavidBel\AiSearch\Ingestion\ChunkProcessingRetry;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use PHPUnit\Framework\TestCase;

class ChunkIngestionTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(
            ChunkProcessingFactory::class,
            ChunkDeleteFactory::class
        );
    }

    public function testRunsAFactoryCreatedChunkProcessingWorkflow(): void
    {
        $workflow = $this->createMock(ChunkProcessing::class);
        $workflow->expects(self::once())->method('execute')->with(7);
        $factory = $this->createMock(ChunkProcessingFactory::class);
        $factory->expects(self::once())->method('create')->willReturn($workflow);
        $versioning = $this->createMock(Versioning::class);
        $versioning->expects(self::once())->method('hasIngestionIndexVersion')->willReturn(true);
        $versioning->expects(self::once())->method('getIngestionIndexVersion')->willReturn(7);
        $versioning->expects(self::once())->method('activateTargetWhenReady');

        (new ChunkProcessingCron($factory, $versioning))->execute();
    }

    public function testRunsAFactoryCreatedChunkDeleteWorkflow(): void
    {
        $workflow = $this->createMock(ChunkDelete::class);
        $workflow->expects(self::once())->method('execute')->with(7);
        $factory = $this->createMock(ChunkDeleteFactory::class);
        $factory->expects(self::once())->method('create')->willReturn($workflow);
        $versioning = $this->createMock(Versioning::class);
        $versioning->expects(self::once())->method('hasIngestionIndexVersion')->willReturn(true);
        $versioning->expects(self::once())->method('getIngestionIndexVersion')->willReturn(7);

        (new ChunkDeleteCron($factory, $versioning))->execute();
    }

    public function testRunsIngestionChunkProcessingRetry(): void
    {
        $workflow = $this->createMock(ChunkProcessingRetry::class);
        $workflow->expects(self::once())->method('execute')->with(7);
        $versioning = $this->createMock(Versioning::class);
        $versioning->expects(self::once())->method('hasIngestionIndexVersion')->willReturn(true);
        $versioning->expects(self::once())->method('getIngestionIndexVersion')->willReturn(7);

        (new ChunkProcessingRetryCron($workflow, $versioning))->execute();
    }

    public function testRunsIngestionChunkProcessingCleanup(): void
    {
        $workflow = $this->createMock(ChunkProcessingCleanup::class);
        $workflow->expects(self::once())->method('execute')->with(7, 8);
        $versioning = $this->createMock(Versioning::class);
        $versioning->expects(self::once())->method('hasIngestionIndexVersion')->willReturn(true);
        $versioning->expects(self::once())->method('getIngestionIndexVersion')->willReturn(7);
        $versioning->expects(self::once())->method('hasTargetIndexVersion')->willReturn(true);
        $versioning->expects(self::once())->method('getTargetIndexVersion')->willReturn(8);
        $versioning->expects(self::once())->method('deleteObsoletePhysicalIndexes');

        (new ChunkProcessingCleanupCron($workflow, $versioning))->execute();
    }
}
