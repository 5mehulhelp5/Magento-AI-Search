<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Workflow\VectorEmbedding;

use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Collection;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingBatch;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingExecution;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingInput;
use DavidBel\AiSearch\Workflow\VectorEmbedding\OpenSearchUpdater;
use DavidBel\AiSearch\Workflow\VectorEmbedding\OpenSearchUpdater\BulkResult;
use DavidBel\AiSearch\Workflow\VectorEmbedding\OpenSearchUpdater\ChunkDocument;
use DavidBel\AiSearch\Workflow\VectorEmbedding\ProductCacheCleaner;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnexpectedValueException;

class EmbeddingExecutionTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(CollectionFactory::class);
    }

    public function testStartsCacheCleaningAndHonorsDeadlineAndStopState(): void
    {
        $cacheCleaner = $this->createMock(ProductCacheCleaner::class);
        $cacheCleaner->expects(self::once())
            ->method('start');
        $execution = $this->createExecutionWithoutResource($cacheCleaner);

        $execution->start();

        self::assertTrue($execution->canAcceptWork(PHP_INT_MAX));
        self::assertFalse($execution->canAcceptWork(0));
        $execution->stop(new RuntimeException('stopped'));
        self::assertFalse($execution->canAcceptWork(PHP_INT_MAX));
    }

    public function testLoadsPendingRowsThroughOneCachedResource(): void
    {
        $calls = [];
        $resource = $this->createMock(EmbeddingBacklog::class);
        $resource->expects(self::exactly(2))
            ->method('getPendingUpsertsForEmbedding')
            ->willReturnCallback(
                static function (
                    int $limit,
                    ?string $updatedAt,
                    ?int $backlogId
                ) use (&$calls): array {
                    $calls[] = [$limit, $updatedAt, $backlogId];

                    return [['backlog_id' => count($calls)]];
                }
            );
        $execution = $this->createExecution(
            $resource,
            self::createStub(OpenSearchUpdater::class),
            self::createStub(ProductCacheCleaner::class)
        );

        self::assertSame(
            [['backlog_id' => 1]],
            $execution->getPendingUpserts(100, null, null)
        );
        self::assertSame(
            [['backlog_id' => 2]],
            $execution->getPendingUpserts(100, '2026-07-31 10:00:00', 42)
        );
        self::assertSame(
            [
                [100, null, null],
                [100, '2026-07-31 10:00:00', 42],
            ],
            $calls
        );
    }

    public function testFinishesSuccessfulBatchAndMarksBacklogDone(): void
    {
        $batch = self::batch(1, 2);
        $vectors = [[0.1], [0.2]];
        $result = new BulkResult(
            [self::document(1, 501), self::document(2, 501)],
            []
        );
        $resource = $this->createMock(EmbeddingBacklog::class);
        $resource->expects(self::once())
            ->method('markDoneByIds')
            ->with([1, 2]);
        $resource->expects(self::never())
            ->method('markFailedByIds');
        $updater = $this->createMock(OpenSearchUpdater::class);
        $updater->expects(self::once())
            ->method('update')
            ->with($resource, $batch, $vectors)
            ->willReturn($result);
        $cacheCleaner = $this->createMock(ProductCacheCleaner::class);
        $cacheCleaner->expects(self::once())
            ->method('register')
            ->with([501]);
        $cacheCleaner->expects(self::once())
            ->method('flush');
        $execution = $this->createExecution($resource, $updater, $cacheCleaner);

        $execution->addBatch(10, $batch);
        $execution->fulfilled($vectors, 10);

        self::assertSame(2, $execution->finish());
    }

    public function testPartialOpenSearchFailureMarksSuccessesThenThrows(): void
    {
        $batch = self::batch(1, 2);
        $resource = $this->createMock(EmbeddingBacklog::class);
        $resource->expects(self::once())
            ->method('markDoneByIds')
            ->with([1]);
        $updater = self::createStub(OpenSearchUpdater::class);
        $updater->method('update')
            ->willReturn(
                new BulkResult(
                    [self::document(1, 501)],
                    [self::document(2, 502)]
                )
            );
        $cacheCleaner = $this->createMock(ProductCacheCleaner::class);
        $cacheCleaner->expects(self::once())
            ->method('register')
            ->with([501]);
        $cacheCleaner->expects(self::once())
            ->method('flush');
        $execution = $this->createExecution($resource, $updater, $cacheCleaner);
        $execution->addBatch(0, $batch);
        $execution->fulfilled([[0.1], [0.2]], 0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'OpenSearch rejected one or more chunk documents.'
        );

        $execution->finish();
    }

    /**
     * @return iterable<string, array{mixed, class-string<\Throwable>, string}>
     */
    public static function rejectedReasons(): iterable
    {
        yield 'exception reason' => [
            new LogicException('embedding failed'),
            LogicException::class,
            'embedding failed',
        ];
        yield 'non-exception reason' => [
            'embedding failed',
            RuntimeException::class,
            'The embedding request failed without an exception.',
        ];
    }

    /**
     * @param class-string<\Throwable> $exceptionClass
     */
    #[DataProvider('rejectedReasons')]
    public function testRejectedBatchIsMarkedFailedAndRethrown(
        mixed $reason,
        string $exceptionClass,
        string $exceptionMessage
    ): void {
        $batch = self::batch(7, 8);
        $resource = $this->createMock(EmbeddingBacklog::class);
        $resource->expects(self::once())
            ->method('markFailedByIds')
            ->with([7, 8], 'embedder');
        $resource->expects(self::once())
            ->method('markDoneByIds')
            ->with([]);
        $cacheCleaner = $this->createMock(ProductCacheCleaner::class);
        $cacheCleaner->expects(self::once())
            ->method('flush');
        $execution = $this->createExecution(
            $resource,
            self::createStub(OpenSearchUpdater::class),
            $cacheCleaner
        );
        $execution->addBatch(3, $batch);
        $execution->rejected($reason, 3);

        $this->expectException($exceptionClass);
        $this->expectExceptionMessage($exceptionMessage);

        $execution->finish();
    }

    public function testCacheFailureMarksSuccessfulBacklogIdsFailed(): void
    {
        $failure = new RuntimeException('cache flush failed');
        $batch = self::batch(9);
        $resource = $this->createMock(EmbeddingBacklog::class);
        $resource->expects(self::once())
            ->method('markFailedByIds')
            ->with([9], 'cache');
        $resource->expects(self::never())
            ->method('markDoneByIds');
        $updater = self::createStub(OpenSearchUpdater::class);
        $updater->method('update')
            ->willReturn(new BulkResult([self::document(9, 509)], []));
        $cacheCleaner = $this->createMock(ProductCacheCleaner::class);
        $cacheCleaner->expects(self::once())
            ->method('register')
            ->with([509]);
        $cacheCleaner->expects(self::once())
            ->method('flush')
            ->willThrowException($failure);
        $execution = $this->createExecution($resource, $updater, $cacheCleaner);
        $execution->addBatch(0, $batch);
        $execution->fulfilled([[0.1]], 0);

        $this->expectExceptionObject($failure);

        $execution->finish();
    }

    public function testUnknownCompletedBatchFailsAtFinish(): void
    {
        $resource = $this->createMock(EmbeddingBacklog::class);
        $resource->expects(self::once())
            ->method('markDoneByIds')
            ->with([]);
        $updater = $this->createMock(OpenSearchUpdater::class);
        $updater->expects(self::never())
            ->method('update');
        $cacheCleaner = $this->createMock(ProductCacheCleaner::class);
        $cacheCleaner->expects(self::once())
            ->method('flush');
        $execution = $this->createExecution($resource, $updater, $cacheCleaner);
        $execution->fulfilled([], 99);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'The completed embedding batch is unknown.'
        );

        $execution->finish();
    }

    public function testStopPreservesTheFirstFailure(): void
    {
        $first = new LogicException('first failure');
        $resource = $this->createMock(EmbeddingBacklog::class);
        $resource->expects(self::once())
            ->method('markDoneByIds')
            ->with([]);
        $cacheCleaner = $this->createMock(ProductCacheCleaner::class);
        $cacheCleaner->expects(self::once())
            ->method('flush');
        $execution = $this->createExecution(
            $resource,
            self::createStub(OpenSearchUpdater::class),
            $cacheCleaner
        );
        $execution->stop($first);
        $execution->stop(new RuntimeException('second failure'));

        $this->expectExceptionObject($first);

        $execution->finish();
    }

    private function createExecution(
        EmbeddingBacklog $resource,
        OpenSearchUpdater $updater,
        ProductCacheCleaner $cacheCleaner
    ): EmbeddingExecution {
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())
            ->method('getResourceModel')
            ->willReturn($resource);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->expects(self::once())
            ->method('create')
            ->willReturn($collection);

        return new EmbeddingExecution(
            $collectionFactory,
            $updater,
            $cacheCleaner
        );
    }

    private function createExecutionWithoutResource(
        ProductCacheCleaner $cacheCleaner
    ): EmbeddingExecution {
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->expects(self::never())
            ->method('create');

        return new EmbeddingExecution(
            $collectionFactory,
            self::createStub(OpenSearchUpdater::class),
            $cacheCleaner
        );
    }

    private static function batch(int ...$backlogIds): EmbeddingBatch
    {
        $inputs = [];

        foreach ($backlogIds as $backlogId) {
            $inputs[] = new EmbeddingInput(
                $backlogId,
                '2026-07-31 10:00:00',
                $backlogId + 100,
                'product',
                $backlogId + 500,
                1,
                'description',
                0,
                'content-' . $backlogId,
                'hash-' . $backlogId
            );
        }

        return new EmbeddingBatch($inputs);
    }

    private static function document(
        int $backlogId,
        int $sourceEntityId
    ): ChunkDocument {
        return new ChunkDocument(
            $backlogId,
            $backlogId + 100,
            'product',
            $sourceEntityId,
            1,
            'description',
            0,
            'content-' . $backlogId,
            'hash-' . $backlogId,
            [0.1]
        );
    }
}
