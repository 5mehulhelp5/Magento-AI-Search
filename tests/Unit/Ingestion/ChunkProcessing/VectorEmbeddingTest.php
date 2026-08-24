<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Ingestion\ChunkProcessing;

use DavidBel\AiSearch\Client\Embedding\Base\EmbedderClientInterface;
use DavidBel\AiSearch\Client\Embedding\Base\EmbedderClientPool;
use DavidBel\AiSearch\Client\Embedding\Base\EmbeddingInput;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingBatch;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingItem;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorEmbedding;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorEmbedding\PromisePool;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorEmbedding\RequestBatch;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorEmbedding\RequestBatchFactory;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use GuzzleHttp\Promise\FulfilledPromise;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

class VectorEmbeddingTest extends TestCase
{
    private ?Throwable $recordedFailure = null;

    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(RequestBatchFactory::class);
    }

    public function testEmbedsUniqueInputsAndCompletesWithExpandedVectors(): void
    {
        $batch = new ProcessingBatch([
            $this->createItem(10),
            $this->createItem(20),
        ]);
        $requestBatch = new RequestBatch($batch);
        $factory = $this->createMock(RequestBatchFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->with(['processingBatch' => $batch])
            ->willReturn($requestBatch);
        $embedderClient = $this->createMock(EmbedderClientInterface::class);
        $embedderClient->expects(self::once())
            ->method('embedDocumentsAsync')
            ->with([new EmbeddingInput(null, 'same text')])
            ->willReturn(new FulfilledPromise([[0.1, 0.2]]));
        $embedderClientPool = $this->createMock(EmbedderClientPool::class);
        $embedderClientPool->expects(self::once())
            ->method('getClient')
            ->willReturn($embedderClient);
        $completed = false;

        (new VectorEmbedding(
            $embedderClientPool,
            new PromisePool(),
            $factory
        ))->execute(
            [7 => $batch],
            1,
            static function (array $vectors, int $batchId) use (&$completed): void {
                self::assertSame([[0.1, 0.2], [0.1, 0.2]], $vectors);
                self::assertSame(7, $batchId);
                $completed = true;
            },
            static function (): void {
                self::fail('The embedding request was not expected to fail.');
            }
        );

        self::assertTrue($completed);
    }

    public function testConvertsRequestConstructionFailureToRejectedPromise(): void
    {
        $batch = new ProcessingBatch([$this->createItem(10)]);
        $factory = self::createStub(RequestBatchFactory::class);
        $factory->method('create')->willThrowException(new RuntimeException('factory failed'));
        $clientPool = self::createStub(EmbedderClientPool::class);
        $clientPool->method('getClient')->willReturn(self::createStub(EmbedderClientInterface::class));

        (new VectorEmbedding($clientPool, new PromisePool(), $factory))->execute(
            [3 => $batch],
            1,
            [$this, 'recordUnexpectedEmbeddingCompletion'],
            [$this, 'recordEmbeddingFailure']
        );

        self::assertInstanceOf(RuntimeException::class, $this->recordedFailure);
        self::assertSame('factory failed', $this->recordedFailure->getMessage());
    }

    public function recordUnexpectedEmbeddingCompletion(): void
    {
        self::fail('The embedding request was expected to fail.');
    }

    public function recordEmbeddingFailure(Throwable $throwable, int $batchId): void
    {
        self::assertSame(3, $batchId);
        $this->recordedFailure = $throwable;
    }

    private function createItem(int $backlogId): ProcessingItem
    {
        return new ProcessingItem(
            $backlogId,
            1,
            '2026-08-19 10:00:00',
            $backlogId + 100,
            'product',
            $backlogId,
            1,
            'catalog_product_' . $backlogId,
            0,
            'same text',
            'hash-' . $backlogId
        );
    }
}
