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

class VectorEmbeddingTest extends TestCase
{
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
