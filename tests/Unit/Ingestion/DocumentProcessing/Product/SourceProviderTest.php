<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Ingestion\DocumentProcessing\Product;

use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider;
use InvalidArgumentException;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SourceProviderTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(CollectionFactory::class);
    }

    public function testLoadsProductIdsUsingTheProductResource(): void
    {
        $select = $this->createMock(Select::class);
        $select->expects(self::once())
            ->method('from')
            ->with('catalog_product_entity', ['entity_id'])
            ->willReturnSelf();
        $select->expects(self::once())
            ->method('where')
            ->with('entity_id > ?', 10)
            ->willReturnSelf();
        $select->expects(self::once())
            ->method('order')
            ->with('entity_id ASC')
            ->willReturnSelf();
        $select->expects(self::once())
            ->method('limit')
            ->with(200)
            ->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())
            ->method('select')
            ->willReturn($select);
        $connection->expects(self::once())
            ->method('fetchCol')
            ->with($select)
            ->willReturn(['11', 12]);
        $productResource = $this->createMock(ProductResource::class);
        $productResource->expects(self::once())
            ->method('getConnection')
            ->willReturn($connection);
        $productResource->expects(self::once())
            ->method('getEntityTable')
            ->willReturn('catalog_product_entity');

        self::assertSame(
            [11, 12],
            $this->createProvider($productResource)->getProductIdsAfter(10, 200)
        );
    }

    public function testRejectsANonPositiveBatchLimitBeforeLoadingAResource(): void
    {
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->expects(self::never())
            ->method('create');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The product batch limit must be positive.');

        (new SourceProvider($collectionFactory))->getProductIdsAfter(0, 0);
    }

    public function testReturnsImmediatelyForEmptyProductIds(): void
    {
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->expects(self::never())
            ->method('create');

        self::assertSame(
            [],
            (new SourceProvider($collectionFactory))->getByProductIds([])
        );
    }

    public function testResolvesStoreOverridesAndDefaultFallbacks(): void
    {
        $select = $this->createSelectStub();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::exactly(3))
            ->method('select')
            ->willReturn($select);
        $connection->expects(self::once())
            ->method('fetchOne')
            ->with($select)
            ->willReturn('44');
        $connection->expects(self::exactly(2))
            ->method('fetchAll')
            ->with($select)
            ->willReturnOnConsecutiveCalls(
                self::descriptionRows(),
                self::storeAssignments()
            );
        $productResource = $this->createMock(ProductResource::class);
        $productResource->expects(self::once())
            ->method('getConnection')
            ->willReturn($connection);
        $productResource->expects(self::exactly(4))
            ->method('getTable')
            ->willReturnCallback(
                static fn(string $table): string => 'resolved_' . $table
            );
        $productResource->expects(self::once())
            ->method('getProductWebsiteTable')
            ->willReturn('catalog_product_website');

        $sources = $this->createProvider($productResource)
            ->getByProductIds([10, 20]);

        self::assertSame(2, $sources[10][0]->storeId);
        self::assertSame('Store 2', $sources[10][0]->content);
        self::assertSame(3, $sources[10][1]->storeId);
        self::assertSame('Default 10', $sources[10][1]->content);
        self::assertSame(3, $sources[20][0]->storeId);
        self::assertSame('Default 20', $sources[20][0]->content);
    }

    public function testRejectsAnUnresolvedDescriptionAttribute(): void
    {
        $select = $this->createSelectStub();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())
            ->method('select')
            ->willReturn($select);
        $connection->expects(self::once())
            ->method('fetchOne')
            ->willReturn(false);
        $productResource = $this->createMock(ProductResource::class);
        $productResource->expects(self::once())
            ->method('getConnection')
            ->willReturn($connection);
        $productResource->expects(self::exactly(2))
            ->method('getTable')
            ->willReturnArgument(0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The product description attribute could not be resolved.'
        );

        $this->createProvider($productResource)->getByProductIds([10]);
    }

    private function createProvider(ProductResource $productResource): SourceProvider
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())
            ->method('getEntity')
            ->willReturn($productResource);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->expects(self::once())
            ->method('create')
            ->willReturn($collection);

        return new SourceProvider($collectionFactory);
    }

    private function createSelectStub(): Select
    {
        $select = self::createStub(Select::class);
        $select->method('from')
            ->willReturnSelf();
        $select->method('join')
            ->willReturnSelf();
        $select->method('where')
            ->willReturnSelf();
        $select->method('order')
            ->willReturnSelf();
        $select->method('limit')
            ->willReturnSelf();

        return $select;
    }

    /**
     * @return list<array{entity_id: string, store_id: string, value: string|null}>
     */
    private static function descriptionRows(): array
    {
        return [
            ['entity_id' => '10', 'store_id' => '0', 'value' => 'Default 10'],
            ['entity_id' => '10', 'store_id' => '2', 'value' => 'Store 2'],
            ['entity_id' => '20', 'store_id' => '0', 'value' => 'Default 20'],
            ['entity_id' => '20', 'store_id' => '3', 'value' => null],
        ];
    }

    /**
     * @return list<array{product_id: string, store_id: string}>
     */
    private static function storeAssignments(): array
    {
        return [
            ['product_id' => '10', 'store_id' => '2'],
            ['product_id' => '10', 'store_id' => '3'],
            ['product_id' => '20', 'store_id' => '3'],
        ];
    }
}
