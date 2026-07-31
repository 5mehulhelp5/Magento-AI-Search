<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Workflow;

use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Api\EmbedderClientInterface;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use DavidBel\AiSearch\Workflow\VectorEmbedding;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingBatch;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingBatchFactory;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingExecution;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingExecutionFactory;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingInputMapper;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingPromisePool;
use Generator;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class VectorEmbeddingTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(
            EmbeddingBatchFactory::class,
            EmbeddingExecutionFactory::class
        );
    }

    public function testProcessesRowsInCursorBasedBatchesThroughExecution(): void
    {
        $firstRows = [
            self::row(1, '2026-07-31 10:00:00', 'first'),
            self::row(2, '2026-07-31 10:00:00', 'second'),
        ];
        $secondRows = [self::row(3, '2026-07-31 10:01:00', 'third')];
        $queryCalls = [];
        $addedBatches = [];
        $fulfillments = [];
        $execution = $this->createBatchExecution(
            [$firstRows, $secondRows, []],
            $queryCalls,
            $addedBatches,
            $fulfillments
        );
        $embedderInputs = [];
        $workflow = $this->createWorkflow(
            $execution,
            $this->createEmbedder($embedderInputs),
            $this->createBatchFactory(2),
            $this->createSynchronousPool($execution)
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
        self::assertSame([0, 1], array_keys($addedBatches));
        self::assertSame([['first', 'second'], ['third']], $embedderInputs);
        self::assertSame([[[0.5], [0.5]], 0], $fulfillments[0]);
        self::assertSame([[[0.5]], 1], $fulfillments[1]);
    }

    public function testStopsGeneratingWhenExecutionCannotAcceptWork(): void
    {
        $execution = $this->createMock(EmbeddingExecution::class);
        $execution->expects(self::once())
            ->method('start');
        $execution->expects(self::once())
            ->method('canAcceptWork')
            ->with(600_000_000_000)
            ->willReturn(false);
        $execution->expects(self::never())
            ->method('getPendingUpserts');
        $execution->expects(self::never())
            ->method('addBatch');
        $execution->expects(self::once())
            ->method('finish')
            ->willReturn(0);
        $embedder = $this->createMock(EmbedderClientInterface::class);
        $embedder->expects(self::never())
            ->method('embedAsync');

        self::assertSame(
            0,
            $this->createWorkflow(
                $execution,
                $embedder,
                $this->createBatchFactory(0),
                $this->createSynchronousPool($execution)
            )->execute()
        );
    }

    public function testStopsExecutionWhenThePromisePoolThrows(): void
    {
        $failure = new RuntimeException('pool failed');
        $execution = $this->createMock(EmbeddingExecution::class);
        $execution->expects(self::once())
            ->method('start');
        $execution->expects(self::once())
            ->method('stop')
            ->with($failure);
        $execution->expects(self::once())
            ->method('finish')
            ->willReturn(4);
        $promisePool = $this->createMock(EmbeddingPromisePool::class);
        $promisePool->expects(self::once())
            ->method('run')
            ->willThrowException($failure);

        self::assertSame(
            4,
            $this->createWorkflow(
                $execution,
                self::createStub(EmbedderClientInterface::class),
                self::createStub(EmbeddingBatchFactory::class),
                $promisePool
            )->execute()
        );
    }

    /**
     * @param list<list<array<string, mixed>>> $rowsByCall
     * @param list<array{int, ?string, ?int}> $queryCalls
     * @param array<int, EmbeddingBatch> $addedBatches
     * @param list<array{list<list<float>>, int}> $fulfillments
     */
    private function createBatchExecution(
        array $rowsByCall,
        array &$queryCalls,
        array &$addedBatches,
        array &$fulfillments
    ): EmbeddingExecution&MockObject {
        $execution = $this->createMock(EmbeddingExecution::class);
        $execution->expects(self::once())
            ->method('start');
        $execution->expects(self::exactly(count($rowsByCall)))
            ->method('canAcceptWork')
            ->with(600_000_000_000)
            ->willReturn(true);
        $execution->expects(self::exactly(count($rowsByCall)))
            ->method('getPendingUpserts')
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
        $this->configureBatchCallbacks(
            $execution,
            $addedBatches,
            $fulfillments
        );
        $execution->expects(self::never())
            ->method('rejected');
        $execution->expects(self::once())
            ->method('finish')
            ->willReturn(3);

        return $execution;
    }

    /**
     * @param array<int, EmbeddingBatch> $addedBatches
     * @param list<array{list<list<float>>, int}> $fulfillments
     */
    private function configureBatchCallbacks(
        EmbeddingExecution&MockObject $execution,
        array &$addedBatches,
        array &$fulfillments
    ): void {
        $execution->expects(self::exactly(2))
            ->method('addBatch')
            ->willReturnCallback(
                static function (int $batchId, EmbeddingBatch $batch) use (&$addedBatches): void {
                    $addedBatches[$batchId] = $batch;
                }
            );
        $execution->expects(self::exactly(2))
            ->method('fulfilled')
            ->willReturnCallback(
                static function (array $vectors, int $batchId) use (&$fulfillments): void {
                    $fulfillments[] = [$vectors, $batchId];
                }
            );
    }

    /**
     * @param list<list<string>> $embedderInputs
     */
    private function createEmbedder(
        array &$embedderInputs
    ): EmbedderClientInterface&MockObject {
        $embedder = $this->createMock(EmbedderClientInterface::class);
        $embedder->expects(self::exactly(2))
            ->method('embedAsync')
            ->willReturnCallback(
                static function (array $contents) use (&$embedderInputs): PromiseInterface {
                    $embedderInputs[] = $contents;

                    return Create::promiseFor(
                        array_fill(0, count($contents), [0.5])
                    );
                }
            );

        return $embedder;
    }

    private function createBatchFactory(int $expectedCalls): EmbeddingBatchFactory&MockObject
    {
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

    private function createSynchronousPool(
        EmbeddingExecution $execution
    ): EmbeddingPromisePool&MockObject {
        $pool = $this->createMock(EmbeddingPromisePool::class);
        $pool->expects(self::once())
            ->method('run')
            ->with(
                self::isInstanceOf(Generator::class),
                3,
                [$execution, 'fulfilled'],
                [$execution, 'rejected']
            )
            ->willReturnCallback(
                static function (
                    iterable $promises,
                    int $concurrency,
                    callable $fulfilled,
                    callable $rejected
                ): void {
                    foreach ($promises as $batchId => $promise) {
                        self::assertInstanceOf(PromiseInterface::class, $promise);
                        $fulfilled($promise->wait(), $batchId);
                    }
                }
            );

        return $pool;
    }

    private function createWorkflow(
        EmbeddingExecution $execution,
        EmbedderClientInterface $embedder,
        EmbeddingBatchFactory $batchFactory,
        EmbeddingPromisePool $promisePool
    ): VectorEmbedding {
        $executionFactory = $this->createMock(EmbeddingExecutionFactory::class);
        $executionFactory->expects(self::once())
            ->method('create')
            ->willReturn($execution);

        return new VectorEmbedding(
            $embedder,
            new EmbeddingInputMapper(),
            $batchFactory,
            $promisePool,
            $executionFactory
        );
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
