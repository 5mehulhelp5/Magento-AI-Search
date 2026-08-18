<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer\ProductAttributeOptionIndexer;

use DavidBel\AiSearch\Config\IndexingScopeConfig;
use InvalidArgumentException;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use RuntimeException;

class AffectedProductIdProvider
{
    private const int DEFAULT_STORE_ID = 0;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly AttributeOptionFilterProvider $attributeOptionFilterProvider,
        private readonly IndexingScopeConfig $indexingScopeConfig
    ) {
    }

    /**
     * @param list<int> $optionIds
     * @return iterable<list<int>>
     */
    public function getProductIdBatches(array $optionIds, int $batchSize): iterable
    {
        if ($batchSize < 1) {
            throw new InvalidArgumentException('The affected product batch size must be positive.');
        }

        if ($optionIds === []) {
            return;
        }

        $storeIds = $this->indexingScopeConfig->getStoreIdsForIndexing();

        if ($storeIds === []) {
            return;
        }

        $productResource = $this->createProductResource();
        $connection = $productResource->getConnection();
        $optionFiltersByBackendType = $this->attributeOptionFilterProvider->getByBackendType($optionIds);
        $scopedStoreIds = $this->getScopedStoreIds($storeIds);

        foreach ($optionFiltersByBackendType as $backendType => $attributeFilters) {
            yield from $this->getProductIdBatchesForBackendType(
                $batchSize,
                $backendType,
                $attributeFilters,
                $scopedStoreIds,
                $productResource,
                $connection
            );
        }
    }

    /**
     * @param positive-int $batchSize
     * @param array<int, array{frontend_input: string, option_ids: list<int>}> $attributeFilters
     * @param list<int> $storeIds
     * @return iterable<list<int>>
     */
    private function getProductIdBatchesForBackendType(
        int $batchSize,
        string $backendType,
        array $attributeFilters,
        array $storeIds,
        ProductResource $productResource,
        AdapterInterface $connection
    ): iterable {
        $fromProductId = 0;

        while (true) {
            $productIds = $this->getProductIdsFrom(
                $fromProductId,
                $batchSize,
                $backendType,
                $attributeFilters,
                $storeIds,
                $productResource,
                $connection
            );

            if ($productIds === []) {
                return;
            }

            yield $productIds;
            $fromProductId = $productIds[array_key_last($productIds)];
        }
    }

    private function createProductResource(): ProductResource
    {
        /** @var ProductResource $productResource */
        $productResource = $this->collectionFactory->create()->getEntity();

        return $productResource;
    }

    /**
     * @param positive-int $limit
     * @param array<int, array{frontend_input: string, option_ids: list<int>}> $attributeFilters
     * @param list<int> $storeIds
     * @return list<int>
     */
    private function getProductIdsFrom(
        int $fromProductId,
        int $limit,
        string $backendType,
        array $attributeFilters,
        array $storeIds,
        ProductResource $productResource,
        AdapterInterface $connection
    ): array {
        $conditions = $this->getAttributeValueConditions($attributeFilters, $connection);

        if ($conditions === []) {
            return [];
        }

        $select = $connection->select()
            ->distinct(true)
            ->from(
                ['attribute_value' => $productResource->getTable('catalog_product_entity_' . $backendType)],
                []
            )
            ->join(
                ['product' => $productResource->getEntityTable()],
                sprintf(
                    'product.%1$s = attribute_value.%1$s',
                    $productResource->getLinkField()
                ),
                ['entity_id']
            )
            ->where('product.entity_id > ?', $fromProductId)
            ->where('attribute_value.store_id IN (?)', $storeIds)
            ->where(implode(' OR ', $conditions))
            ->order('product.entity_id ASC')
            ->limit($limit);

        return $this->toPositiveIntegerList($connection->fetchCol($select), 'entity_id');
    }

    /**
     * @param array<int, array{frontend_input: string, option_ids: list<int>}> $attributeFilters
     * @return list<string>
     */
    private function getAttributeValueConditions(
        array $attributeFilters,
        AdapterInterface $connection
    ): array {
        $conditions = [];

        foreach ($attributeFilters as $attributeId => $attributeFilter) {
            $attributeCondition = $connection->quoteInto(
                'attribute_value.attribute_id = ?',
                $attributeId
            );
            $valueConditions = $this->getOptionValueConditions(
                $attributeFilter['frontend_input'],
                $attributeFilter['option_ids'],
                $connection
            );

            if ($valueConditions === []) {
                continue;
            }

            $conditions[] = sprintf(
                '(%s AND (%s))',
                $attributeCondition,
                implode(' OR ', $valueConditions)
            );
        }

        return $conditions;
    }

    /**
     * @param list<int> $optionIds
     * @return list<string>
     */
    private function getOptionValueConditions(
        string $frontendInput,
        array $optionIds,
        AdapterInterface $connection
    ): array {
        if ($frontendInput === 'select') {
            return [
                $connection->prepareSqlCondition(
                    'attribute_value.value',
                    ['in' => $optionIds]
                ),
            ];
        }

        $conditions = [];

        foreach ($optionIds as $optionId) {
            $conditions[] = $connection->prepareSqlCondition(
                'attribute_value.value',
                ['finset' => $optionId]
            );
        }

        return $conditions;
    }

    /**
     * @param list<int> $storeIds
     * @return list<int>
     */
    private function getScopedStoreIds(array $storeIds): array
    {
        $storeIds[] = self::DEFAULT_STORE_ID;
        $storeIds = array_values(array_unique($storeIds));
        sort($storeIds, SORT_NUMERIC);

        return $storeIds;
    }

    /**
     * @param array<array-key, mixed> $values
     * @return list<int>
     */
    private function toPositiveIntegerList(array $values, string $field): array
    {
        $integers = [];

        foreach ($values as $value) {
            $integers[] = $this->toPositiveInteger($value, $field);
        }

        return $integers;
    }

    private function toPositiveInteger(mixed $value, string $field): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if ($integer === false || $integer < 1) {
            throw new RuntimeException(sprintf('Database field "%s" is not a positive integer.', $field));
        }

        return $integer;
    }
}
