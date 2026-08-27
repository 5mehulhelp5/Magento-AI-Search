<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Repository\EmbeddingBacklog;

use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Model\EmbeddingBacklog;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as EmbeddingBacklogResource;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Collection;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use DavidBel\AiSearch\Repository\EmbeddingBacklog\Save;
use DavidBel\AiSearch\Test\Unit\TestDouble\GeneratedFactoryStub;
use Exception;
use Magento\Framework\Exception\CouldNotSaveException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class SaveTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(CollectionFactory::class);
    }

    public function testSavesAnEmbeddingBacklogModel(): void
    {
        $embeddingBacklog = $this->createEmbeddingBacklog();
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->expects(self::once())
            ->method('save')
            ->with($embeddingBacklog);
        $collectionFactory = $this->createCollectionFactory($resource);

        self::assertSame(
            $embeddingBacklog,
            (new Save($collectionFactory))->execute($embeddingBacklog)
        );
    }

    public function testRejectsAnUnpersistableImplementation(): void
    {
        $embeddingBacklog = self::createStub(EmbeddingBacklogInterface::class);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->expects(self::never())
            ->method('create');

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage(
            'The embedding backlog implementation cannot be persisted.'
        );

        (new Save($collectionFactory))->execute($embeddingBacklog);
    }

    public function testWrapsAStorageFailure(): void
    {
        $embeddingBacklog = $this->createEmbeddingBacklog();
        $storageFailure = new Exception('storage failed');
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->expects(self::once())
            ->method('save')
            ->willThrowException($storageFailure);
        $collectionFactory = $this->createCollectionFactory($resource);

        try {
            (new Save($collectionFactory))->execute($embeddingBacklog);
            self::fail('A storage failure must be wrapped.');
        } catch (CouldNotSaveException $exception) {
            self::assertSame(
                'Could not save the AI search embedding backlog entry.',
                $exception->getMessage()
            );
            self::assertSame($storageFailure, $exception->getPrevious());
        }
    }

    private function createCollectionFactory(
        EmbeddingBacklogResource $resource
    ): CollectionFactory {
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

    private function createEmbeddingBacklog(): EmbeddingBacklog
    {
        $reflection = new ReflectionClass(EmbeddingBacklog::class);

        return $reflection->newInstanceWithoutConstructor();
    }
}
