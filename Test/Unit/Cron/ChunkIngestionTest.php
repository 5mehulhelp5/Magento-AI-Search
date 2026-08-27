<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Cron;

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
use DavidBel\AiSearch\Log\Logger;
use DavidBel\AiSearch\Test\Unit\TestDouble\GeneratedFactoryStub;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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

        (new ChunkProcessingCron(
            $factory,
            $versioning,
            self::createStub(Logger::class)
        ))->execute();
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

        (new ChunkDeleteCron(
            $factory,
            $versioning,
            self::createStub(Logger::class)
        ))->execute();
    }

    public function testRunsIngestionChunkProcessingRetry(): void
    {
        $workflow = $this->createMock(ChunkProcessingRetry::class);
        $workflow->expects(self::once())->method('execute')->with(7);
        $versioning = $this->createMock(Versioning::class);
        $versioning->expects(self::once())->method('hasIngestionIndexVersion')->willReturn(true);
        $versioning->expects(self::once())->method('getIngestionIndexVersion')->willReturn(7);

        (new ChunkProcessingRetryCron(
            $workflow,
            $versioning,
            self::createStub(Logger::class)
        ))->execute();
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

        (new ChunkProcessingCleanupCron(
            $workflow,
            $versioning,
            self::createStub(Logger::class)
        ))->execute();
    }

    public function testChunkDeleteStopsWhenNoIngestionVersionExists(): void
    {
        $factory = $this->createMock(ChunkDeleteFactory::class);
        $factory->expects(self::never())->method('create');
        $versioning = self::createStub(Versioning::class);
        $versioning->method('hasIngestionIndexVersion')->willReturn(false);

        (new ChunkDeleteCron($factory, $versioning, self::createStub(Logger::class)))->execute();
    }

    public function testChunkRetryStopsWhenNoIngestionVersionExists(): void
    {
        $workflow = $this->createMock(ChunkProcessingRetry::class);
        $workflow->expects(self::never())->method('execute');
        $versioning = self::createStub(Versioning::class);
        $versioning->method('hasIngestionIndexVersion')->willReturn(false);

        (new ChunkProcessingRetryCron(
            $workflow,
            $versioning,
            self::createStub(Logger::class)
        ))->execute();
    }

    public function testChunkProcessingLogsAndRethrowsFailure(): void
    {
        $exception = new RuntimeException('processing failed');
        $versioning = self::createStub(Versioning::class);
        $versioning->method('activateTargetWhenReady')->willThrowException($exception);
        $logger = $this->failureLogger(ChunkProcessingCron::class, $exception);

        $this->expectExceptionObject($exception);
        (new ChunkProcessingCron(
            self::createStub(ChunkProcessingFactory::class),
            $versioning,
            $logger
        ))->execute();
    }

    public function testChunkDeleteLogsAndRethrowsFailure(): void
    {
        $exception = new RuntimeException('delete failed');
        $versioning = self::createStub(Versioning::class);
        $versioning->method('hasIngestionIndexVersion')->willThrowException($exception);
        $logger = $this->failureLogger(ChunkDeleteCron::class, $exception);

        $this->expectExceptionObject($exception);
        (new ChunkDeleteCron(
            self::createStub(ChunkDeleteFactory::class),
            $versioning,
            $logger
        ))->execute();
    }

    public function testChunkRetryLogsAndRethrowsFailure(): void
    {
        $exception = new RuntimeException('retry failed');
        $versioning = self::createStub(Versioning::class);
        $versioning->method('hasIngestionIndexVersion')->willThrowException($exception);
        $logger = $this->failureLogger(ChunkProcessingRetryCron::class, $exception);

        $this->expectExceptionObject($exception);
        (new ChunkProcessingRetryCron(
            self::createStub(ChunkProcessingRetry::class),
            $versioning,
            $logger
        ))->execute();
    }

    public function testChunkCleanupLogsAndRethrowsFailure(): void
    {
        $exception = new RuntimeException('cleanup failed');
        $versioning = self::createStub(Versioning::class);
        $versioning->method('hasIngestionIndexVersion')->willThrowException($exception);
        $logger = $this->failureLogger(ChunkProcessingCleanupCron::class, $exception);

        $this->expectExceptionObject($exception);
        (new ChunkProcessingCleanupCron(
            self::createStub(ChunkProcessingCleanup::class),
            $versioning,
            $logger
        ))->execute();
    }

    private function failureLogger(string $cronClass, RuntimeException $exception): Logger
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())->method('cronStarted')->with($cronClass);
        $logger->expects(self::once())->method('cronFailed')->with($cronClass, $exception);
        $logger->expects(self::once())->method('cronFinished')->with($cronClass);

        return $logger;
    }
}
