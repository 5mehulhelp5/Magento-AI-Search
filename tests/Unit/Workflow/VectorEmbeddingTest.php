<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Workflow;

use Closure;
use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Api\EmbedderClientInterface;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Collection;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use DavidBel\AiSearch\Workflow\VectorEmbedding;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingBatch;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingBatchFactory;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingInputMapper;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingPromisePool;
use Generator;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnexpectedValueException;

class VectorEmbeddingTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(
            CollectionFactory::class,
            EmbeddingBatchFactory::class
        );
    }

    public function testProcessesPendingRowsInCursorBasedBatches(): void
    {
        $firstRows = [
            self::row(1, '2026-07-31 10:00:00', 'first'),
            self::row(2, '2026-07-31 10:00:00', 'second'),
        ];
        $secondRows = [
            self::row(3, '2026-07-31 10:01:00', 'third'),
        ];
        $queryCalls = [];
        $embeddedIds = [];
        $embedderInputs = [];
        $resource = $this->createSuccessfulResource(
            [$firstRows, $secondRows, []],
            $queryCalls,
            $embeddedIds
        );
        $workflow = $this->createWorkflow(
            $resource,
            $this->createSuccessfulEmbedder($embedderInputs),
            $this->createBatchFactory(2),
            $this->createSynchronousPromisePool()
        );

        self::assertSame(3, $workflow->execute());
        self::assertSame(
            [
                [100, null, null],
                [100, '2026-07-31 10:00:00', 2],
                [100, '2026-07-31 10:01:00', 3],
            ],
            $queryCalls
        );
        self::assertSame(
            [
                ['first', 'second'],
                ['third'],
            ],
            $embedderInputs
        );
        self::assertSame([[1, 2], [3]], $embeddedIds);
    }

    /**
     * @param list<list<array<string, mixed>>> $rowsByCall
     * @param list<array{int, ?string, ?int}> $queryCalls
     * @param list<list<int>> $embeddedIds
     */
    private function createSuccessfulResource(
        array $rowsByCall,
        array &$queryCalls,
        array &$embeddedIds
    ): EmbeddingBacklog&MockObject {
        $resource = $this->createMock(EmbeddingBacklog::class);
        $resource->expects(self::exactly(count($rowsByCall)))
            ->method('getPendingUpsertsForEmbedding')
            ->willReturnCallback(
                static function (
                    int $limit,
                    ?string $updatedAt,
                    ?int $backlogId
                ) use (
                    &$queryCalls,
                    &$rowsByCall
                ): array {
                    $queryCalls[] = [$limit, $updatedAt, $backlogId];

                    return array_shift($rowsByCall) ?? [];
                }
            );
        $resource->expects(self::exactly(2))
            ->method('markEmbeddedByIds')
            ->willReturnCallback(
                static function (array $backlogIds) use (&$embeddedIds): void {
                    $embeddedIds[] = $backlogIds;
                }
            );
        $resource->expects(self::never())
            ->method('markFailedByIds');

        return $resource;
    }

    /**
     * @param list<list<string>> $embedderInputs
     */
    private function createSuccessfulEmbedder(
        array &$embedderInputs
    ): EmbedderClientInterface&MockObject {
        $embedderClient = $this->createMock(EmbedderClientInterface::class);
        $embedderClient->expects(self::exactly(2))
            ->method('embedAsync')
            ->willReturnCallback(
                static function (
                    array $contents
                ) use (&$embedderInputs): PromiseInterface {
                    $embedderInputs[] = $contents;

                    return Create::promiseFor(
                        array_fill(0, count($contents), [0.5])
                    );
                }
            );

        return $embedderClient;
    }

    public function testReturnsZeroWhenThereIsNoPendingWork(): void
    {
        $resource = $this->createMock(EmbeddingBacklog::class);
        $resource->expects(self::once())
            ->method('getPendingUpsertsForEmbedding')
            ->with(100, null, null)
            ->willReturn([]);
        $resource->expects(self::never())
            ->method('markEmbeddedByIds');
        $resource->expects(self::never())
            ->method('markFailedByIds');
        $embedderClient = $this->createMock(EmbedderClientInterface::class);
        $embedderClient->expects(self::never())
            ->method('embedAsync');

        $workflow = $this->createWorkflow(
            $resource,
            $embedderClient,
            $this->createBatchFactory(0),
            $this->createSynchronousPromisePool()
        );

        self::assertSame(0, $workflow->execute());
    }

    /**
     * @return iterable<string, array{mixed, class-string<\Throwable>, string}>
     */
    public static function rejectedReasons(): iterable
    {
        yield 'exception reason is preserved' => [
            new LogicException('request failed'),
            LogicException::class,
            'request failed',
        ];
        yield 'non-exception reason is wrapped' => [
            'request failed',
            RuntimeException::class,
            'The embedding request failed without an exception.',
        ];
    }

    /**
     * @param class-string<\Throwable> $exceptionClass
     */
    #[DataProvider('rejectedReasons')]
    public function testMarksRejectedBatchAsFailedAndStopsLoadingWork(
        mixed $reason,
        string $exceptionClass,
        string $exceptionMessage
    ): void {
        $resource = $this->createMock(EmbeddingBacklog::class);
        $resource->expects(self::once())
            ->method('getPendingUpsertsForEmbedding')
            ->with(100, null, null)
            ->willReturn([
                self::row(7, '2026-07-31 11:00:00', 'failed content'),
            ]);
        $resource->expects(self::never())
            ->method('markEmbeddedByIds');
        $resource->expects(self::once())
            ->method('markFailedByIds')
            ->with([7], 'embedder');
        $embedderClient = $this->createMock(EmbedderClientInterface::class);
        $embedderClient->expects(self::once())
            ->method('embedAsync')
            ->with(['failed content'])
            ->willReturn(Create::promiseFor([[0.5]]));
        $workflow = $this->createWorkflow(
            $resource,
            $embedderClient,
            $this->createBatchFactory(1),
            $this->createRejectedPromisePool($reason)
        );

        $this->expectException($exceptionClass);
        $this->expectExceptionMessage($exceptionMessage);

        $workflow->execute();
    }

    private function createRejectedPromisePool(
        mixed $reason
    ): EmbeddingPromisePool&MockObject {
        $promisePool = $this->createMock(EmbeddingPromisePool::class);
        $promisePool->expects(self::once())
            ->method('run')
            ->with(
                self::isInstanceOf(Generator::class),
                3,
                self::isInstanceOf(Closure::class),
                self::isInstanceOf(Closure::class)
            )
            ->willReturnCallback(
                static function (
                    iterable $promises,
                    int $concurrency,
                    Closure $fulfilled,
                    Closure $rejected
                ) use ($reason): void {
                    foreach ($promises as $batchId => $promise) {
                        self::assertInstanceOf(PromiseInterface::class, $promise);
                        $rejected($reason, $batchId);
                    }
                }
            );

        return $promisePool;
    }

    public function testRejectsAnUnknownCompletedBatch(): void
    {
        $resource = $this->createMock(EmbeddingBacklog::class);
        $resource->expects(self::never())
            ->method('getPendingUpsertsForEmbedding');
        $promisePool = $this->createMock(EmbeddingPromisePool::class);
        $promisePool->expects(self::once())
            ->method('run')
            ->willReturnCallback(
                static function (
                    iterable $promises,
                    int $concurrency,
                    Closure $fulfilled,
                    Closure $rejected
                ): void {
                    $fulfilled([], 99);
                }
            );

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'The completed embedding batch is unknown.'
        );

        $this->createWorkflow(
            $resource,
            self::createStub(EmbedderClientInterface::class),
            self::createStub(EmbeddingBatchFactory::class),
            $promisePool
        )->execute();
    }

    private function createWorkflow(
        EmbeddingBacklog $resource,
        EmbedderClientInterface $embedderClient,
        EmbeddingBatchFactory $embeddingBatchFactory,
        EmbeddingPromisePool $embeddingPromisePool
    ): VectorEmbedding {
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())
            ->method('getResourceModel')
            ->willReturn($resource);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->expects(self::once())
            ->method('create')
            ->willReturn($collection);

        return new VectorEmbedding(
            $collectionFactory,
            $embedderClient,
            new EmbeddingInputMapper(),
            $embeddingBatchFactory,
            $embeddingPromisePool
        );
    }

    private function createBatchFactory(
        int $expectedCalls
    ): EmbeddingBatchFactory&MockObject {
        $factory = $this->createMock(EmbeddingBatchFactory::class);
        $factory->expects(self::exactly($expectedCalls))
            ->method('create')
            ->willReturnCallback(
                static function (array $arguments): EmbeddingBatch {
                    $inputs = $arguments['inputs'] ?? null;
                    self::assertIsArray($inputs);
                    /** @var list<\DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingInput> $inputs */

                    return new EmbeddingBatch($inputs);
                }
            );

        return $factory;
    }

    private function createSynchronousPromisePool(): EmbeddingPromisePool&MockObject
    {
        $promisePool = $this->createMock(EmbeddingPromisePool::class);
        $promisePool->expects(self::once())
            ->method('run')
            ->with(
                self::isInstanceOf(Generator::class),
                3,
                self::isInstanceOf(Closure::class),
                self::isInstanceOf(Closure::class)
            )
            ->willReturnCallback(
                static function (
                    iterable $promises,
                    int $concurrency,
                    Closure $fulfilled,
                    Closure $rejected
                ): void {
                    foreach ($promises as $batchId => $promise) {
                        self::assertInstanceOf(PromiseInterface::class, $promise);
                        $fulfilled($promise->wait(), $batchId);
                    }
                }
            );

        return $promisePool;
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(
        int $backlogId,
        string $updatedAt,
        string $content
    ): array {
        return [
            EmbeddingBacklogInterface::BACKLOG_ID => (string) $backlogId,
            EmbeddingBacklogInterface::UPDATED_AT => $updatedAt,
            EmbeddingBacklogInterface::CHUNK_ID => (string) ($backlogId + 100),
            DocumentInterface::SOURCE_ENTITY_TYPE => 'product',
            DocumentInterface::SOURCE_ENTITY_ID => '42',
            DocumentInterface::STORE_ID => '1',
            DocumentInterface::SOURCE_CODE => 'description',
            ChunkInterface::CHUNK_INDEX => '0',
            ChunkInterface::CONTENT => $content,
            ChunkInterface::CONTENT_HASH => 'hash-' . $backlogId,
        ];
    }
}
