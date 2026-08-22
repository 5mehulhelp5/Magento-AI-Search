<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Ingestion\DocumentProcessing\Product;

use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\Eligibility\DataProvider;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\Attributes\DataProvider as PHPUnitDataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class EligibilityDataProviderTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(CollectionFactory::class);
    }

    public function testLoadsEligibleScopeRows(): void
    {
        $scopeRows = [
            ['product_id' => 10, 'store_id' => 1, 'website_id' => 1, 'type_id' => 'simple'],
        ];
        $connection = $this->createConnection([$this->attributeRows(), $scopeRows]);
        $provider = new DataProvider($this->createCollectionFactory($connection));

        self::assertSame($scopeRows, $provider->getEligibleScopeRows([10], [1]));
    }

    public function testRejectsInvalidEligibleScopeRow(): void
    {
        $connection = $this->createConnection([$this->attributeRows(), ['invalid']]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('eligible product scope row is invalid');

        (new DataProvider($this->createCollectionFactory($connection)))
            ->getEligibleScopeRows([10], [1]);
    }

    public function testLoadsChildIdsGroupedByParent(): void
    {
        $connection = $this->createConnection([
            [
                ['parent_id' => '10', 'child_id' => '11'],
                ['parent_id' => 10, 'child_id' => 12],
                ['parent_id' => 20, 'child_id' => 21],
            ],
        ]);
        $provider = new DataProvider($this->createCollectionFactory($connection));

        self::assertSame([10 => [11, 12], 20 => [21]], $provider->getChildIdsByParentId([10, 20]));
    }

    /**
     * @param array<array-key, mixed>|string $row
     */
    #[PHPUnitDataProvider('invalidRelationRows')]
    public function testRejectsInvalidProductRelationRows(array|string $row, string $message): void
    {
        $connection = $this->createConnection([[$row]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        (new DataProvider($this->createCollectionFactory($connection)))
            ->getChildIdsByParentId([10]);
    }

    /**
     * @return array<string, array{array<array-key, mixed>|string, string}>
     */
    public static function invalidRelationRows(): array
    {
        return [
            'not array' => ['invalid', 'relation row is invalid'],
            'parent id' => [['parent_id' => 0, 'child_id' => 11], 'parent_id'],
            'child id' => [['parent_id' => 10, 'child_id' => 0], 'child_id'],
        ];
    }

    public function testReturnsNoEnabledChildrenWithoutChildIds(): void
    {
        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects(self::never())->method('create');

        self::assertSame(
            [],
            (new DataProvider($factory))->getEnabledChildIdsByStoreId(
                [['store_id' => 1]],
                []
            )
        );
    }

    public function testLoadsEnabledChildrenByStoreAndDeduplicatesInput(): void
    {
        $connection = $this->createConnection([
            $this->attributeRows(),
            [
                ['store_id' => '1', 'child_id' => '11'],
                ['store_id' => 2, 'child_id' => 12],
            ],
        ]);
        $provider = new DataProvider($this->createCollectionFactory($connection));

        self::assertSame(
            [1 => [11 => true], 2 => [12 => true]],
            $provider->getEnabledChildIdsByStoreId(
                [['store_id' => 1], ['store_id' => 2], ['store_id' => 1]],
                [10 => [11, 12], 20 => [12]]
            )
        );
    }

    /**
     * @param array<array-key, mixed> $row
     */
    #[PHPUnitDataProvider('invalidEnabledChildRows')]
    public function testRejectsInvalidEnabledChildRows(array $row, string $message): void
    {
        $connection = $this->createConnection([$this->attributeRows(), [$row]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        (new DataProvider($this->createCollectionFactory($connection)))
            ->getEnabledChildIdsByStoreId([['store_id' => 1]], [10 => [11]]);
    }

    /**
     * @return array<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidEnabledChildRows(): array
    {
        return [
            'store id' => [['store_id' => 0, 'child_id' => 11], 'store_id'],
            'child id' => [['store_id' => 1, 'child_id' => 0], 'child_id'],
        ];
    }

    public function testRejectsInvalidScopeStoreBeforeLoadingEnabledChildren(): void
    {
        $connection = $this->createConnection([$this->attributeRows()]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('store_id');

        (new DataProvider($this->createCollectionFactory($connection)))
            ->getEnabledChildIdsByStoreId([['store_id' => 0]], [10 => [11]]);
    }

    public function testLoadsDistinctParentIds(): void
    {
        $connection = $this->createConnection([], ['20', 30]);
        $provider = new DataProvider($this->createCollectionFactory($connection));

        self::assertSame([20, 30], $provider->getParentIdsByChildIds([11, 12]));
    }

    public function testRejectsInvalidParentId(): void
    {
        $connection = $this->createConnection([], [0]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('parent_id');

        (new DataProvider($this->createCollectionFactory($connection)))
            ->getParentIdsByChildIds([11]);
    }

    /**
     * @param array<array-key, mixed>|string $row
     */
    #[PHPUnitDataProvider('invalidAttributeRows')]
    public function testRejectsInvalidEligibilityAttributeRows(
        array|string $row,
        string $message
    ): void {
        $connection = $this->createConnection([[$row]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        (new DataProvider($this->createCollectionFactory($connection)))
            ->getEligibleScopeRows([10], [1]);
    }

    /**
     * @return array<string, array{array<array-key, mixed>|string, string}>
     */
    public static function invalidAttributeRows(): array
    {
        return [
            'not array' => ['invalid', 'attribute row is invalid'],
            'code' => [['attribute_code' => null, 'attribute_id' => 1], 'attribute row is invalid'],
            'id' => [['attribute_code' => 'status', 'attribute_id' => 0], 'attribute_id'],
        ];
    }

    public function testRejectsMissingEligibilityAttribute(): void
    {
        $connection = $this->createConnection([
            [['attribute_code' => 'status', 'attribute_id' => 1]],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('attributes could not be resolved');

        (new DataProvider($this->createCollectionFactory($connection)))
            ->getEligibleScopeRows([10], [1]);
    }

    /**
     * @param list<array<array-key, mixed>> $fetchAllResults
     * @param array<array-key, mixed> $fetchColResult
     */
    private function createConnection(
        array $fetchAllResults,
        array $fetchColResult = []
    ): AdapterInterface {
        $select = self::createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('join')->willReturnSelf();
        $select->method('joinLeft')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('order')->willReturnSelf();
        $select->method('reset')->willReturnSelf();
        $select->method('columns')->willReturnSelf();
        $select->method('distinct')->willReturnSelf();
        $connection = self::createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturnOnConsecutiveCalls(...$fetchAllResults);
        $connection->method('fetchCol')->willReturn($fetchColResult);
        $connection->method('quoteInto')->willReturn('condition');
        $connection->method('getCheckSql')->willReturn('scoped_value');

        return $connection;
    }

    private function createCollectionFactory(AdapterInterface $connection): CollectionFactory
    {
        $resource = self::createStub(ProductResource::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getEntityTable')->willReturn('catalog_product_entity');
        $resource->method('getProductWebsiteTable')->willReturn('catalog_product_website');
        $resource->method('getTable')->willReturnMap([
            ['eav_attribute', 'eav_attribute'],
            ['eav_entity_type', 'eav_entity_type'],
            ['catalog_product_entity_int', 'catalog_product_entity_int'],
            ['catalog_product_relation', 'catalog_product_relation'],
            ['store', 'store'],
        ]);
        $collection = self::createStub(Collection::class);
        $collection->method('getEntity')->willReturn($resource);
        $factory = self::createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return $factory;
    }

    /**
     * @return list<array{attribute_code: string, attribute_id: int}>
     */
    private function attributeRows(): array
    {
        return [
            ['attribute_code' => 'status', 'attribute_id' => 1],
            ['attribute_code' => 'visibility', 'attribute_id' => 2],
        ];
    }
}
