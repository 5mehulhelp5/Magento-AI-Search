<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Repository;

use DavidBel\AiSearch\Model\Chunk;
use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Model\ChunkFactory;
use DavidBel\AiSearch\Model\ResourceModel\Chunk as ChunkResource;
use DavidBel\AiSearch\Model\ResourceModel\Chunk\Collection;
use DavidBel\AiSearch\Model\ResourceModel\Chunk\CollectionFactory;
use DavidBel\AiSearch\Repository\Chunk\DeleteById;
use DavidBel\AiSearch\Repository\Chunk\Get;
use DavidBel\AiSearch\Repository\Chunk\Save;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class ChunkActionsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(
            ChunkFactory::class,
            CollectionFactory::class
        );
    }

    public function testSaveUsesTheCollectionResourceModel(): void
    {
        $chunk = $this->createChunk();
        $resource = $this->createMock(ChunkResource::class);
        $resource->expects(self::once())
            ->method('save')
            ->with($chunk);
        $collectionFactory = $this->createCollectionFactory($resource);

        self::assertSame($chunk, (new Save($collectionFactory))->execute($chunk));
    }

    public function testGetUsesTheCollectionResourceModel(): void
    {
        $chunk = $this->createChunk();
        $chunk->setChunkId(15);
        $chunkFactory = $this->createMock(ChunkFactory::class);
        $chunkFactory->expects(self::once())
            ->method('create')
            ->willReturn($chunk);
        $resource = $this->createMock(ChunkResource::class);
        $resource->expects(self::once())
            ->method('load')
            ->with($chunk, 15)
            ->willReturn($resource);
        $collectionFactory = $this->createCollectionFactory($resource);

        self::assertSame(
            $chunk,
            (new Get($chunkFactory, $collectionFactory))->execute(15)
        );
    }

    public function testGetRejectsMissingChunk(): void
    {
        $chunk = $this->createChunk();
        $chunkFactory = self::createStub(ChunkFactory::class);
        $chunkFactory->method('create')->willReturn($chunk);
        $resource = self::createStub(ChunkResource::class);

        $this->expectException(NoSuchEntityException::class);
        (new Get($chunkFactory, $this->createCollectionFactory($resource)))->execute(15);
    }

    public function testSaveRejectsNonModelImplementation(): void
    {
        $this->expectException(CouldNotSaveException::class);

        (new Save(self::createStub(CollectionFactory::class)))
            ->execute(self::createStub(ChunkInterface::class));
    }

    public function testSaveWrapsResourceFailure(): void
    {
        $resource = self::createStub(ChunkResource::class);
        $resource->method('save')->willThrowException(new RuntimeException('save failed'));

        $this->expectException(CouldNotSaveException::class);
        (new Save($this->createCollectionFactory($resource)))->execute($this->createChunk());
    }

    public function testDeleteRejectsNonModelImplementation(): void
    {
        $get = self::createStub(Get::class);
        $get->method('execute')->willReturn(self::createStub(ChunkInterface::class));

        $this->expectException(CouldNotDeleteException::class);
        (new DeleteById($get, self::createStub(CollectionFactory::class)))->execute(15);
    }

    public function testDeleteWrapsResourceFailure(): void
    {
        $get = self::createStub(Get::class);
        $get->method('execute')->willReturn($this->createChunk());
        $resource = self::createStub(ChunkResource::class);
        $resource->method('delete')->willThrowException(new RuntimeException('delete failed'));

        $this->expectException(CouldNotDeleteException::class);
        (new DeleteById($get, $this->createCollectionFactory($resource)))->execute(15);
    }

    public function testDeleteUsesTheCollectionResourceModel(): void
    {
        $chunk = $this->createChunk();
        $get = $this->createMock(Get::class);
        $get->expects(self::once())
            ->method('execute')
            ->with(15)
            ->willReturn($chunk);
        $resource = $this->createMock(ChunkResource::class);
        $resource->expects(self::once())
            ->method('delete')
            ->with($chunk);
        $collectionFactory = $this->createCollectionFactory($resource);

        self::assertTrue((new DeleteById($get, $collectionFactory))->execute(15));
    }

    private function createCollectionFactory(ChunkResource $resource): CollectionFactory
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())
            ->method('getResourceModel')
            ->willReturn($resource);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->expects(self::once())
            ->method('create')
            ->willReturn($collection);

        return $collectionFactory;
    }

    private function createChunk(): Chunk
    {
        $reflection = new ReflectionClass(Chunk::class);

        return $reflection->newInstanceWithoutConstructor();
    }
}
