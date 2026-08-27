<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Repository\EmbeddingBacklog;

use DavidBel\AiSearch\Model\EmbeddingBacklog;
use DavidBel\AiSearch\Model\EmbeddingBacklogFactory;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as EmbeddingBacklogResource;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Collection;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use DavidBel\AiSearch\Repository\EmbeddingBacklog\Get;
use DavidBel\AiSearch\Test\Unit\TestDouble\GeneratedFactoryStub;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class GetTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(
            EmbeddingBacklogFactory::class,
            CollectionFactory::class
        );
    }

    public function testLoadsAnEmbeddingBacklogById(): void
    {
        $embeddingBacklog = $this->createEmbeddingBacklog();
        $factory = $this->createMock(EmbeddingBacklogFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->willReturn($embeddingBacklog);
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->expects(self::once())
            ->method('load')
            ->with($embeddingBacklog, 12)
            ->willReturnCallback(
                static function (
                    EmbeddingBacklog $model
                ) use ($resource): EmbeddingBacklogResource {
                    $model->setBacklogId(12);

                    return $resource;
                }
            );
        $collectionFactory = $this->createCollectionFactory($resource);

        self::assertSame(
            $embeddingBacklog,
            (new Get($factory, $collectionFactory))->execute(12)
        );
    }

    public function testRejectsAMissingEmbeddingBacklog(): void
    {
        $embeddingBacklog = $this->createEmbeddingBacklog();
        $factory = $this->createMock(EmbeddingBacklogFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->willReturn($embeddingBacklog);
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->expects(self::once())
            ->method('load')
            ->with($embeddingBacklog, 404);
        $collectionFactory = $this->createCollectionFactory($resource);

        $this->expectException(NoSuchEntityException::class);

        (new Get($factory, $collectionFactory))->execute(404);
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
