<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Ingestion;

use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as EmbeddingBacklogResource;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Collection;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use DavidBel\AiSearch\Ingestion\DocumentProcessing;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdater;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdateResult;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\ScopedSource;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DocumentProcessingTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(CollectionFactory::class);
    }

    public function testPerformsAFullUpdateAndQueuesChunkChanges(): void
    {
        $sources = [new ScopedSource(2, 'Description')];
        $sourceProvider = $this->createMock(SourceProvider::class);
        $batch = 0;
        $sourceProvider->expects(self::exactly(2))
            ->method('getProductIdsAfter')
            ->willReturnCallback(
                static function (int $lastProductId, int $limit) use (&$batch): array {
                    self::assertSame(DocumentProcessing::BATCH_SIZE, $limit);

                    if ($batch === 0) {
                        self::assertSame(0, $lastProductId);
                        ++$batch;

                        return [30];
                    }

                    self::assertSame(30, $lastProductId);

                    return [];
                }
            );
        $sourceProvider->expects(self::once())
            ->method('getByProductIds')
            ->with([30])
            ->willReturn([30 => $sources]);
        $documentUpdater = $this->createMock(DocumentUpdater::class);
        $documentUpdater->expects(self::once())
            ->method('fullUpdate')
            ->with('product', 30, 'description', $sources)
            ->willReturn(new DocumentUpdateResult([101], [102]));
        $connection = $this->createTransactionConnection();
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->method('getConnection')
            ->willReturn($connection);
        $resource->expects(self::once())
            ->method('saveByChunkId')
            ->with(101);
        $resource->expects(self::once())
            ->method('deleteByChunkId')
            ->with(102);

        (new DocumentProcessing(
            $sourceProvider,
            $documentUpdater,
            $this->createCollectionFactory($resource)
        ))->fullUpdate();
    }

    public function testDeltaUpdatesSourcesAndMissingProductsInTransactions(): void
    {
        $firstProductSources = [new ScopedSource(1, 'First description')];
        $sourceProvider = $this->createMock(SourceProvider::class);
        $sourceProvider->expects(self::once())
            ->method('getByProductIds')
            ->with([10, 20])
            ->willReturn([10 => $firstProductSources]);
        $documentUpdater = $this->createMock(DocumentUpdater::class);
        $updatedProducts = [];
        $documentUpdater->expects(self::exactly(2))
            ->method('deltaUpdate')
            ->willReturnCallback(
                static function (
                    string $sourceEntityType,
                    int $sourceEntityId,
                    string $sourceCode,
                    array $sources
                ) use (&$updatedProducts): DocumentUpdateResult {
                    self::assertSame('product', $sourceEntityType);
                    self::assertSame('description', $sourceCode);
                    $updatedProducts[$sourceEntityId] = $sources;

                    return $sourceEntityId === 10
                        ? new DocumentUpdateResult([201], [])
                        : new DocumentUpdateResult([], [202]);
                }
            );
        $connection = $this->createTransactionConnection(2);
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->method('getConnection')
            ->willReturn($connection);
        $resource->expects(self::once())
            ->method('saveByChunkId')
            ->with(201);
        $resource->expects(self::once())
            ->method('deleteByChunkId')
            ->with(202);

        (new DocumentProcessing(
            $sourceProvider,
            $documentUpdater,
            $this->createCollectionFactory($resource)
        ))->deltaUpdate([10, 20]);

        self::assertSame($firstProductSources, $updatedProducts[10]);
        self::assertSame([], $updatedProducts[20]);
    }

    public function testCommitsAnUpdateWithoutBacklogChanges(): void
    {
        $sourceProvider = self::createStub(SourceProvider::class);
        $sourceProvider->method('getByProductIds')
            ->willReturn([]);
        $documentUpdater = $this->createMock(DocumentUpdater::class);
        $documentUpdater->expects(self::once())
            ->method('deltaUpdate')
            ->willReturn(new DocumentUpdateResult([], []));
        $connection = $this->createTransactionConnection();
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->method('getConnection')
            ->willReturn($connection);
        $resource->expects(self::never())
            ->method('saveByChunkId');
        $resource->expects(self::never())
            ->method('deleteByChunkId');

        (new DocumentProcessing(
            $sourceProvider,
            $documentUpdater,
            $this->createCollectionFactory($resource)
        ))->deltaUpdate([10]);
    }

    public function testRollsBackAFailedProductUpdate(): void
    {
        $sourceProvider = self::createStub(SourceProvider::class);
        $sourceProvider->method('getByProductIds')
            ->willReturn([]);
        $failure = new RuntimeException('update failed');
        $documentUpdater = $this->createMock(DocumentUpdater::class);
        $documentUpdater->expects(self::once())
            ->method('deltaUpdate')
            ->willThrowException($failure);
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())
            ->method('beginTransaction');
        $connection->expects(self::never())
            ->method('commit');
        $connection->expects(self::once())
            ->method('rollBack');
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->method('getConnection')
            ->willReturn($connection);
        $resource->expects(self::never())
            ->method('saveByChunkId');
        $resource->expects(self::never())
            ->method('deleteByChunkId');

        $this->expectExceptionObject($failure);

        (new DocumentProcessing(
            $sourceProvider,
            $documentUpdater,
            $this->createCollectionFactory($resource)
        ))->deltaUpdate([10]);
    }

    public function testDoesNotLoadAnEmptyDeltaUpdate(): void
    {
        $sourceProvider = $this->createMock(SourceProvider::class);
        $sourceProvider->expects(self::never())
            ->method('getByProductIds');
        $documentUpdater = self::createStub(DocumentUpdater::class);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->expects(self::never())
            ->method('create');

        (new DocumentProcessing(
            $sourceProvider,
            $documentUpdater,
            $collectionFactory
        ))->deltaUpdate([]);
    }

    public function testLoadsDeltaUpdatesInBoundedBatches(): void
    {
        $productIds = range(1, DocumentProcessing::BATCH_SIZE + 1);
        $loadedBatches = [];
        $sourceProvider = $this->createMock(SourceProvider::class);
        $sourceProvider->expects(self::exactly(2))
            ->method('getByProductIds')
            ->willReturnCallback(
                static function (array $productIdBatch) use (&$loadedBatches): array {
                    $loadedBatches[] = $productIdBatch;

                    return [];
                }
            );
        $documentUpdater = self::createStub(DocumentUpdater::class);
        $documentUpdater->method('deltaUpdate')
            ->willReturn(new DocumentUpdateResult([], []));
        $connection = self::createStub(AdapterInterface::class);
        $resource = self::createStub(EmbeddingBacklogResource::class);
        $resource->method('getConnection')
            ->willReturn($connection);

        (new DocumentProcessing(
            $sourceProvider,
            $documentUpdater,
            $this->createCollectionFactory($resource, 2)
        ))->deltaUpdate($productIds);

        self::assertSame(
            [
                range(1, DocumentProcessing::BATCH_SIZE),
                [DocumentProcessing::BATCH_SIZE + 1],
            ],
            $loadedBatches
        );
    }

    private function createTransactionConnection(int $transactions = 1): AdapterInterface
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::exactly($transactions))
            ->method('beginTransaction');
        $connection->expects(self::exactly($transactions))
            ->method('commit');
        $connection->expects(self::never())
            ->method('rollBack');

        return $connection;
    }

    private function createCollectionFactory(
        EmbeddingBacklogResource $resource,
        int $creations = 1
    ): CollectionFactory {
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::exactly($creations))
            ->method('getResourceModel')
            ->willReturn($resource);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->expects(self::exactly($creations))
            ->method('create')
            ->willReturn($collection);

        return $collectionFactory;
    }
}
