<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Ingestion\DocumentProcessing\Product;

use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\DeletedProductIdProvider;
use DavidBel\AiSearch\Model\ResourceModel\Document;
use DavidBel\AiSearch\Model\ResourceModel\Document\Collection as DocumentCollection;
use DavidBel\AiSearch\Model\ResourceModel\Document\CollectionFactory as DocumentCollectionFactory;
use DavidBel\AiSearch\Test\Unit\TestDouble\GeneratedFactoryStub;
use InvalidArgumentException;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DeletedProductIdProviderTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(
            DocumentCollectionFactory::class,
            ProductCollectionFactory::class
        );
    }

    public function testLoadsDeletedProductIds(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $select = $this->createMock(Select::class);
        $select->expects(self::once())->method('distinct')->willReturnSelf();
        $select->expects(self::once())->method('from')->willReturnSelf();
        $select->expects(self::once())->method('joinLeft')->willReturnSelf();
        $select->expects(self::exactly(3))->method('where')->willReturnSelf();
        $select->expects(self::once())->method('order')->willReturnSelf();
        $select->expects(self::once())->method('limit')->with(100)->willReturnSelf();
        $connection->expects(self::once())->method('select')->willReturn($select);
        $connection->expects(self::once())->method('fetchCol')->with($select)->willReturn(['11', 12]);

        self::assertSame(
            [11, 12],
            $this->createProvider($connection)->getProductIdsFrom(10, 100)
        );
    }

    /**
     * @param array{int, int} $arguments
     */
    #[DataProvider('invalidArguments')]
    public function testRejectsInvalidArguments(array $arguments, string $message): void
    {
        $documentFactory = $this->createMock(DocumentCollectionFactory::class);
        $documentFactory->expects(self::never())->method('create');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        (new DeletedProductIdProvider(
            $documentFactory,
            self::createStub(ProductCollectionFactory::class)
        ))->getProductIdsFrom($arguments[0], $arguments[1]);
    }

    /**
     * @return array<string, array{array{int, int}, string}>
     */
    public static function invalidArguments(): array
    {
        return [
            'starting id' => [[-1, 10], 'cannot be negative'],
            'limit' => [[0, 0], 'limit must be positive'],
        ];
    }

    #[DataProvider('invalidProductIds')]
    public function testRejectsInvalidDeletedProductId(mixed $productId): void
    {
        $connection = self::createStub(AdapterInterface::class);
        $select = self::createStub(Select::class);
        $select->method('distinct')->willReturnSelf();
        $select->method('from')->willReturnSelf();
        $select->method('joinLeft')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('order')->willReturnSelf();
        $select->method('limit')->willReturnSelf();
        $connection->method('select')->willReturn($select);
        $connection->method('fetchCol')->willReturn([$productId]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid product ID');

        $this->createProvider($connection)->getProductIdsFrom(0, 10);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidProductIds(): array
    {
        return ['zero' => [0], 'negative' => [-1], 'text' => ['invalid']];
    }

    private function createProvider(AdapterInterface $connection): DeletedProductIdProvider
    {
        $documentResource = self::createStub(Document::class);
        $documentResource->method('getConnection')->willReturn($connection);
        $documentResource->method('getMainTable')->willReturn('ai_search_document');
        $documentCollection = self::createStub(DocumentCollection::class);
        $documentCollection->method('getResourceModel')->willReturn($documentResource);
        $documentFactory = self::createStub(DocumentCollectionFactory::class);
        $documentFactory->method('create')->willReturn($documentCollection);
        $productResource = self::createStub(ProductResource::class);
        $productResource->method('getEntityTable')->willReturn('catalog_product_entity');
        $productCollection = self::createStub(ProductCollection::class);
        $productCollection->method('getEntity')->willReturn($productResource);
        $productFactory = self::createStub(ProductCollectionFactory::class);
        $productFactory->method('create')->willReturn($productCollection);

        return new DeletedProductIdProvider($documentFactory, $productFactory);
    }
}
