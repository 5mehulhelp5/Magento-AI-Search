<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Repository;

use DavidBel\AiSearch\Model\Document;
use DavidBel\AiSearch\Model\DocumentFactory;
use DavidBel\AiSearch\Model\ResourceModel\Document as DocumentResource;
use DavidBel\AiSearch\Model\ResourceModel\Document\Collection;
use DavidBel\AiSearch\Model\ResourceModel\Document\CollectionFactory;
use DavidBel\AiSearch\Repository\Document\DeleteById;
use DavidBel\AiSearch\Repository\Document\Get;
use DavidBel\AiSearch\Repository\Document\Save;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class DocumentActionsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(
            DocumentFactory::class,
            CollectionFactory::class
        );
    }

    public function testSaveUsesTheCollectionResourceModel(): void
    {
        $document = $this->createDocument();
        $resource = $this->createMock(DocumentResource::class);
        $resource->expects(self::once())
            ->method('save')
            ->with($document);
        $collectionFactory = $this->createCollectionFactory($resource);

        self::assertSame($document, (new Save($collectionFactory))->execute($document));
    }

    public function testGetUsesTheCollectionResourceModel(): void
    {
        $document = $this->createDocument();
        $documentFactory = $this->createMock(DocumentFactory::class);
        $documentFactory->expects(self::once())
            ->method('create')
            ->willReturn($document);
        $resource = $this->createMock(DocumentResource::class);
        $resource->expects(self::once())
            ->method('load')
            ->with($document, 12)
            ->willReturnCallback(
                static function (Document $model) use ($resource): DocumentResource {
                    $model->setDocumentId(12);

                    return $resource;
                }
            );
        $collectionFactory = $this->createCollectionFactory($resource);

        self::assertSame(
            $document,
            (new Get($documentFactory, $collectionFactory))->execute(12)
        );
    }

    public function testDeleteUsesTheCollectionResourceModel(): void
    {
        $document = $this->createDocument();
        $get = $this->createMock(Get::class);
        $get->expects(self::once())
            ->method('execute')
            ->with(12)
            ->willReturn($document);
        $resource = $this->createMock(DocumentResource::class);
        $resource->expects(self::once())
            ->method('delete')
            ->with($document);
        $collectionFactory = $this->createCollectionFactory($resource);

        self::assertTrue((new DeleteById($get, $collectionFactory))->execute(12));
    }

    private function createCollectionFactory(
        DocumentResource $resource
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

    private function createDocument(): Document
    {
        $reflection = new ReflectionClass(Document::class);

        return $reflection->newInstanceWithoutConstructor();
    }
}
