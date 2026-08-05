<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Ingestion\DocumentProcessing;

use DavidBel\AiSearch\Api\ChunkRepositoryInterface;
use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Api\DocumentRepositoryInterface;
use DavidBel\AiSearch\Model\ChunkFactory;
use DavidBel\AiSearch\Model\ChunkSearchResults;
use DavidBel\AiSearch\Model\DocumentFactory;
use DavidBel\AiSearch\Model\DocumentSearchResults;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Chunking;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdater;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdateResult;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\ScopedSource;
use LogicException;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DocumentUpdaterTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(
            SearchCriteriaBuilderFactory::class,
            DocumentFactory::class,
            ChunkFactory::class
        );
    }

    public function testDeltaUpdateReturnsChangedAndDeletedChunkIds(): void
    {
        $document = $this->createChangedDocument(
            1,
            10,
            'old source hash',
            'New source'
        );
        $documentRepository = $this->createMock(DocumentRepositoryInterface::class);
        $documentRepository->method('getList')
            ->willReturn($this->documentResults([$document]));
        $documentRepository->expects(self::once())
            ->method('save')
            ->with($document)
            ->willReturn($document);
        $changedChunk = $this->createChangedChunk(
            0,
            100,
            'Old chunk',
            'New chunk'
        );
        $staleChunk = $this->createChunkStub(1, 101, 'Stale chunk');
        $chunkRepository = $this->createMock(ChunkRepositoryInterface::class);
        $chunkRepository->method('getList')
            ->willReturn($this->chunkResults([$changedChunk, $staleChunk]));
        $chunkRepository->expects(self::once())
            ->method('save')
            ->with($changedChunk)
            ->willReturn($changedChunk);
        $chunkRepository->expects(self::once())
            ->method('deleteById')
            ->with(101)
            ->willReturn(true);
        $chunking = $this->createMock(Chunking::class);
        $chunking->expects(self::once())
            ->method('chunk')
            ->with('New source')
            ->willReturn(['New chunk']);

        $result = $this->deltaUpdate(
            $this->createUpdater($documentRepository, $chunkRepository, $chunking),
            [new ScopedSource(1, 'New source')]
        );

        self::assertSame([100], $result->upsertChunkIds);
        self::assertSame([101], $result->deletionChunkIds);
    }

    public function testFullUpdateQueuesAnUnchangedChunk(): void
    {
        $content = 'Unchanged source';
        $chunkContent = 'Unchanged chunk';
        $document = $this->createDocumentStub(
            1,
            10,
            hash('sha256', $content)
        );
        $documentRepository = $this->createMock(DocumentRepositoryInterface::class);
        $documentRepository->method('getList')
            ->willReturn($this->documentResults([$document]));
        $documentRepository->expects(self::never())
            ->method('save');
        $chunk = $this->createChunkStub(0, 100, $chunkContent);
        $chunkRepository = $this->createMock(ChunkRepositoryInterface::class);
        $chunkRepository->method('getList')
            ->willReturn($this->chunkResults([$chunk]));
        $chunkRepository->expects(self::never())
            ->method('save');
        $chunkRepository->expects(self::never())
            ->method('deleteById');
        $chunking = $this->createMock(Chunking::class);
        $chunking->expects(self::once())
            ->method('chunk')
            ->with($content)
            ->willReturn([$chunkContent]);

        $result = $this->createUpdater(
            $documentRepository,
            $chunkRepository,
            $chunking
        )->fullUpdate(
            'product',
            42,
            'description',
            [new ScopedSource(1, $content)]
        );

        self::assertSame([100], $result->upsertChunkIds);
        self::assertSame([], $result->deletionChunkIds);
    }

    public function testDeltaUpdateSkipsAnUnchangedSource(): void
    {
        $content = 'Unchanged source';
        $document = $this->createDocumentStub(
            1,
            10,
            hash('sha256', $content)
        );
        $documentRepository = $this->createMock(DocumentRepositoryInterface::class);
        $documentRepository->method('getList')
            ->willReturn($this->documentResults([$document]));
        $documentRepository->expects(self::never())
            ->method('save');
        $chunkRepository = $this->createMock(ChunkRepositoryInterface::class);
        $chunkRepository->expects(self::never())
            ->method('getList');
        $chunkRepository->expects(self::never())
            ->method('save');
        $chunkRepository->expects(self::never())
            ->method('deleteById');
        $chunking = $this->createMock(Chunking::class);
        $chunking->expects(self::never())
            ->method('chunk');

        $result = $this->deltaUpdate(
            $this->createUpdater($documentRepository, $chunkRepository, $chunking),
            [new ScopedSource(1, $content)]
        );

        self::assertSame([], $result->upsertChunkIds);
        self::assertSame([], $result->deletionChunkIds);
    }

    public function testReturnsChunksDeletedWithAStaleDocument(): void
    {
        $document = $this->createDocumentStub(2, 10, 'source hash');
        $documentRepository = $this->createMock(DocumentRepositoryInterface::class);
        $documentRepository->method('getList')
            ->willReturn($this->documentResults([$document]));
        $documentRepository->expects(self::once())
            ->method('deleteById')
            ->with(10)
            ->willReturn(true);
        $chunk = $this->createChunkStub(0, 100, 'Stale chunk');
        $chunkRepository = $this->createMock(ChunkRepositoryInterface::class);
        $chunkRepository->method('getList')
            ->willReturn($this->chunkResults([$chunk]));
        $chunkRepository->expects(self::once())
            ->method('deleteById')
            ->with(100)
            ->willReturn(true);
        $chunking = $this->createMock(Chunking::class);
        $chunking->expects(self::never())
            ->method('chunk');

        $result = $this->deltaUpdate(
            $this->createUpdater($documentRepository, $chunkRepository, $chunking),
            []
        );

        self::assertSame([], $result->upsertChunkIds);
        self::assertSame([100], $result->deletionChunkIds);
    }

    public function testRequiresASavedChunkId(): void
    {
        $document = $this->createChangedDocument(
            1,
            10,
            'old source hash',
            'New source'
        );
        $documentRepository = $this->createMock(DocumentRepositoryInterface::class);
        $documentRepository->method('getList')
            ->willReturn($this->documentResults([$document]));
        $documentRepository->expects(self::once())
            ->method('save')
            ->willReturn($document);
        $chunk = $this->createChangedChunk(0, null, 'Old chunk', 'New chunk');
        $chunkRepository = $this->createMock(ChunkRepositoryInterface::class);
        $chunkRepository->method('getList')
            ->willReturn($this->chunkResults([$chunk]));
        $chunkRepository->expects(self::once())
            ->method('save')
            ->willReturn($chunk);
        $chunking = $this->createMock(Chunking::class);
        $chunking->expects(self::once())
            ->method('chunk')
            ->willReturn(['New chunk']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A persisted AI search chunk must have an ID.');

        $this->deltaUpdate(
            $this->createUpdater($documentRepository, $chunkRepository, $chunking),
            [new ScopedSource(1, 'New source')]
        );
    }

    /**
     * @param list<ScopedSource> $sources
     */
    private function deltaUpdate(
        DocumentUpdater $updater,
        array $sources
    ): DocumentUpdateResult {
        return $updater->deltaUpdate(
            'product',
            42,
            'description',
            $sources
        );
    }

    private function createUpdater(
        DocumentRepositoryInterface $documentRepository,
        ChunkRepositoryInterface $chunkRepository,
        Chunking $chunking
    ): DocumentUpdater {
        $searchCriteria = self::createStub(SearchCriteriaInterface::class);
        $searchCriteriaBuilder = self::createStub(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->method('addFilter')
            ->willReturnSelf();
        $searchCriteriaBuilder->method('addSortOrder')
            ->willReturnSelf();
        $searchCriteriaBuilder->method('create')
            ->willReturn($searchCriteria);
        $searchCriteriaBuilderFactory = self::createStub(
            SearchCriteriaBuilderFactory::class
        );
        $searchCriteriaBuilderFactory->method('create')
            ->willReturn($searchCriteriaBuilder);
        $sortOrderBuilder = self::createStub(SortOrderBuilder::class);
        $sortOrderBuilder->method('setField')
            ->willReturnSelf();
        $sortOrderBuilder->method('setAscendingDirection')
            ->willReturnSelf();
        $sortOrderBuilder->method('create')
            ->willReturn(self::createStub(SortOrder::class));

        return new DocumentUpdater(
            $searchCriteriaBuilderFactory,
            $sortOrderBuilder,
            $documentRepository,
            $chunkRepository,
            self::createStub(DocumentFactory::class),
            self::createStub(ChunkFactory::class),
            $chunking
        );
    }

    private function createDocumentMock(
        int $storeId,
        int $documentId,
        string $sourceHash
    ): DocumentInterface&MockObject {
        $document = $this->createMock(DocumentInterface::class);
        $document->method('getStoreId')
            ->willReturn($storeId);
        $document->method('getDocumentId')
            ->willReturn($documentId);
        $document->method('getSourceHash')
            ->willReturn($sourceHash);

        return $document;
    }

    private function createChangedDocument(
        int $storeId,
        int $documentId,
        string $sourceHash,
        string $newContent
    ): DocumentInterface&MockObject {
        $document = $this->createDocumentMock(
            $storeId,
            $documentId,
            $sourceHash
        );
        $document->expects(self::once())
            ->method('setSourceHash')
            ->with(hash('sha256', $newContent))
            ->willReturnSelf();

        return $document;
    }

    private function createDocumentStub(
        int $storeId,
        int $documentId,
        string $sourceHash
    ): DocumentInterface {
        $document = self::createStub(DocumentInterface::class);
        $document->method('getStoreId')
            ->willReturn($storeId);
        $document->method('getDocumentId')
            ->willReturn($documentId);
        $document->method('getSourceHash')
            ->willReturn($sourceHash);

        return $document;
    }

    private function createChunkMock(
        int $chunkIndex,
        ?int $chunkId,
        string $content
    ): ChunkInterface&MockObject {
        $chunk = $this->createMock(ChunkInterface::class);
        $chunk->method('getChunkIndex')
            ->willReturn($chunkIndex);
        $chunk->method('getChunkId')
            ->willReturn($chunkId);
        $chunk->method('getContent')
            ->willReturn($content);
        $chunk->method('getContentHash')
            ->willReturn(hash('sha256', $content));

        return $chunk;
    }

    private function createChangedChunk(
        int $chunkIndex,
        ?int $chunkId,
        string $content,
        string $newContent
    ): ChunkInterface&MockObject {
        $chunk = $this->createChunkMock($chunkIndex, $chunkId, $content);
        $chunk->expects(self::once())
            ->method('setContent')
            ->with($newContent)
            ->willReturnSelf();
        $chunk->expects(self::once())
            ->method('setContentHash')
            ->with(hash('sha256', $newContent))
            ->willReturnSelf();

        return $chunk;
    }

    private function createChunkStub(
        int $chunkIndex,
        ?int $chunkId,
        string $content
    ): ChunkInterface {
        $chunk = self::createStub(ChunkInterface::class);
        $chunk->method('getChunkIndex')
            ->willReturn($chunkIndex);
        $chunk->method('getChunkId')
            ->willReturn($chunkId);
        $chunk->method('getContent')
            ->willReturn($content);
        $chunk->method('getContentHash')
            ->willReturn(hash('sha256', $content));

        return $chunk;
    }

    /**
     * @param list<DocumentInterface> $documents
     */
    private function documentResults(array $documents): DocumentSearchResults
    {
        $results = new DocumentSearchResults();
        $results->setItems($documents);

        return $results;
    }

    /**
     * @param list<ChunkInterface> $chunks
     */
    private function chunkResults(array $chunks): ChunkSearchResults
    {
        $results = new ChunkSearchResults();
        $results->setItems($chunks);

        return $results;
    }
}
