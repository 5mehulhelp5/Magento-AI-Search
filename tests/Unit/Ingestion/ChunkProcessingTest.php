<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Ingestion;

use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Config\SemanticDataProcessingConfig;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as EmbeddingBacklogResource;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Collection;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Maintenance as BacklogMaintenance;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use DavidBel\AiSearch\Ingestion\ChunkProcessing;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\CacheClean;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingBatch;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingBatchFactory;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingItem;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingItemMapper;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingResultHandler;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingResultHandlerFactory;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingState;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingStateFactory;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorEmbedding;
use DavidBel\AiSearch\Log\Logger;
use PHPUnit\Framework\TestCase;

class ChunkProcessingTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(
            CollectionFactory::class,
            ProcessingBatchFactory::class,
            ProcessingStateFactory::class,
            ProcessingResultHandlerFactory::class
        );
    }

    public function testProcessesPendingRowsWithStableCursorAndFinishes(): void
    {
        $resource = $this->createResource($this->createRow());
        $state = $this->createMock(ProcessingState::class);
        $state->expects(self::exactly(2))->method('isWithinRuntime')->willReturn(true);
        $state->expects(self::once())
            ->method('addBatch')
            ->with(0, self::isInstanceOf(ProcessingBatch::class));
        $handler = $this->createMock(ProcessingResultHandler::class);
        $handler->expects(self::once())->method('finish')->willReturn(1);
        $cacheClean = $this->createMock(CacheClean::class);
        $cacheClean->expects(self::once())->method('start');
        $maintenance = $this->createMock(BacklogMaintenance::class);
        $maintenance->expects(self::once())
            ->method('markMissingChunkUpsertsOutdated')
            ->with(7);

        $workflow = new ChunkProcessing(
            $this->createCollectionFactory($resource),
            new ProcessingItemMapper(),
            $this->createBatchFactory(),
            $this->createStateFactory($state),
            $this->createHandlerFactory($state, $handler),
            $this->createVectorEmbedding($handler),
            $cacheClean,
            $this->createSemanticDataProcessingConfig(),
            $maintenance,
            self::createStub(Logger::class)
        );

        self::assertSame(1, $workflow->execute(7));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function createResource(array $row): EmbeddingBacklogResource
    {
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->expects(self::exactly(2))
            ->method('getPendingUpsertsForEmbedding')
            ->willReturnCallback(static function (
                int $indexVersion,
                int $limit,
                ?string $updatedAt,
                ?int $backlogId
            ) use ($row): array {
                self::assertSame(7, $indexVersion);
                self::assertSame(100, $limit);

                if ($updatedAt === null) {
                    self::assertNull($backlogId);

                    return [$row];
                }

                self::assertSame('2026-08-04 10:00:00', $updatedAt);
                self::assertSame(10, $backlogId);

                return [];
            });

        return $resource;
    }

    private function createVectorEmbedding(
        ProcessingResultHandler $handler
    ): VectorEmbedding {
        $vectorEmbedding = $this->createMock(VectorEmbedding::class);
        $vectorEmbedding->expects(self::once())
            ->method('execute')
            ->willReturnCallback(static function (
                iterable $batches,
                int $concurrency,
                callable $completed,
                callable $failed
            ) use ($handler): void {
                self::assertSame(3, $concurrency);
                self::assertSame([$handler, 'completed'], $completed);
                self::assertSame([$handler, 'failed'], $failed);
                $batchList = iterator_to_array($batches);
                self::assertSame([0], array_keys($batchList));
                self::assertInstanceOf(ProcessingBatch::class, $batchList[0]);
                self::assertSame(['text'], $batchList[0]->getContents());
            });

        return $vectorEmbedding;
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

    private function createSemanticDataProcessingConfig(): SemanticDataProcessingConfig
    {
        $config = self::createStub(SemanticDataProcessingConfig::class);
        $config->method('getVectorEmbeddingBatchSize')->willReturn(100);
        $config->method('getVectorEmbeddingConcurrentRequests')->willReturn(3);
        $config->method('getVectorEmbeddingMaximumRuntimeSeconds')->willReturn(600);

        return $config;
    }

    private function createBatchFactory(): ProcessingBatchFactory
    {
        $factory = $this->createMock(ProcessingBatchFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->willReturnCallback(static function (array $arguments): ProcessingBatch {
                $items = $arguments['items'] ?? null;
                self::assertIsArray($items);
                self::assertContainsOnlyInstancesOf(ProcessingItem::class, $items);
                /** @var list<ProcessingItem> $items */

                return new ProcessingBatch($items);
            });

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
        $factory->expects(self::once())
            ->method('create')
            ->with(['processingState' => $state])
            ->willReturn($handler);

        return $factory;
    }

    /**
     * @return array<string, mixed>
     */
    private function createRow(): array
    {
        return [
            EmbeddingBacklogInterface::BACKLOG_ID => '10',
            EmbeddingBacklogInterface::BACKLOG_VERSION => '2',
            EmbeddingBacklogInterface::INDEX_VERSION => '7',
            EmbeddingBacklogInterface::UPDATED_AT => '2026-08-04 10:00:00',
            EmbeddingBacklogInterface::CHUNK_ID => '42',
            DocumentInterface::SOURCE_ENTITY_TYPE => 'product',
            DocumentInterface::SOURCE_ENTITY_ID => '99',
            DocumentInterface::STORE_ID => '1',
            DocumentInterface::SOURCE_CODE => 'catalog_product_99',
            ChunkInterface::CHUNK_INDEX => '0',
            ChunkInterface::CONTENT => 'text',
            ChunkInterface::CONTENT_HASH => 'hash',
        ];
    }
}
