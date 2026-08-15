<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Ingestion;

use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Config\DataProcessingConfig;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as EmbeddingBacklogResource;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Collection;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use DavidBel\AiSearch\Ingestion\ChunkDelete;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\CacheClean;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingResultHandler;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingResultHandlerFactory;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingState;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingStateFactory;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Delete\Batch;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Delete\BatchFactory;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Delete\ItemMapper;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Item;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ChunkDeleteTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(
            CollectionFactory::class,
            BatchFactory::class,
            ProcessingStateFactory::class,
            ProcessingResultHandlerFactory::class
        );
    }

    public function testStopsAfterADeleteFailureAndFinishesProcessing(): void
    {
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->expects(self::once())
            ->method('getItemsToDelete')
            ->with(7, 1000, 3, null, null)
            ->willReturn([$this->createRow(10, 2, 42, '2026-08-04 10:00:00')]);
        $state = $this->createMock(ProcessingState::class);
        $state->expects(self::once())->method('isWithinRuntime')->willReturn(true);
        $vectorSync = $this->createMock(VectorSync::class);
        $vectorSync->expects(self::once())
            ->method('delete')
            ->willThrowException(new RuntimeException('OpenSearch unavailable'));
        $handler = $this->createMock(ProcessingResultHandler::class);
        $handler->expects(self::once())
            ->method('openSearchFailed')
            ->with([10 => 2]);
        $handler->expects(self::never())->method('completeDelete');
        $handler->expects(self::once())->method('finish')->willReturn(0);
        $cacheClean = $this->createMock(CacheClean::class);
        $cacheClean->expects(self::once())->method('start');

        $workflow = new ChunkDelete(
            $this->createCollectionFactory($resource),
            new ItemMapper(),
            $this->createBatchFactory(),
            $this->createStateFactory($state),
            $this->createHandlerFactory($state, $handler),
            $vectorSync,
            $cacheClean,
            $this->createDataProcessingConfig()
        );

        self::assertSame(0, $workflow->execute(7));
    }

    private function createCollectionFactory(
        EmbeddingBacklogResource $resource
    ): CollectionFactory {
        $collection = self::createStub(Collection::class);
        $collection->method('getResourceModel')->willReturn($resource);
        $factory = self::createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return $factory;
    }

    private function createDataProcessingConfig(): DataProcessingConfig
    {
        $config = self::createStub(DataProcessingConfig::class);
        $config->method('getVectorDeleteBatchSize')->willReturn(1000);
        $config->method('getVectorDeleteUpsertAttemptThreshold')->willReturn(3);
        $config->method('getVectorDeleteMaximumRuntimeSeconds')->willReturn(600);

        return $config;
    }

    private function createBatchFactory(): BatchFactory
    {
        $factory = $this->createMock(BatchFactory::class);
        $factory->expects(self::once())->method('create')
            ->willReturnCallback(
                static function (array $arguments): Batch {
                    $items = $arguments['items'] ?? null;
                    self::assertIsArray($items);
                    self::assertContainsOnlyInstancesOf(Item::class, $items);
                    /** @var list<Item> $items */

                    return new Batch($items);
                }
            );

        return $factory;
    }

    private function createStateFactory(ProcessingState $state): ProcessingStateFactory
    {
        $factory = $this->createMock(ProcessingStateFactory::class);
        $factory->expects(self::once())->method('create')->willReturn($state);

        return $factory;
    }

    private function createHandlerFactory(
        ProcessingState $state,
        ProcessingResultHandler $handler
    ): ProcessingResultHandlerFactory {
        $factory = $this->createMock(ProcessingResultHandlerFactory::class);
        $factory->expects(self::once())->method('create')
            ->with(['processingState' => $state])
            ->willReturn($handler);

        return $factory;
    }

    /**
     * @return array<string, mixed>
     */
    private function createRow(
        int $backlogId,
        int $backlogVersion,
        int $chunkId,
        string $updatedAt
    ): array {
        return [
            EmbeddingBacklogInterface::BACKLOG_ID => (string) $backlogId,
            EmbeddingBacklogInterface::BACKLOG_VERSION => (string) $backlogVersion,
            EmbeddingBacklogInterface::INDEX_VERSION => '7',
            EmbeddingBacklogInterface::UPDATED_AT => $updatedAt,
            EmbeddingBacklogInterface::CHUNK_ID => (string) $chunkId,
            EmbeddingBacklogInterface::SOURCE_ENTITY_TYPE => 'product',
            EmbeddingBacklogInterface::SOURCE_ENTITY_ID => '99',
        ];
    }
}
