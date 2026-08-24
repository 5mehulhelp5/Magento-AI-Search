<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Indexer;

use DavidBel\AiSearch\Config\SemanticDataProcessingConfig;
use DavidBel\AiSearch\Config\EmbeddedAttribute;
use DavidBel\AiSearch\Config\EmbeddedAttributesConfig;
use DavidBel\AiSearch\Config\IndexingScopeConfig;
use DavidBel\AiSearch\Indexer\ProductAttributeOptionIndexer;
use DavidBel\AiSearch\Indexer\ProductAttributeOptionIndexer\AffectedProductIdProvider;
use DavidBel\AiSearch\Indexer\ProductAttributeOptionIndexer\AttributeOptionFilterProvider;
use DavidBel\AiSearch\Indexer\ProductAttributeOptionIndexer\DynamicDocumentAttributeCodeProvider;
use DavidBel\AiSearch\Indexer\ProductAttributeOptionIndexer\ProductIndexerPublisher;
use DavidBel\AiSearch\Indexer\ProductIndexer;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use InvalidArgumentException;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Mview\View\Changelog;
use Magento\Framework\Mview\ViewInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ProductAttributeOptionIndexerTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(CollectionFactory::class);
    }

    public function testDynamicDocumentAttributeCodesAreRecursiveUniqueAndSorted(): void
    {
        $child = new EmbeddedAttribute(' size, color ', false, 'text_as_is', null, null);
        $nested = new EmbeddedAttribute(
            'material',
            false,
            'text_as_is',
            null,
            [new EmbeddedAttribute('color, ', false, 'text_as_is', null, null)]
        );
        $document = new EmbeddedAttribute(
            'embedding_template',
            false,
            'text_as_is',
            null,
            [$child, $nested]
        );
        $config = self::createStub(EmbeddedAttributesConfig::class);
        $config->method('getDynamicDocument')->willReturnMap([[1, $document], [2, null]]);
        $scope = self::createStub(IndexingScopeConfig::class);
        $scope->method('getStoreIdsForIndexing')->willReturn([2, 1]);

        self::assertSame(
            ['color', 'material', 'size'],
            (new DynamicDocumentAttributeCodeProvider($config, $scope))->getAttributeCodes()
        );
    }

    public function testDynamicDocumentWithNoChildrenHasNoAttributeCodes(): void
    {
        $document = new EmbeddedAttribute('embedding_template', false, 'text_as_is', null, null);
        $config = self::createStub(EmbeddedAttributesConfig::class);
        $config->method('getDynamicDocument')->willReturn($document);
        $scope = self::createStub(IndexingScopeConfig::class);
        $scope->method('getStoreIdsForIndexing')->willReturn([1]);

        self::assertSame(
            [],
            (new DynamicDocumentAttributeCodeProvider($config, $scope))->getAttributeCodes()
        );
    }

    public function testOptionFilterReturnsEarlyWithoutDynamicAttributes(): void
    {
        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects(self::never())->method('create');
        $codeProvider = self::createStub(DynamicDocumentAttributeCodeProvider::class);
        $codeProvider->method('getAttributeCodes')->willReturn([]);

        self::assertSame(
            [],
            (new AttributeOptionFilterProvider($factory, $codeProvider))->getByBackendType([5])
        );
    }

    public function testOptionFilterGroupsNormalizesAndSkipsUnsupportedRows(): void
    {
        $rows = [
            $this->optionRow(8, 2, 'varchar', 'multiselect'),
            $this->optionRow(5, 1, 'int', 'select'),
            $this->optionRow(7, 2, 'varchar', 'multiselect'),
            $this->optionRow(5, 1, 'int', 'select'),
            $this->optionRow(9, 3, 'decimal', 'select'),
            $this->optionRow(10, 4, 'int', 'text'),
        ];
        $provider = $this->createOptionFilterProvider($rows, ['color', 'size']);

        self::assertSame(
            [
                'int' => [1 => ['frontend_input' => 'select', 'option_ids' => [5]]],
                'varchar' => [
                    2 => ['frontend_input' => 'multiselect', 'option_ids' => [7, 8]],
                ],
            ],
            $provider->getByBackendType([8, 5, 7, 9, 10])
        );
    }

    /**
     * @param array<array-key, mixed>|string $row
     */
    #[DataProvider('invalidOptionRows')]
    public function testOptionFilterRejectsInvalidRows(array|string $row, string $message): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        $this->createOptionFilterProvider([$row], ['color'])->getByBackendType([5]);
    }

    /**
     * @return array<string, array{array<array-key, mixed>|string, string}>
     */
    public static function invalidOptionRows(): array
    {
        return [
            'not array' => ['invalid', 'option row is invalid'],
            'backend' => [['backend_type' => '', 'frontend_input' => 'select'], 'backend_type'],
            'input' => [['backend_type' => 'int', 'frontend_input' => ''], 'frontend_input'],
            'attribute' => [self::staticOptionRow(5, 0, 'int', 'select'), 'attribute_id'],
            'option' => [self::staticOptionRow(0, 1, 'int', 'select'), 'option_id'],
        ];
    }

    public function testOptionFilterRejectsChangingInputTypeForOneAttribute(): void
    {
        $rows = [
            $this->optionRow(5, 1, 'int', 'select'),
            $this->optionRow(6, 1, 'int', 'multiselect'),
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('input type changed');

        $this->createOptionFilterProvider($rows, ['color'])->getByBackendType([5, 6]);
    }

    public function testAffectedProductProviderReturnsBatchesForEachBackendType(): void
    {
        $connection = $this->createAffectedProductConnection([
            ['10', 20],
            [],
            [30],
            [],
        ]);
        $filters = self::createStub(AttributeOptionFilterProvider::class);
        $filters->method('getByBackendType')->willReturn([
            'int' => [1 => ['frontend_input' => 'select', 'option_ids' => [5]]],
            'varchar' => [2 => ['frontend_input' => 'multiselect', 'option_ids' => [6, 7]]],
        ]);
        $scope = self::createStub(IndexingScopeConfig::class);
        $scope->method('getStoreIdsForIndexing')->willReturn([2, 1, 2]);
        $provider = new AffectedProductIdProvider(
            $this->createCollectionFactory($connection),
            $filters,
            $scope
        );

        self::assertSame(
            [[10, 20], [30]],
            iterator_to_array($provider->getProductIdBatches([5], 2), false)
        );
    }

    public function testAffectedProductProviderReturnsWithoutOptionsStoresOrConditions(): void
    {
        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects(self::never())->method('create');
        $filters = self::createStub(AttributeOptionFilterProvider::class);
        $scope = self::createStub(IndexingScopeConfig::class);
        $scope->method('getStoreIdsForIndexing')->willReturn([]);
        $provider = new AffectedProductIdProvider($factory, $filters, $scope);

        self::assertSame([], iterator_to_array($provider->getProductIdBatches([], 10)));
        self::assertSame([], iterator_to_array($provider->getProductIdBatches([5], 10)));

        $connection = $this->createAffectedProductConnection([]);
        $scopeWithStore = self::createStub(IndexingScopeConfig::class);
        $scopeWithStore->method('getStoreIdsForIndexing')->willReturn([1]);
        $emptyProvider = new AffectedProductIdProvider(
            $this->createCollectionFactory($connection),
            $filters,
            $scopeWithStore
        );
        self::assertSame([], iterator_to_array($emptyProvider->getProductIdBatches([5], 10)));
    }

    public function testAffectedProductProviderRejectsInvalidBatchSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('batch size must be positive');

        iterator_to_array(
            (new AffectedProductIdProvider(
                self::createStub(CollectionFactory::class),
                self::createStub(AttributeOptionFilterProvider::class),
                self::createStub(IndexingScopeConfig::class)
            ))->getProductIdBatches([5], 0)
        );
    }

    public function testAffectedProductProviderSkipsAttributesWithoutValueConditions(): void
    {
        $connection = $this->createAffectedProductConnection([]);
        $filters = self::createStub(AttributeOptionFilterProvider::class);
        $filters->method('getByBackendType')->willReturn([
            'varchar' => [
                2 => ['frontend_input' => 'multiselect', 'option_ids' => []],
            ],
        ]);
        $scope = self::createStub(IndexingScopeConfig::class);
        $scope->method('getStoreIdsForIndexing')->willReturn([1]);
        $provider = new AffectedProductIdProvider(
            $this->createCollectionFactory($connection),
            $filters,
            $scope
        );

        self::assertSame([], iterator_to_array($provider->getProductIdBatches([5], 10)));
    }

    public function testAffectedProductProviderRejectsInvalidDatabaseProductId(): void
    {
        $connection = $this->createAffectedProductConnection([[0]]);
        $filters = self::createStub(AttributeOptionFilterProvider::class);
        $filters->method('getByBackendType')->willReturn([
            'int' => [1 => ['frontend_input' => 'select', 'option_ids' => [5]]],
        ]);
        $scope = self::createStub(IndexingScopeConfig::class);
        $scope->method('getStoreIdsForIndexing')->willReturn([1]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('entity_id');

        iterator_to_array(
            (new AffectedProductIdProvider(
                $this->createCollectionFactory($connection),
                $filters,
                $scope
            ))->getProductIdBatches([5], 10)
        );
    }

    public function testPublisherDoesNothingForEmptyProductIds(): void
    {
        $registry = $this->createMock(IndexerRegistry::class);
        $registry->expects(self::never())->method('get');

        (new ProductIndexerPublisher($registry))->publishProductIds([]);
    }

    public function testPublisherAddsScheduledProductsToChangelog(): void
    {
        $changelog = $this->createMock(Changelog::class);
        $changelog->expects(self::once())->method('addList')->with([10, 20]);
        $view = self::createStub(ViewInterface::class);
        $view->method('getChangelog')->willReturn($changelog);
        $indexer = $this->createMock(IndexerInterface::class);
        $indexer->method('isScheduled')->willReturn(true);
        $indexer->expects(self::once())->method('getView')->willReturn($view);
        $indexer->expects(self::never())->method('reindexList');
        $publisher = new ProductIndexerPublisher($this->createRegistry($indexer));

        $publisher->publishProductIds([10, 20]);
    }

    public function testPublisherReindexesImmediatelyAndCanInvalidate(): void
    {
        $indexer = $this->createMock(IndexerInterface::class);
        $indexer->method('isScheduled')->willReturn(false);
        $indexer->expects(self::once())->method('reindexList')->with([10]);
        $indexer->expects(self::once())->method('invalidate');
        $publisher = new ProductIndexerPublisher($this->createRegistry($indexer, 2));

        $publisher->publishProductIds([10]);
        $publisher->invalidateProductIndexer();
    }

    public function testOptionIndexerDelegatesAllExecutionModes(): void
    {
        $affected = self::createStub(AffectedProductIdProvider::class);
        $affected->method('getProductIdBatches')->willReturn([[10, 20], [30]]);
        $publisher = $this->createMock(ProductIndexerPublisher::class);
        $publisher->expects(self::exactly(6))->method('publishProductIds');
        $publisher->expects(self::once())->method('invalidateProductIndexer');
        $config = self::createStub(SemanticDataProcessingConfig::class);
        $config->method('getDocumentProcessingBatchSize')->willReturn(100);
        $indexer = new ProductAttributeOptionIndexer($affected, $publisher, $config);

        $indexer->executeFull();
        $indexer->executeList([5, '5']);
        $indexer->executeRow(5);
        $indexer->execute([5]);
    }

    /**
     * @param array<array-key, mixed> $ids
     */
    #[DataProvider('invalidOptionIds')]
    public function testOptionIndexerRejectsInvalidOptionIds(array $ids): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('positive integers');

        (new ProductAttributeOptionIndexer(
            self::createStub(AffectedProductIdProvider::class),
            self::createStub(ProductIndexerPublisher::class),
            self::createStub(SemanticDataProcessingConfig::class)
        ))->executeList($ids);
    }

    /**
     * @return array<string, array{array<array-key, mixed>}>
     */
    public static function invalidOptionIds(): array
    {
        return [
            'zero' => [[0]],
            'negative' => [[-1]],
            'text' => [['invalid']],
        ];
    }

    /**
     * @param list<array<array-key, mixed>|string> $rows
     * @param list<string> $attributeCodes
     */
    private function createOptionFilterProvider(
        array $rows,
        array $attributeCodes
    ): AttributeOptionFilterProvider {
        $select = self::createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('join')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection = self::createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn($rows);
        $codes = self::createStub(DynamicDocumentAttributeCodeProvider::class);
        $codes->method('getAttributeCodes')->willReturn($attributeCodes);

        return new AttributeOptionFilterProvider(
            $this->createCollectionFactory($connection),
            $codes
        );
    }

    /**
     * @param list<array<array-key, mixed>> $productIdResults
     */
    private function createAffectedProductConnection(array $productIdResults): AdapterInterface
    {
        $select = self::createStub(Select::class);
        $select->method('distinct')->willReturnSelf();
        $select->method('from')->willReturnSelf();
        $select->method('join')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('order')->willReturnSelf();
        $select->method('limit')->willReturnSelf();
        $connection = self::createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchCol')->willReturnOnConsecutiveCalls(...$productIdResults);
        $connection->method('quoteInto')->willReturn('attribute condition');
        $connection->method('prepareSqlCondition')->willReturn('value condition');

        return $connection;
    }

    private function createCollectionFactory(AdapterInterface $connection): CollectionFactory
    {
        $resource = self::createStub(ProductResource::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTable')->willReturnMap([
            ['eav_attribute_option', 'eav_attribute_option'],
            ['eav_attribute', 'eav_attribute'],
            ['eav_entity_type', 'eav_entity_type'],
            ['catalog_product_entity_int', 'catalog_product_entity_int'],
            ['catalog_product_entity_text', 'catalog_product_entity_text'],
            ['catalog_product_entity_varchar', 'catalog_product_entity_varchar'],
        ]);
        $resource->method('getEntityTable')->willReturn('catalog_product_entity');
        $resource->method('getLinkField')->willReturn('entity_id');
        $collection = self::createStub(Collection::class);
        $collection->method('getEntity')->willReturn($resource);
        $factory = self::createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return $factory;
    }

    private function createRegistry(IndexerInterface $indexer, int $calls = 1): IndexerRegistry
    {
        $registry = $this->createMock(IndexerRegistry::class);
        $registry->expects(self::exactly($calls))
            ->method('get')
            ->with(ProductIndexer::ID)
            ->willReturn($indexer);

        return $registry;
    }

    /**
     * @return array<string, int|string>
     */
    private function optionRow(
        int $optionId,
        int $attributeId,
        string $backendType,
        string $frontendInput
    ): array {
        return self::staticOptionRow($optionId, $attributeId, $backendType, $frontendInput);
    }

    /**
     * @return array<string, int|string>
     */
    private static function staticOptionRow(
        int $optionId,
        int $attributeId,
        string $backendType,
        string $frontendInput
    ): array {
        return [
            'option_id' => $optionId,
            'attribute_id' => $attributeId,
            'backend_type' => $backendType,
            'frontend_input' => $frontendInput,
        ];
    }
}
