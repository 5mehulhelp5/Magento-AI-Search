<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\Eligibility;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use RuntimeException;

class DataProvider
{
    private const int DEFAULT_STORE_ID = 0;

    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @param list<int> $productIds
     * @return list<array<array-key, mixed>>
     */
    public function getEligibleScopeRows(array $productIds): array
    {
        $productResource = $this->createProductResource();
        $connection = $productResource->getConnection();
        $attributeIds = $this->getAttributeIds($productResource, $connection);
        $attributeTable = $productResource->getTable('catalog_product_entity_int');
        $select = $this->createProductScopeSelect($productResource, $connection);
        $this->joinScopedAttribute(
            $select,
            $connection,
            $attributeTable,
            'status',
            $attributeIds['status']
        );
        $this->joinScopedAttribute(
            $select,
            $connection,
            $attributeTable,
            'visibility',
            $attributeIds['visibility']
        );
        $select->where('product.entity_id IN (?)', $productIds)
            ->where('store.store_id <> ?', self::DEFAULT_STORE_ID)
            ->where('store.is_active = ?', 1)
            ->where(
                $this->getScopedValueExpression($connection, 'status') . ' = ?',
                Status::STATUS_ENABLED
            )
            ->where(
                $this->getScopedValueExpression($connection, 'visibility') . ' IN (?)',
                [Visibility::VISIBILITY_IN_SEARCH, Visibility::VISIBILITY_BOTH]
            )
            ->order(['product.entity_id ASC', 'store.store_id ASC']);

        return $this->toRowList(
            $connection->fetchAll($select),
            'An eligible product scope row is invalid.'
        );
    }

    /**
     * @param list<int> $parentIds
     * @return array<int, list<int>>
     */
    public function getChildIdsByParentId(array $parentIds): array
    {
        $productResource = $this->createProductResource();
        $connection = $productResource->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    $productResource->getTable('catalog_product_relation'),
                    ['parent_id', 'child_id']
                )
                ->where('parent_id IN (?)', $parentIds)
                ->order(['parent_id ASC', 'child_id ASC'])
        );
        $childIdsByParentId = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new RuntimeException('A composite product relation row is invalid.');
            }

            $parentId = $this->toPositiveInteger($row['parent_id'] ?? null, 'parent_id');
            $childIdsByParentId[$parentId][] = $this->toPositiveInteger(
                $row['child_id'] ?? null,
                'child_id'
            );
        }

        return $childIdsByParentId;
    }

    /**
     * @param list<array<array-key, mixed>> $scopeRows
     * @param array<int, list<int>> $childIdsByParentId
     * @return array<int, array<int, true>>
     */
    public function getEnabledChildIdsByStoreId(
        array $scopeRows,
        array $childIdsByParentId
    ): array {
        $childIds = $this->getUniqueChildIds($childIdsByParentId);

        if ($childIds === []) {
            return [];
        }

        $productResource = $this->createProductResource();
        $connection = $productResource->getConnection();
        $attributeIds = $this->getAttributeIds($productResource, $connection);
        $rows = $this->getEnabledChildRows(
            $childIds,
            $this->getStoreIds($scopeRows),
            $attributeIds['status'],
            $productResource,
            $connection
        );
        $enabledChildIdsByStoreId = [];

        foreach ($rows as $row) {
            $storeId = $this->toPositiveInteger($row['store_id'] ?? null, 'store_id');
            $childId = $this->toPositiveInteger($row['child_id'] ?? null, 'child_id');
            $enabledChildIdsByStoreId[$storeId][$childId] = true;
        }

        return $enabledChildIdsByStoreId;
    }

    /**
     * @param list<int> $childIds
     * @return list<int>
     */
    public function getParentIdsByChildIds(array $childIds): array
    {
        $productResource = $this->createProductResource();
        $connection = $productResource->getConnection();

        return $this->toPositiveIntegerList(
            $connection->fetchCol(
                $connection->select()
                    ->from(
                        $productResource->getTable('catalog_product_relation'),
                        ['parent_id']
                    )
                    ->where('child_id IN (?)', $childIds)
                    ->distinct()
            ),
            'parent_id'
        );
    }

    private function createProductResource(): ProductResource
    {
        /** @var ProductResource $productResource */
        $productResource = $this->collectionFactory->create()->getEntity();

        return $productResource;
    }

    /**
     * @return array{status: int, visibility: int}
     */
    private function getAttributeIds(
        ProductResource $productResource,
        AdapterInterface $connection
    ): array {
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    ['attribute' => $productResource->getTable('eav_attribute')],
                    ['attribute_code', 'attribute_id']
                )
                ->join(
                    ['entity_type' => $productResource->getTable('eav_entity_type')],
                    'entity_type.entity_type_id = attribute.entity_type_id',
                    []
                )
                ->where('entity_type.entity_type_code = ?', 'catalog_product')
                ->where('attribute.attribute_code IN (?)', ['status', 'visibility'])
        );
        $attributeIds = [];

        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['attribute_code'] ?? null)) {
                throw new RuntimeException('A product eligibility attribute row is invalid.');
            }

            $attributeIds[$row['attribute_code']] = $this->toPositiveInteger(
                $row['attribute_id'] ?? null,
                'attribute_id'
            );
        }

        if (!isset($attributeIds['status'], $attributeIds['visibility'])) {
            throw new RuntimeException('Product eligibility attributes could not be resolved.');
        }

        return [
            'status' => $attributeIds['status'],
            'visibility' => $attributeIds['visibility'],
        ];
    }

    private function createProductScopeSelect(
        ProductResource $productResource,
        AdapterInterface $connection
    ): Select {
        return $connection->select()
            ->from(
                ['product' => $productResource->getEntityTable()],
                ['product_id' => 'entity_id', 'type_id']
            )
            ->join(
                ['assignment' => $productResource->getProductWebsiteTable()],
                'assignment.product_id = product.entity_id',
                []
            )
            ->join(
                ['store' => $productResource->getTable('store')],
                'store.website_id = assignment.website_id',
                ['store_id', 'website_id']
            );
    }

    private function joinScopedAttribute(
        Select $select,
        AdapterInterface $connection,
        string $attributeTable,
        string $alias,
        int $attributeId
    ): void {
        $defaultAlias = $alias . '_default';
        $storeAlias = $alias . '_store';
        $select->join(
            [$defaultAlias => $attributeTable],
            $connection->quoteInto(
                $defaultAlias . '.entity_id = product.entity_id AND '
                . $defaultAlias . '.attribute_id = ?',
                $attributeId
            ) . $connection->quoteInto(
                ' AND ' . $defaultAlias . '.store_id = ?',
                self::DEFAULT_STORE_ID
            ),
            []
        );
        $select->joinLeft(
            [$storeAlias => $attributeTable],
            $connection->quoteInto(
                $storeAlias . '.entity_id = product.entity_id AND '
                . $storeAlias . '.attribute_id = ?',
                $attributeId
            ) . ' AND ' . $storeAlias . '.store_id = store.store_id',
            []
        );
    }

    private function getScopedValueExpression(
        AdapterInterface $connection,
        string $alias
    ): string {
        return (string) $connection->getCheckSql(
            $alias . '_store.value_id > 0',
            $alias . '_store.value',
            $alias . '_default.value'
        );
    }

    /**
     * @param list<int> $childIds
     * @param list<int> $storeIds
     * @return list<array<array-key, mixed>>
     */
    private function getEnabledChildRows(
        array $childIds,
        array $storeIds,
        int $statusAttributeId,
        ProductResource $productResource,
        AdapterInterface $connection
    ): array {
        $select = $this->createProductScopeSelect($productResource, $connection);
        $this->joinScopedAttribute(
            $select,
            $connection,
            $productResource->getTable('catalog_product_entity_int'),
            'status',
            $statusAttributeId
        );
        $select->reset(Select::COLUMNS)
            ->columns(['child_id' => 'product.entity_id', 'store_id' => 'store.store_id'])
            ->where('product.entity_id IN (?)', $childIds)
            ->where('store.store_id IN (?)', $storeIds)
            ->where('store.is_active = ?', 1)
            ->where(
                $this->getScopedValueExpression($connection, 'status') . ' = ?',
                Status::STATUS_ENABLED
            )
            ->order(['store.store_id ASC', 'product.entity_id ASC']);

        return $this->toRowList(
            $connection->fetchAll($select),
            'An eligible child product row is invalid.'
        );
    }

    /**
     * @param array<int, list<int>> $childIdsByParentId
     * @return list<int>
     */
    private function getUniqueChildIds(array $childIdsByParentId): array
    {
        $childIds = [];

        foreach ($childIdsByParentId as $parentChildIds) {
            $childIds += array_fill_keys($parentChildIds, true);
        }

        return array_keys($childIds);
    }

    /**
     * @param list<array<array-key, mixed>> $scopeRows
     * @return list<int>
     */
    private function getStoreIds(array $scopeRows): array
    {
        $storeIds = [];

        foreach ($scopeRows as $scopeRow) {
            $storeId = $this->toPositiveInteger($scopeRow['store_id'] ?? null, 'store_id');
            $storeIds[$storeId] = true;
        }

        return array_keys($storeIds);
    }

    /**
     * @param array<array-key, mixed> $rows
     * @return list<array<array-key, mixed>>
     */
    private function toRowList(array $rows, string $errorMessage): array
    {
        $rowList = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new RuntimeException($errorMessage);
            }

            $rowList[] = $row;
        }

        return $rowList;
    }

    /**
     * @param array<array-key, mixed> $values
     * @return list<int>
     */
    private function toPositiveIntegerList(array $values, string $field): array
    {
        return array_map(
            fn(mixed $value): int => $this->toPositiveInteger($value, $field),
            array_values($values)
        );
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
