<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Workflow;

use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as EmbeddingBacklogResource;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Collection;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use DavidBel\AiSearch\Workflow\ChunkDeletion;
use DavidBel\AiSearch\Workflow\ChunkProcessing\CacheClean;
use DavidBel\AiSearch\Workflow\ChunkProcessing\ProcessingResultHandler;
use DavidBel\AiSearch\Workflow\ChunkProcessing\ProcessingResultHandlerFactory;
use DavidBel\AiSearch\Workflow\ChunkProcessing\ProcessingState;
use DavidBel\AiSearch\Workflow\ChunkProcessing\ProcessingStateFactory;
use DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync;
use DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync\Delete\Batch;
use DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync\Delete\BatchFactory;
use DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync\Delete\ItemMapper;
use DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync\Item;
use DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync\Result;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ChunkDeletionTest extends TestCase
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

    public function testContinuesAfterADeletionFailureAndFinishesSuccessfulBatches(): void
    {
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->expects(self::exactly(3))
            ->method('getItemsForDeletion')
            ->with(1000, 3)
            ->willReturnOnConsecutiveCalls(
                [$this->createRow(10, 2, 42, '2026-08-04 10:00:00')],
                [$this->createRow(11, 3, 43, '2026-08-04 10:01:00')],
                []
            );
        $state = $this->createMock(ProcessingState::class);
        $state->expects(self::exactly(3))->method('isWithinRuntime')->willReturn(true);
        $result = self::createStub(Result::class);
        $vectorSync = $this->createMock(VectorSync::class);
        $vectorSync->expects(self::exactly(2))
            ->method('delete')
            ->willReturnCallback(static function (Batch $batch) use ($result): Result {
                if ($batch->getLastItem()->backlogId === 10) {
                    throw new RuntimeException('OpenSearch unavailable');
                }

                return $result;
            });
        $handler = $this->createMock(ProcessingResultHandler::class);
        $handler->expects(self::once())
            ->method('openSearchFailed')
            ->with([10 => 2]);
        $handler->expects(self::once())->method('completeDeletion')->with($result);
        $handler->expects(self::once())->method('finish')->willReturn(1);
        $cacheClean = $this->createMock(CacheClean::class);
        $cacheClean->expects(self::once())->method('start');

        $workflow = new ChunkDeletion(
            $this->createCollectionFactory($resource),
            new ItemMapper(),
            $this->createBatchFactory(),
            $this->createStateFactory($state),
            $this->createHandlerFactory($state, $handler),
            $vectorSync,
            $cacheClean
        );

        self::assertSame(1, $workflow->execute());
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

    private function createBatchFactory(): BatchFactory
    {
        $factory = $this->createMock(BatchFactory::class);
        $factory->expects(self::exactly(2))->method('create')
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
        int $version,
        int $chunkId,
        string $updatedAt
    ): array {
        return [
            EmbeddingBacklogInterface::BACKLOG_ID => (string) $backlogId,
            EmbeddingBacklogInterface::VERSION => (string) $version,
            EmbeddingBacklogInterface::UPDATED_AT => $updatedAt,
            EmbeddingBacklogInterface::CHUNK_ID => (string) $chunkId,
            EmbeddingBacklogInterface::SOURCE_ENTITY_TYPE => 'product',
            EmbeddingBacklogInterface::SOURCE_ENTITY_ID => '99',
        ];
    }
}
