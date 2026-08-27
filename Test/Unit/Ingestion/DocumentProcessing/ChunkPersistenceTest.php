<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Ingestion\DocumentProcessing;

use DavidBel\AiSearch\Api\ChunkRepositoryInterface;
use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Api\Data\ChunkSearchResultsInterface;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdater\ChunkPersistence;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\UpdateMode;
use DavidBel\AiSearch\Model\ChunkFactory;
use DavidBel\AiSearch\Test\Unit\TestDouble\GeneratedFactoryStub;
use LogicException;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ChunkPersistenceTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(
            SearchCriteriaBuilderFactory::class,
            ChunkFactory::class
        );
    }

    public function testReconcilesUnchangedChangedNewAndDeletedChunks(): void
    {
        $unchanged = $this->chunk(10, 0, 'same');
        $changed = $this->createMock(ChunkInterface::class);
        $changed->method('getChunkId')->willReturn(11);
        $changed->method('getChunkIndex')->willReturn(1);
        $changed->method('getContent')->willReturn('old');
        $changed->method('getContentHash')->willReturn(hash('sha256', 'old'));
        $deleted = $this->chunk(13, 3, 'deleted');
        $new = $this->createMock(ChunkInterface::class);
        $new->expects(self::once())->method('setDocumentId')->with(5)->willReturnSelf();
        $new->expects(self::once())->method('setChunkIndex')->with(2)->willReturnSelf();
        $new->expects(self::once())->method('setContent')->with('new')->willReturnSelf();
        $new->expects(self::once())
            ->method('setContentHash')
            ->with(hash('sha256', 'new'))
            ->willReturnSelf();
        $new->method('getChunkId')->willReturn(12);
        $changed->expects(self::once())->method('setContent')->with('changed')->willReturnSelf();
        $changed->expects(self::once())
            ->method('setContentHash')
            ->with(hash('sha256', 'changed'))
            ->willReturnSelf();
        $repository = $this->createRepository([$unchanged, $changed, $deleted]);
        $repository->expects(self::exactly(2))
            ->method('save')
            ->willReturnOnConsecutiveCalls($changed, $new);
        $repository->expects(self::once())->method('deleteById')->with(13);
        $factory = self::createStub(ChunkFactory::class);
        $factory->method('create')->willReturn($new);

        $result = $this->createPersistence($repository, $factory)->reconcile(
            5,
            ['same', 'changed', 'new'],
            UpdateMode::DeltaUpdate
        );

        self::assertSame([11, 12], $result->upsertChunkIds);
        self::assertSame([13], $result->deleteChunkIds);
    }

    public function testFullUpdateReturnsUnchangedChunkId(): void
    {
        $chunk = $this->chunk(10, 0, 'same');
        $repository = $this->createRepository([$chunk]);
        $repository->expects(self::never())->method('save');

        $result = $this->createPersistence(
            $repository,
            self::createStub(ChunkFactory::class)
        )->reconcile(5, ['same'], UpdateMode::FullUpdate);

        self::assertSame([10], $result->upsertChunkIds);
        self::assertSame([], $result->deleteChunkIds);
    }

    public function testHashMatchStillRequiresIdenticalContent(): void
    {
        $chunk = $this->createMock(ChunkInterface::class);
        $chunk->method('getChunkIndex')->willReturn(0);
        $chunk->method('getContentHash')->willReturn(hash('sha256', 'new'));
        $chunk->method('getContent')->willReturn('different');
        $chunk->method('getChunkId')->willReturn(10);
        $chunk->expects(self::once())->method('setContent')->with('new')->willReturnSelf();
        $chunk->expects(self::once())->method('setContentHash')->willReturnSelf();
        $repository = $this->createRepository([$chunk]);
        $repository->expects(self::once())->method('save')->with($chunk)->willReturn($chunk);

        self::assertSame(
            [10],
            $this->createPersistence($repository, self::createStub(ChunkFactory::class))
                ->reconcile(5, ['new'], UpdateMode::DeltaUpdate)
                ->upsertChunkIds
        );
    }

    public function testReturnsAndDeletesChunkIdsByDocument(): void
    {
        $first = $this->chunk(10, 0, 'first');
        $second = $this->chunk(11, 1, 'second');
        $repository = $this->createRepository([$first, $second]);
        $persistence = $this->createPersistence(
            $repository,
            self::createStub(ChunkFactory::class)
        );

        self::assertSame([10, 11], $persistence->getChunkIdsByDocumentId(5));

        $deleteRepository = $this->createRepository([$first, $second]);
        $deleteRepository->expects(self::exactly(2))->method('deleteById');
        self::assertSame(
            [10, 11],
            $this->createPersistence(
                $deleteRepository,
                self::createStub(ChunkFactory::class)
            )->deleteByDocumentId(5)
        );
    }

    public function testRequiresPersistedChunkId(): void
    {
        $chunk = $this->chunk(null, 0, 'same');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must have an ID');

        $this->createPersistence(
            $this->createRepository([$chunk]),
            self::createStub(ChunkFactory::class)
        )->getChunkIdsByDocumentId(5);
    }

    private function chunk(?int $id, int $index, string $content): ChunkInterface
    {
        $chunk = self::createStub(ChunkInterface::class);
        $chunk->method('getChunkId')->willReturn($id);
        $chunk->method('getChunkIndex')->willReturn($index);
        $chunk->method('getContent')->willReturn($content);
        $chunk->method('getContentHash')->willReturn(hash('sha256', $content));

        return $chunk;
    }

    /**
     * @param list<ChunkInterface> $chunks
     */
    private function createRepository(
        array $chunks
    ): ChunkRepositoryInterface&MockObject {
        $results = self::createStub(ChunkSearchResultsInterface::class);
        $results->method('getItems')->willReturn($chunks);
        $repository = $this->createMock(ChunkRepositoryInterface::class);
        $repository->expects(self::once())->method('getList')->willReturn($results);

        return $repository;
    }

    private function createPersistence(
        ChunkRepositoryInterface $repository,
        ChunkFactory $chunkFactory
    ): ChunkPersistence {
        $sortOrder = self::createStub(SortOrder::class);
        $sortBuilder = self::createStub(SortOrderBuilder::class);
        $sortBuilder->method('setField')->willReturnSelf();
        $sortBuilder->method('setAscendingDirection')->willReturnSelf();
        $sortBuilder->method('create')->willReturn($sortOrder);
        $criteria = self::createStub(SearchCriteriaInterface::class);
        $criteriaBuilder = self::createStub(SearchCriteriaBuilder::class);
        $criteriaBuilder->method('addFilter')->willReturnSelf();
        $criteriaBuilder->method('addSortOrder')->willReturnSelf();
        $criteriaBuilder->method('create')->willReturn($criteria);
        $criteriaFactory = self::createStub(SearchCriteriaBuilderFactory::class);
        $criteriaFactory->method('create')->willReturn($criteriaBuilder);

        return new ChunkPersistence(
            $criteriaFactory,
            $sortBuilder,
            $repository,
            $chunkFactory
        );
    }
}
