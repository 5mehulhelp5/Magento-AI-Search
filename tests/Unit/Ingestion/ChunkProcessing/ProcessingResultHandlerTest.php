<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Ingestion\ChunkProcessing;

use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as EmbeddingBacklogResource;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Collection;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\IndexVersion as BacklogIndexVersion;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\CacheClean;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingBatch;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingResultHandler;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingState;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Item;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Result;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ProcessingResultHandlerTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(CollectionFactory::class);
    }

    public function testCompletesAnEmbeddingBatchAndRecordsOnlySuccessfulRows(): void
    {
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->expects(self::once())
            ->method('markFailedByVersions')
            ->with([20 => 3], 'opensearch');
        $batch = self::createStub(ProcessingBatch::class);
        $result = new Result(
            [$this->createItem(10, 2, 99)],
            [$this->createItem(20, 3, 100)]
        );
        $vectorSync = $this->createMock(VectorSync::class);
        $vectorSync->expects(self::once())
            ->method('upsert')
            ->with($batch, [[0.1, 0.2]])
            ->willReturn($result);
        $cacheClean = $this->createMock(CacheClean::class);
        $cacheClean->expects(self::once())
            ->method('register')
            ->with('product', [99]);
        $cacheClean->expects(self::once())->method('registerSearchResults');
        $state = $this->createMock(ProcessingState::class);
        $state->expects(self::once())->method('getBatch')->with(7)->willReturn($batch);
        $state->expects(self::once())->method('recordSuccesses')->with([10 => 2]);
        $state->expects(self::once())->method('removeBatch')->with(7);
        $backlogIndexVersion = $this->createMock(BacklogIndexVersion::class);
        $backlogIndexVersion->expects(self::once())
            ->method('markFullReindexItemsIndexed')
            ->with([10 => 7]);

        $this->createHandler($resource, $vectorSync, $cacheClean, $state, $backlogIndexVersion)
            ->completed([[0.1, 0.2]], 7);
    }

    public function testDoesNotRegisterSearchResultCacheWithoutSuccessfulRows(): void
    {
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->expects(self::once())
            ->method('markFailedByVersions')
            ->with([20 => 3], 'opensearch');
        $cacheClean = $this->createMock(CacheClean::class);
        $cacheClean->expects(self::never())->method('register');
        $cacheClean->expects(self::never())->method('registerSearchResults');
        $state = $this->createMock(ProcessingState::class);
        $state->expects(self::once())->method('recordSuccesses')->with([]);
        $backlogIndexVersion = $this->createMock(BacklogIndexVersion::class);
        $backlogIndexVersion->expects(self::once())
            ->method('markFullReindexItemsIndexed')
            ->with([]);

        $this->createHandler(
            $resource,
            self::createStub(VectorSync::class),
            $cacheClean,
            $state,
            $backlogIndexVersion
        )->completeDelete(new Result([], [$this->createItem(20, 3, 100)]));
    }

    public function testMarksTheBatchFailedWhenOpenSearchThrows(): void
    {
        $batch = $this->createMock(ProcessingBatch::class);
        $batch->expects(self::once())
            ->method('getBacklogVersions')
            ->willReturn([10 => 2]);
        $vectorSync = $this->createMock(VectorSync::class);
        $vectorSync->expects(self::once())
            ->method('upsert')
            ->willThrowException(new RuntimeException('OpenSearch unavailable'));
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->expects(self::once())
            ->method('markFailedByVersions')
            ->with([10 => 2], 'opensearch');
        $state = $this->createMock(ProcessingState::class);
        $state->method('getBatch')->with(5)->willReturn($batch);
        $state->expects(self::once())->method('removeBatch')->with(5);

        $this->createHandler(
            $resource,
            $vectorSync,
            self::createStub(CacheClean::class),
            $state
        )->completed([[0.1]], 5);
    }

    public function testMarksAnEmbeddingPromiseFailureAndRemovesItsBatch(): void
    {
        $batch = $this->createMock(ProcessingBatch::class);
        $batch->expects(self::once())
            ->method('getBacklogVersions')
            ->willReturn([30 => 4]);
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->expects(self::once())
            ->method('markFailedByVersions')
            ->with([30 => 4], 'embedder');
        $state = $this->createMock(ProcessingState::class);
        $state->method('getBatch')->with(9)->willReturn($batch);
        $state->expects(self::once())->method('removeBatch')->with(9);

        $this->createHandler(
            $resource,
            self::createStub(VectorSync::class),
            self::createStub(CacheClean::class),
            $state
        )->failed(new RuntimeException('Embedding failed'), 9);
    }

    public function testFinishesByFlushingCacheAndCompletingCurrentVersions(): void
    {
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->expects(self::once())
            ->method('markDoneByVersions')
            ->with([10 => 2, 30 => 4]);
        $cacheClean = $this->createMock(CacheClean::class);
        $cacheClean->expects(self::once())->method('flush');
        $state = self::createStub(ProcessingState::class);
        $state->method('getSuccessfulBacklogVersions')->willReturn([10 => 2, 30 => 4]);
        $state->method('getProcessedCount')->willReturn(2);

        self::assertSame(
            2,
            $this->createHandler(
                $resource,
                self::createStub(VectorSync::class),
                $cacheClean,
                $state
            )->finish()
        );
    }

    public function testMarksSuccessfulRowsFailedWhenCacheFlushFails(): void
    {
        $failure = new RuntimeException('Cache flush failed');
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->expects(self::once())
            ->method('markFailedByVersions')
            ->with([10 => 2], 'cache');
        $resource->expects(self::never())->method('markDoneByVersions');
        $cacheClean = self::createStub(CacheClean::class);
        $cacheClean->method('flush')->willThrowException($failure);
        $state = self::createStub(ProcessingState::class);
        $state->method('getSuccessfulBacklogVersions')->willReturn([10 => 2]);

        $this->expectExceptionObject($failure);

        $this->createHandler(
            $resource,
            self::createStub(VectorSync::class),
            $cacheClean,
            $state
        )->finish();
    }

    private function createHandler(
        EmbeddingBacklogResource $resource,
        VectorSync $vectorSync,
        CacheClean $cacheClean,
        ProcessingState $state,
        ?BacklogIndexVersion $backlogIndexVersion = null
    ): ProcessingResultHandler {
        $collection = self::createStub(Collection::class);
        $collection->method('getResourceModel')->willReturn($resource);
        $collectionFactory = self::createStub(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        return new ProcessingResultHandler(
            $collectionFactory,
            $vectorSync,
            $cacheClean,
            $state,
            $backlogIndexVersion ?? self::createStub(BacklogIndexVersion::class)
        );
    }

    private function createItem(int $backlogId, int $backlogVersion, int $entityId): Item
    {
        return new Item(
            $backlogId,
            $backlogVersion,
            '2026-08-04 10:00:00',
            $backlogId + 100,
            'product',
            $entityId,
            7
        );
    }
}
