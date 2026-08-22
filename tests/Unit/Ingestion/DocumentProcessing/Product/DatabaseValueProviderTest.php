<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Ingestion\DocumentProcessing\Product;

use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\AttributeValueProvider;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\EmbeddingTemplate\AttributeData;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\EmbeddingTemplate\AttributeDataProvider;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DatabaseValueProviderTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(CollectionFactory::class);
    }

    public function testAttributeValueProviderReturnsEmptyWithoutInput(): void
    {
        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects(self::never())->method('create');
        $provider = new AttributeValueProvider($factory);

        self::assertSame([], $provider->getValuesBySourceCode([], [10]));
        self::assertSame([], $provider->getValuesBySourceCode(['name'], []));
    }

    public function testAttributeValueProviderLoadsTextAndVarcharValues(): void
    {
        $connection = $this->createConnection([
            [
                ['attribute_id' => '1', 'attribute_code' => 'description', 'backend_type' => 'text'],
                ['attribute_id' => 2, 'attribute_code' => 'name', 'backend_type' => 'varchar'],
            ],
            [
                ['attribute_id' => 1, 'entity_id' => 10, 'store_id' => 0, 'value' => 'Description'],
                ['attribute_id' => 1, 'entity_id' => 10, 'store_id' => 1, 'value' => null],
            ],
            [
                ['attribute_id' => 2, 'entity_id' => '10', 'store_id' => '1', 'value' => 'Name'],
            ],
        ]);
        $provider = new AttributeValueProvider($this->createCollectionFactory($connection));

        self::assertSame(
            [
                'description' => ['10:0' => 'Description'],
                'name' => ['10:1' => 'Name'],
            ],
            $provider->getValuesBySourceCode(['description', 'name'], [10])
        );
    }

    /**
     * @param array<array-key, mixed>|string $row
     */
    #[DataProvider('invalidSourceAttributeRows')]
    public function testAttributeValueProviderRejectsInvalidAttributeRows(
        array|string $row,
        string $message
    ): void {
        $connection = $this->createConnection([[$row]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        (new AttributeValueProvider($this->createCollectionFactory($connection)))
            ->getValuesBySourceCode(['name'], [10]);
    }

    /**
     * @return array<string, array{array<array-key, mixed>|string, string}>
     */
    public static function invalidSourceAttributeRows(): array
    {
        return [
            'not array' => ['invalid', 'attribute row is invalid'],
            'id' => [
                ['attribute_id' => 0, 'attribute_code' => 'name', 'backend_type' => 'varchar'],
                'attribute_id',
            ],
            'code' => [
                ['attribute_id' => 1, 'attribute_code' => '', 'backend_type' => 'varchar'],
                'attribute_code',
            ],
            'backend' => [
                ['attribute_id' => 1, 'attribute_code' => 'name', 'backend_type' => 'decimal'],
                'backend type is not supported',
            ],
        ];
    }

    public function testAttributeValueProviderRejectsUnresolvedAttribute(): void
    {
        $connection = $this->createConnection([[]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('could not be resolved');

        (new AttributeValueProvider($this->createCollectionFactory($connection)))
            ->getValuesBySourceCode(['name'], [10]);
    }

    /**
     * @param array<array-key, mixed>|string $valueRow
     */
    #[DataProvider('invalidSourceValueRows')]
    public function testAttributeValueProviderRejectsInvalidValueRows(
        array|string $valueRow,
        string $message
    ): void {
        $connection = $this->createConnection([
            [['attribute_id' => 1, 'attribute_code' => 'name', 'backend_type' => 'varchar']],
            [$valueRow],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        (new AttributeValueProvider($this->createCollectionFactory($connection)))
            ->getValuesBySourceCode(['name'], [10]);
    }

    /**
     * @return array<string, array{array<array-key, mixed>|string, string}>
     */
    public static function invalidSourceValueRows(): array
    {
        return [
            'not array' => ['invalid', 'value row is invalid'],
            'non-string value' => [
                ['attribute_id' => 1, 'entity_id' => 10, 'store_id' => 0, 'value' => 12],
                'value is not a string',
            ],
            'unknown attribute' => [
                ['attribute_id' => 2, 'entity_id' => 10, 'store_id' => 0, 'value' => 'Name'],
                'attribute is unknown',
            ],
            'product id' => [
                ['attribute_id' => 1, 'entity_id' => 0, 'store_id' => 0, 'value' => 'Name'],
                'entity_id',
            ],
            'store id' => [
                ['attribute_id' => 1, 'entity_id' => 10, 'store_id' => -1, 'value' => 'Name'],
                'store_id',
            ],
        ];
    }

    public function testAttributeDataProviderLoadsMetadataValuesAndLabels(): void
    {
        $connection = $this->createConnection([
            [
                self::attributeRow(1, 'color', 'int', 'select'),
                self::attributeRow(2, 'description', 'text', 'textarea'),
                self::attributeRow(3, 'name', 'varchar', 'text'),
            ],
            [
                ['attribute_id' => 1, 'entity_id' => 10, 'store_id' => 0, 'value' => 5],
                ['attribute_id' => 1, 'entity_id' => 10, 'store_id' => 2, 'value' => null],
            ],
            [['attribute_id' => 2, 'entity_id' => 10, 'store_id' => 0, 'value' => 'Text']],
            [['attribute_id' => 3, 'entity_id' => 10, 'store_id' => 2, 'value' => 'Name']],
            [
                ['option_id' => 5, 'store_id' => 0, 'value' => 'Red'],
                ['option_id' => 5, 'store_id' => 2, 'value' => 'Crimson'],
            ],
        ]);
        $provider = new AttributeDataProvider($this->createCollectionFactory($connection));

        self::assertEquals(
            new AttributeData(
                [
                    1 => ['code' => 'color', 'backend_type' => 'int', 'frontend_input' => 'select'],
                    2 => ['code' => 'description', 'backend_type' => 'text', 'frontend_input' => 'textarea'],
                    3 => ['code' => 'name', 'backend_type' => 'varchar', 'frontend_input' => 'text'],
                ],
                [
                    'color' => ['10:0' => '5'],
                    'description' => ['10:0' => 'Text'],
                    'name' => ['10:2' => 'Name'],
                ],
                [5 => [0 => 'Red', 2 => 'Crimson']]
            ),
            $provider->get(['color', 'description', 'name'], [10], [2])
        );
    }

    public function testAttributeDataProviderSupportsNoAttributes(): void
    {
        $connection = $this->createConnection([[]]);
        $provider = new AttributeDataProvider($this->createCollectionFactory($connection));

        self::assertEquals(new AttributeData([], [], []), $provider->get([], [10], [1]));
    }

    /**
     * @param array<array-key, mixed>|string $row
     */
    #[DataProvider('invalidTemplateAttributeRows')]
    public function testAttributeDataProviderRejectsInvalidAttributeRows(
        array|string $row,
        string $message
    ): void {
        $connection = $this->createConnection([[$row]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        (new AttributeDataProvider($this->createCollectionFactory($connection)))
            ->get(['name'], [10], [1]);
    }

    /**
     * @return array<string, array{array<array-key, mixed>|string, string}>
     */
    public static function invalidTemplateAttributeRows(): array
    {
        return [
            'not array' => ['invalid', 'attribute row is invalid'],
            'backend empty' => [self::attributeRow(1, 'name', '', 'text'), 'backend_type'],
            'backend unsupported' => [self::attributeRow(1, 'name', 'decimal', 'text'), 'not supported'],
            'input empty' => [self::attributeRow(1, 'name', 'varchar', ''), 'frontend_input'],
            'input unsupported' => [self::attributeRow(1, 'name', 'varchar', 'date'), 'not supported'],
            'id' => [self::attributeRow(0, 'name', 'varchar', 'text'), 'attribute_id'],
            'code' => [self::attributeRow(1, '', 'varchar', 'text'), 'attribute_code'],
        ];
    }

    public function testAttributeDataProviderRejectsUnresolvedAttribute(): void
    {
        $connection = $this->createConnection([[]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('could not be resolved');

        (new AttributeDataProvider($this->createCollectionFactory($connection)))
            ->get(['name'], [10], [1]);
    }

    /**
     * @param array<array-key, mixed>|string $row
     */
    #[DataProvider('invalidTemplateRawValueRows')]
    public function testAttributeDataProviderRejectsInvalidRawValues(
        array|string $row,
        string $message
    ): void {
        $connection = $this->createConnection([
            [self::attributeRow(1, 'name', 'varchar', 'text')],
            [$row],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        (new AttributeDataProvider($this->createCollectionFactory($connection)))
            ->get(['name'], [10], [1]);
    }

    /**
     * @return array<string, array{array<array-key, mixed>|string, string}>
     */
    public static function invalidTemplateRawValueRows(): array
    {
        return [
            'not array' => ['invalid', 'value row is invalid'],
            'attribute id' => [
                ['attribute_id' => 0, 'entity_id' => 10, 'store_id' => 0, 'value' => 'Name'],
                'attribute_id',
            ],
            'unknown attribute' => [
                ['attribute_id' => 2, 'entity_id' => 10, 'store_id' => 0, 'value' => 'Name'],
                'attribute is unknown',
            ],
            'entity id' => [
                ['attribute_id' => 1, 'entity_id' => 0, 'store_id' => 0, 'value' => 'Name'],
                'entity_id',
            ],
            'store id' => [
                ['attribute_id' => 1, 'entity_id' => 10, 'store_id' => -1, 'value' => 'Name'],
                'store_id',
            ],
            'value type' => [
                ['attribute_id' => 1, 'entity_id' => 10, 'store_id' => 0, 'value' => 1.5],
                'string or integer',
            ],
        ];
    }

    /**
     * @param array<array-key, mixed>|string $row
     */
    #[DataProvider('invalidOptionLabelRows')]
    public function testAttributeDataProviderRejectsInvalidOptionLabels(
        array|string $row,
        string $message
    ): void {
        $connection = $this->createConnection([
            [self::attributeRow(1, 'color', 'int', 'select')],
            [],
            [$row],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        (new AttributeDataProvider($this->createCollectionFactory($connection)))
            ->get(['color'], [10], [1]);
    }

    /**
     * @return array<string, array{array<array-key, mixed>|string, string}>
     */
    public static function invalidOptionLabelRows(): array
    {
        return [
            'not array' => ['invalid', 'option label row is invalid'],
            'option id' => [['option_id' => 0, 'store_id' => 0, 'value' => 'Red'], 'option_id'],
            'store id' => [['option_id' => 5, 'store_id' => -1, 'value' => 'Red'], 'store_id'],
            'value' => [['option_id' => 5, 'store_id' => 0, 'value' => ''], 'value'],
        ];
    }

    /**
     * @param list<array<array-key, mixed>> $results
     */
    private function createConnection(array $results): AdapterInterface
    {
        $select = self::createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('join')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection = self::createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturnOnConsecutiveCalls(...$results);

        return $connection;
    }

    private function createCollectionFactory(AdapterInterface $connection): CollectionFactory
    {
        $resource = self::createStub(ProductResource::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTable')->willReturnMap([
            ['eav_attribute', 'eav_attribute'],
            ['eav_entity_type', 'eav_entity_type'],
            ['catalog_product_entity_int', 'catalog_product_entity_int'],
            ['catalog_product_entity_text', 'catalog_product_entity_text'],
            ['catalog_product_entity_varchar', 'catalog_product_entity_varchar'],
            ['eav_attribute_option', 'eav_attribute_option'],
            ['eav_attribute_option_value', 'eav_attribute_option_value'],
        ]);
        $resource->method('getEntityTable')->willReturn('catalog_product_entity');
        $resource->method('getLinkField')->willReturn('entity_id');
        $collection = self::createStub(Collection::class);
        $collection->method('getEntity')->willReturn($resource);
        $factory = self::createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return $factory;
    }

    /**
     * @return array<string, int|string>
     */
    private static function attributeRow(
        int $id,
        string $code,
        string $backendType,
        string $frontendInput
    ): array {
        return [
            'attribute_id' => $id,
            'attribute_code' => $code,
            'backend_type' => $backendType,
            'frontend_input' => $frontendInput,
        ];
    }
}
