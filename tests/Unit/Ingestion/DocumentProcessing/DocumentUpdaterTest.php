<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Ingestion\DocumentProcessing;

use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Api\DocumentRepositoryInterface;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentSource;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentSource\StoreScopedSource;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdater;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdater\Chunking;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdater\ChunkPersistence;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdater\DocumentSourceUpdater;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdater\Result;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Parsing;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\UpdateMode;
use DavidBel\AiSearch\Model\DocumentFactory;
use DavidBel\AiSearch\Model\DocumentSearchResults;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use LogicException;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DocumentUpdaterTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(
            SearchCriteriaBuilderFactory::class,
            DocumentFactory::class
        );
    }

    public function testDeltaUpdateReturnsChangedAndDeletedChunkIds(): void
    {
        $document = $this->createDocumentMock(1, 10, 'old source hash');
        $document->expects(self::once())
            ->method('setSourceHash')
            ->with(hash('sha256', 'New source'))
            ->willReturnSelf();
        $document->expects(self::once())->method('setTitle')->with(null)->willReturnSelf();
        $repository = $this->createRepository([$document]);
        $repository->expects(self::once())->method('save')->with($document)->willReturn($document);
        $parsing = $this->createMock(Parsing::class);
        $parsing->expects(self::once())
            ->method('parse')
            ->with('New source', 'text_as_is')
            ->willReturn('New source');
        $chunking = $this->createMock(Chunking::class);
        $chunking->expects(self::once())
            ->method('chunk')
            ->with('New source')
            ->willReturn(['New chunk']);
        $persistence = $this->createMock(ChunkPersistence::class);
        $persistence->expects(self::once())
            ->method('reconcile')
            ->with(10, ['New chunk'], UpdateMode::DeltaUpdate)
            ->willReturn(new Result([100], [101]));

        $result = $this->createUpdater($repository, $parsing, $chunking, $persistence)
            ->deltaUpdate('product', 42, [$this->createSource('New source')]);

        self::assertSame([100], $result->upsertChunkIds);
        self::assertSame([101], $result->deleteChunkIds);
    }

    public function testFullUpdateQueuesAnUnchangedChunk(): void
    {
        $content = 'Unchanged source';
        $document = $this->createDocumentStub(1, 10, hash('sha256', $content));
        $repository = $this->createRepository([$document]);
        $repository->expects(self::never())->method('save');
        $parsing = $this->createMock(Parsing::class);
        $parsing->expects(self::once())->method('parse')->willReturn($content);
        $chunking = $this->createMock(Chunking::class);
        $chunking->expects(self::once())->method('chunk')->willReturn(['Unchanged chunk']);
        $persistence = $this->createMock(ChunkPersistence::class);
        $persistence->expects(self::once())
            ->method('reconcile')
            ->with(10, ['Unchanged chunk'], UpdateMode::FullUpdate)
            ->willReturn(new Result([100], []));

        $result = $this->createUpdater($repository, $parsing, $chunking, $persistence)
            ->fullUpdate('product', 42, [$this->createSource($content)]);

        self::assertSame([100], $result->upsertChunkIds);
        self::assertSame([], $result->deleteChunkIds);
    }

    public function testDeltaUpdateSkipsAnUnchangedSource(): void
    {
        $content = 'Unchanged source';
        $document = $this->createDocumentStub(1, 10, hash('sha256', $content));
        $repository = $this->createRepository([$document]);
        $repository->expects(self::never())->method('save');
        $parsing = $this->createMock(Parsing::class);
        $parsing->expects(self::never())->method('parse');
        $chunking = $this->createMock(Chunking::class);
        $chunking->expects(self::never())->method('chunk');
        $persistence = $this->createMock(ChunkPersistence::class);
        $persistence->expects(self::never())->method('reconcile');

        $result = $this->createUpdater($repository, $parsing, $chunking, $persistence)
            ->deltaUpdate('product', 42, [$this->createSource($content)]);

        self::assertSame([], $result->upsertChunkIds);
        self::assertSame([], $result->deleteChunkIds);
    }

    public function testReturnsChunksDeletedWithAStaleDocument(): void
    {
        $document = $this->createDocumentStub(2, 10, 'source hash');
        $repository = $this->createRepository([$document]);
        $repository->expects(self::once())->method('deleteById')->with(10)->willReturn(true);
        $persistence = $this->createMock(ChunkPersistence::class);
        $persistence->expects(self::once())
            ->method('deleteByDocumentId')
            ->with(10)
            ->willReturn([100]);

        $result = $this->createUpdater(
            $repository,
            self::createStub(Parsing::class),
            self::createStub(Chunking::class),
            $persistence
        )->deltaUpdate(
            'product',
            42,
            [new DocumentSource('description', 'text_as_is', [])]
        );

        self::assertSame([], $result->upsertChunkIds);
        self::assertSame([100], $result->deleteChunkIds);
    }

    public function testRequiresASavedDocumentId(): void
    {
        $document = self::createStub(DocumentInterface::class);
        $document->method('getStoreId')->willReturn(1);
        $document->method('getDocumentId')->willReturn(null);
        $document->method('getSourceCode')->willReturn('description');
        $document->method('getSourceHash')->willReturn('old source hash');
        $document->method('getTitle')->willReturn(null);
        $document->method('setSourceHash')->willReturnSelf();
        $document->method('setTitle')->willReturnSelf();
        $repository = $this->createRepository([$document]);
        $repository->expects(self::once())->method('save')->willReturn($document);
        $parsing = self::createStub(Parsing::class);
        $parsing->method('parse')->willReturn('New source');
        $chunking = self::createStub(Chunking::class);
        $chunking->method('chunk')->willReturn(['New chunk']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A persisted AI search document must have an ID.');

        $this->createUpdater(
            $repository,
            $parsing,
            $chunking,
            self::createStub(ChunkPersistence::class)
        )->deltaUpdate('product', 42, [$this->createSource('New source')]);
    }

    public function testSkipsBlankAndUnchunkableSources(): void
    {
        $parsing = self::createStub(Parsing::class);
        $parsing->method('parse')->willReturn('parsed');
        $chunking = self::createStub(Chunking::class);
        $chunking->method('chunk')->willReturn([]);
        $updater = $this->createSourceUpdater(
            self::createStub(DocumentRepositoryInterface::class),
            $parsing,
            $chunking,
            self::createStub(ChunkPersistence::class)
        );
        $source = new DocumentSource(
            'description',
            'text_as_is',
            [new StoreScopedSource(1, '  '), new StoreScopedSource(2, 'content')]
        );

        self::assertEquals(
            new Result([], []),
            $updater->update('product', 42, $source, [], UpdateMode::DeltaUpdate)
        );
    }

    public function testDeltaUpdatePersistsChangedTitleAndReturnsAllDocumentChunks(): void
    {
        $content = 'Unchanged source';
        $document = $this->createDocumentMock(1, 10, hash('sha256', $content), 'Old title');
        $document->expects(self::once())->method('setTitle')->with('New title')->willReturnSelf();
        $repository = $this->createMock(DocumentRepositoryInterface::class);
        $repository->expects(self::once())->method('save')->with($document)->willReturn($document);
        $persistence = $this->createMock(ChunkPersistence::class);
        $persistence->expects(self::once())
            ->method('getChunkIdsByDocumentId')
            ->with(10)
            ->willReturn([100, 101]);
        $source = new DocumentSource(
            'description',
            'text_as_is',
            [new StoreScopedSource(1, $content, 'New title')]
        );

        self::assertEquals(
            new Result([100, 101], []),
            $this->createSourceUpdater(
                $repository,
                self::createStub(Parsing::class),
                self::createStub(Chunking::class),
                $persistence
            )->update('product', 42, $source, [1 => $document], UpdateMode::DeltaUpdate)
        );
    }

    public function testCreatesANewDocumentAndReturnsAllChunksWhenTitleIsAdded(): void
    {
        $document = $this->createMock(DocumentInterface::class);
        $document->expects(self::once())->method('setSourceEntityType')->with('product')->willReturnSelf();
        $document->expects(self::once())->method('setSourceEntityId')->with(42)->willReturnSelf();
        $document->expects(self::once())->method('setStoreId')->with(1)->willReturnSelf();
        $document->expects(self::once())->method('setSourceCode')->with('description')->willReturnSelf();
        $document->expects(self::once())->method('setSourceHash')->willReturnSelf();
        $document->expects(self::once())->method('setTitle')->with('Title')->willReturnSelf();
        $document->method('getDocumentId')->willReturn(10);
        $factory = self::createStub(DocumentFactory::class);
        $factory->method('create')->willReturn($document);
        $repository = $this->createMock(DocumentRepositoryInterface::class);
        $repository->expects(self::once())->method('save')->with($document)->willReturn($document);
        $parsing = self::createStub(Parsing::class);
        $parsing->method('parse')->willReturn('content');
        $chunking = self::createStub(Chunking::class);
        $chunking->method('chunk')->willReturn(['chunk']);
        $persistence = $this->createMock(ChunkPersistence::class);
        $persistence->expects(self::once())
            ->method('reconcile')
            ->willReturn(new Result([100], [102]));
        $persistence->expects(self::once())
            ->method('getChunkIdsByDocumentId')
            ->with(10)
            ->willReturn([100, 101]);
        $source = new DocumentSource(
            'description',
            'text_as_is',
            [new StoreScopedSource(1, 'content', 'Title')]
        );

        self::assertEquals(
            new Result([100, 101], [102]),
            $this->createSourceUpdater(
                $repository,
                $parsing,
                $chunking,
                $persistence,
                $factory
            )->update('product', 42, $source, [], UpdateMode::DeltaUpdate)
        );
    }

    public function testRejectsDeletingADocumentWithoutAnId(): void
    {
        $document = self::createStub(DocumentInterface::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('persisted AI search document must have an ID');

        $this->createSourceUpdater(
            self::createStub(DocumentRepositoryInterface::class),
            self::createStub(Parsing::class),
            self::createStub(Chunking::class),
            self::createStub(ChunkPersistence::class)
        )->deleteDocuments([1 => $document]);
    }

    private function createUpdater(
        DocumentRepositoryInterface $repository,
        Parsing $parsing,
        Chunking $chunking,
        ChunkPersistence $chunkPersistence
    ): DocumentUpdater {
        $builder = self::createStub(SearchCriteriaBuilder::class);
        $builder->method('addFilter')->willReturnSelf();
        $builder->method('create')->willReturn(self::createStub(SearchCriteriaInterface::class));
        $builderFactory = self::createStub(SearchCriteriaBuilderFactory::class);
        $builderFactory->method('create')->willReturn($builder);

        return new DocumentUpdater(
            $builderFactory,
            $repository,
            new DocumentSourceUpdater(
                $repository,
                self::createStub(DocumentFactory::class),
                $parsing,
                $chunking,
                $chunkPersistence
            )
        );
    }

    private function createSourceUpdater(
        DocumentRepositoryInterface $repository,
        Parsing $parsing,
        Chunking $chunking,
        ChunkPersistence $chunkPersistence,
        ?DocumentFactory $documentFactory = null
    ): DocumentSourceUpdater {
        return new DocumentSourceUpdater(
            $repository,
            $documentFactory ?? self::createStub(DocumentFactory::class),
            $parsing,
            $chunking,
            $chunkPersistence
        );
    }

    /**
     * @param list<DocumentInterface> $documents
     */
    private function createRepository(array $documents): DocumentRepositoryInterface&MockObject
    {
        $repository = $this->createMock(DocumentRepositoryInterface::class);
        $repository->method('getList')->willReturn($this->documentResults($documents));

        return $repository;
    }

    private function createDocumentMock(
        int $storeId,
        ?int $documentId,
        string $sourceHash,
        ?string $title = null
    ): DocumentInterface&MockObject {
        $document = $this->createMock(DocumentInterface::class);
        $document->method('getStoreId')->willReturn($storeId);
        $document->method('getDocumentId')->willReturn($documentId);
        $document->method('getSourceCode')->willReturn('description');
        $document->method('getSourceHash')->willReturn($sourceHash);
        $document->method('getTitle')->willReturn($title);

        return $document;
    }

    private function createDocumentStub(
        int $storeId,
        int $documentId,
        string $sourceHash
    ): DocumentInterface {
        $document = self::createStub(DocumentInterface::class);
        $document->method('getStoreId')->willReturn($storeId);
        $document->method('getDocumentId')->willReturn($documentId);
        $document->method('getSourceCode')->willReturn('description');
        $document->method('getSourceHash')->willReturn($sourceHash);
        $document->method('getTitle')->willReturn(null);

        return $document;
    }

    private function createSource(string $content): DocumentSource
    {
        return new DocumentSource(
            'description',
            'text_as_is',
            [new StoreScopedSource(1, $content)]
        );
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
}
