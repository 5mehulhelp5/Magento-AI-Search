<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Ingestion;

use DavidBel\AiSearch\Config\DataProcessingConfig;
use DavidBel\AiSearch\Ingestion\DocumentProcessing;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentSource;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentSource\StoreScopedSource;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdater;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdater\Result;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider;
use DavidBel\AiSearch\Model\EmbeddingBacklog\FullReindexStatus;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as EmbeddingBacklogResource;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Collection;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DocumentProcessingTest extends TestCase
{
    private const int BATCH_SIZE = 100;
    private const int INDEX_VERSION = 7;

    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(CollectionFactory::class);
    }

    public function testPerformsAFullUpdateAndQueuesChunkChanges(): void
    {
        $source = $this->createSource(2, 'Description');
        $sourceProvider = $this->createMock(SourceProvider::class);
        $sourceProvider->expects(self::exactly(2))
            ->method('getProductIdsAfter')
            ->willReturnCallback(static function (int $lastProductId, int $limit): array {
                self::assertSame(self::BATCH_SIZE, $limit);

                return $lastProductId === 0 ? [30] : [];
            });
        $sourceProvider->expects(self::once())
            ->method('getSourcesByProductIds')
            ->with([30])
            ->willReturn([30 => [$source]]);
        $documentUpdater = $this->createMock(DocumentUpdater::class);
        $documentUpdater->expects(self::once())
            ->method('fullUpdate')
            ->with('product', 30, $source)
            ->willReturn(new Result([101], [102]));
        $resource = $this->createBacklogResource($this->createTransactionConnection());
        $resource->expects(self::once())
            ->method('saveByChunkId')
            ->with(101, 'product', 30, self::INDEX_VERSION, FullReindexStatus::Pending);
        $resource->expects(self::once())
            ->method('deleteByChunkId')
            ->with(102, 'product', 30, self::INDEX_VERSION, FullReindexStatus::Pending);

        $this->createProcessing(
            $sourceProvider,
            $documentUpdater,
            $this->createCollectionFactory($resource)
        )->fullUpdate(self::INDEX_VERSION);
    }

    public function testDeltaUpdatesAffectedProductsInTransactions(): void
    {
        $firstSource = $this->createSource(1, 'First description');
        $secondSource = $this->createSource(1, 'Second description');
        $sourceProvider = $this->createMock(SourceProvider::class);
        $sourceProvider->expects(self::once())
            ->method('getAffectedProductIds')
            ->with([10, 20])
            ->willReturn([10, 20]);
        $sourceProvider->expects(self::once())
            ->method('getSourcesByProductIds')
            ->with([10, 20])
            ->willReturn([10 => [$firstSource], 20 => [$secondSource]]);
        $documentUpdater = $this->createMock(DocumentUpdater::class);
        $documentUpdater->expects(self::exactly(2))
            ->method('deltaUpdate')
            ->willReturnCallback(
                static function (
                    string $entityType,
                    int $entityId,
                    DocumentSource $source
                ) use (
                    $firstSource,
                    $secondSource
                ): Result {
                    self::assertSame('product', $entityType);
                    self::assertSame($entityId === 10 ? $firstSource : $secondSource, $source);

                    return $entityId === 10 ? new Result([201], []) : new Result([], [202]);
                }
            );
        $resource = $this->createBacklogResource($this->createTransactionConnection(2));
        $resource->expects(self::once())
            ->method('saveByChunkId')
            ->with(201, 'product', 10, self::INDEX_VERSION, FullReindexStatus::Delta);
        $resource->expects(self::once())
            ->method('deleteByChunkId')
            ->with(202, 'product', 20, self::INDEX_VERSION, FullReindexStatus::Delta);

        $this->createProcessing(
            $sourceProvider,
            $documentUpdater,
            $this->createCollectionFactory($resource)
        )->deltaUpdate([10, 20], self::INDEX_VERSION);
    }

    public function testCommitsAnUpdateWithoutBacklogChanges(): void
    {
        $source = $this->createSource(1, 'Description');
        $sourceProvider = $this->createDeltaSourceProvider([10], [10 => [$source]]);
        $documentUpdater = $this->createMock(DocumentUpdater::class);
        $documentUpdater->expects(self::once())
            ->method('deltaUpdate')
            ->willReturn(new Result([], []));
        $resource = $this->createBacklogResource($this->createTransactionConnection());
        $resource->expects(self::never())->method('saveByChunkId');
        $resource->expects(self::never())->method('deleteByChunkId');

        $this->createProcessing(
            $sourceProvider,
            $documentUpdater,
            $this->createCollectionFactory($resource)
        )->deltaUpdate([10], self::INDEX_VERSION);
    }

    public function testRollsBackAFailedProductUpdate(): void
    {
        $source = $this->createSource(1, 'Description');
        $sourceProvider = $this->createDeltaSourceProvider([10], [10 => [$source]]);
        $failure = new RuntimeException('update failed');
        $documentUpdater = $this->createMock(DocumentUpdater::class);
        $documentUpdater->expects(self::once())
            ->method('deltaUpdate')
            ->willThrowException($failure);
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::never())->method('commit');
        $connection->expects(self::once())->method('rollBack');
        $resource = $this->createBacklogResourceStub($connection);

        $this->expectExceptionObject($failure);

        $this->createProcessing(
            $sourceProvider,
            $documentUpdater,
            $this->createCollectionFactory($resource)
        )->deltaUpdate([10], self::INDEX_VERSION);
    }

    public function testDoesNotLoadAnEmptyDeltaUpdate(): void
    {
        $sourceProvider = $this->createMock(SourceProvider::class);
        $sourceProvider->expects(self::never())->method('getAffectedProductIds');
        $sourceProvider->expects(self::never())->method('getSourcesByProductIds');
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->expects(self::never())->method('create');

        $this->createProcessing(
            $sourceProvider,
            self::createStub(DocumentUpdater::class),
            $collectionFactory
        )->deltaUpdate([], self::INDEX_VERSION);
    }

    public function testLoadsDeltaUpdatesInBoundedBatches(): void
    {
        $productIds = range(1, self::BATCH_SIZE + 1);
        $loadedBatches = [];
        $sourceProvider = $this->createMock(SourceProvider::class);
        $sourceProvider->expects(self::exactly(2))
            ->method('getAffectedProductIds')
            ->willReturnArgument(0);
        $sourceProvider->expects(self::exactly(2))
            ->method('getSourcesByProductIds')
            ->willReturnCallback(
                static function (array $batch) use (&$loadedBatches): array {
                    /** @var list<int> $batch */
                    $loadedBatches[] = $batch;

                    return array_fill_keys($batch, []);
                }
            );
        $resource = $this->createBacklogResourceStub(
            $this->createTransactionConnection(count($productIds))
        );

        $this->createProcessing(
            $sourceProvider,
            self::createStub(DocumentUpdater::class),
            $this->createCollectionFactory($resource, 2)
        )->deltaUpdate($productIds, self::INDEX_VERSION);

        self::assertSame(
            [range(1, self::BATCH_SIZE), [self::BATCH_SIZE + 1]],
            $loadedBatches
        );
    }

    private function createProcessing(
        SourceProvider $sourceProvider,
        DocumentUpdater $documentUpdater,
        CollectionFactory $collectionFactory
    ): DocumentProcessing {
        $config = self::createStub(DataProcessingConfig::class);
        $config->method('getDocumentProcessingBatchSize')->willReturn(self::BATCH_SIZE);

        return new DocumentProcessing(
            $sourceProvider,
            $documentUpdater,
            $collectionFactory,
            $config
        );
    }

    /**
     * @param list<int> $affectedProductIds
     * @param array<int, list<DocumentSource>> $sources
     */
    private function createDeltaSourceProvider(
        array $affectedProductIds,
        array $sources
    ): SourceProvider {
        $sourceProvider = self::createStub(SourceProvider::class);
        $sourceProvider->method('getAffectedProductIds')->willReturn($affectedProductIds);
        $sourceProvider->method('getSourcesByProductIds')->willReturn($sources);

        return $sourceProvider;
    }

    private function createTransactionConnection(int $transactions = 1): AdapterInterface
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::exactly($transactions))->method('beginTransaction');
        $connection->expects(self::exactly($transactions))->method('commit');
        $connection->expects(self::never())->method('rollBack');

        return $connection;
    }

    private function createBacklogResource(
        AdapterInterface $connection
    ): EmbeddingBacklogResource&MockObject {
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->method('getConnection')->willReturn($connection);

        return $resource;
    }

    private function createBacklogResourceStub(
        AdapterInterface $connection
    ): EmbeddingBacklogResource {
        $resource = self::createStub(EmbeddingBacklogResource::class);
        $resource->method('getConnection')->willReturn($connection);

        return $resource;
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

    private function createSource(int $storeId, string $content): DocumentSource
    {
        return new DocumentSource(
            'description',
            'text_as_is',
            [new StoreScopedSource($storeId, $content)]
        );
    }
}
