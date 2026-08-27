<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Repository;

use DavidBel\AiSearch\Model\Document;
use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Model\DocumentFactory;
use DavidBel\AiSearch\Model\ResourceModel\Document as DocumentResource;
use DavidBel\AiSearch\Model\ResourceModel\Document\Collection;
use DavidBel\AiSearch\Model\ResourceModel\Document\CollectionFactory;
use DavidBel\AiSearch\Repository\Document\DeleteById;
use DavidBel\AiSearch\Repository\Document\Get;
use DavidBel\AiSearch\Repository\Document\Save;
use DavidBel\AiSearch\Test\Unit\TestDouble\GeneratedFactoryStub;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

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
        $document->setDocumentId(12);
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
        $document->setDocumentId(12);
        $documentFactory = $this->createMock(DocumentFactory::class);
        $documentFactory->expects(self::once())
            ->method('create')
            ->willReturn($document);
        $resource = $this->createMock(DocumentResource::class);
        $resource->expects(self::once())
            ->method('load')
            ->with($document, 12)
            ->willReturn($resource);
        $collectionFactory = $this->createCollectionFactory($resource);

        self::assertSame(
            $document,
            (new Get($documentFactory, $collectionFactory))->execute(12)
        );
    }

    public function testGetRejectsMissingDocument(): void
    {
        $document = $this->createDocument();
        $documentFactory = self::createStub(DocumentFactory::class);
        $documentFactory->method('create')->willReturn($document);
        $resource = self::createStub(DocumentResource::class);

        $this->expectException(NoSuchEntityException::class);
        (new Get($documentFactory, $this->createCollectionFactory($resource)))->execute(12);
    }

    public function testSaveRejectsNonModelImplementation(): void
    {
        $this->expectException(CouldNotSaveException::class);

        (new Save(self::createStub(CollectionFactory::class)))
            ->execute(self::createStub(DocumentInterface::class));
    }

    public function testSaveWrapsResourceFailure(): void
    {
        $resource = self::createStub(DocumentResource::class);
        $resource->method('save')->willThrowException(new RuntimeException('save failed'));

        $this->expectException(CouldNotSaveException::class);
        (new Save($this->createCollectionFactory($resource)))->execute($this->createDocument());
    }

    public function testDeleteRejectsNonModelImplementation(): void
    {
        $get = self::createStub(Get::class);
        $get->method('execute')->willReturn(self::createStub(DocumentInterface::class));

        $this->expectException(CouldNotDeleteException::class);
        (new DeleteById($get, self::createStub(CollectionFactory::class)))->execute(12);
    }

    public function testDeleteWrapsResourceFailure(): void
    {
        $get = self::createStub(Get::class);
        $get->method('execute')->willReturn($this->createDocument());
        $resource = self::createStub(DocumentResource::class);
        $resource->method('delete')->willThrowException(new RuntimeException('delete failed'));

        $this->expectException(CouldNotDeleteException::class);
        (new DeleteById($get, $this->createCollectionFactory($resource)))->execute(12);
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
