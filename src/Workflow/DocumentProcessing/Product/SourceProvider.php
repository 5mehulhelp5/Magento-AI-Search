<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow\DocumentProcessing\Product;

use InvalidArgumentException;
use Magento\Framework\App\ResourceConnection;
use RuntimeException;

readonly class SourceProvider
{
    private const string PRODUCT_ENTITY_TYPE = 'catalog_product';
    // TODO Make this configurable via admin UI
    private const string SOURCE_CODE = 'description';
    private const int DEFAULT_STORE_ID = 0;

    public function __construct(
        private ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @return list<int>
     */
    public function getProductIdsAfter(int $lastProductId, int $limit): array
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('The product batch limit must be positive.');
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                $this->resourceConnection->getTableName('catalog_product_entity'),
                ['entity_id']
            )
            ->where('entity_id > ?', $lastProductId)
            ->order('entity_id ASC')
            ->limit($limit);

        return $this->toIntegerList($connection->fetchCol($select), 'entity_id');
    }

    /**
     * @param list<int> $productIds
     * @return array<int, list<ScopedSource>>
     */
    public function getByProductIds(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $attributeId = $this->getDescriptionAttributeId();
        $values = $this->getDescriptionValues($productIds, $attributeId);
        $assignments = $connection->fetchAll(
            $connection->select()
                ->from(
                    ['assignment' => $this->resourceConnection->getTableName('catalog_product_website')],
                    ['product_id']
                )
                ->join(
                    ['store' => $this->resourceConnection->getTableName('store')],
                    'store.website_id = assignment.website_id',
                    ['store_id']
                )
                ->where('assignment.product_id IN (?)', $productIds)
                ->where('store.store_id <> ?', self::DEFAULT_STORE_ID)
                ->where('store.is_active = ?', 1)
                ->order(['assignment.product_id ASC', 'store.store_id ASC'])
        );

        return $this->buildScopedSources($assignments, $values);
    }

    private function getDescriptionAttributeId(): int
    {
        $connection = $this->resourceConnection->getConnection();
        $attributeTable = $this->resourceConnection->getTableName('eav_attribute');
        $entityTypeTable = $this->resourceConnection->getTableName('eav_entity_type');
        $select = $connection->select()
            ->from(['attribute' => $attributeTable], ['attribute_id'])
            ->join(
                ['entity_type' => $entityTypeTable],
                'entity_type.entity_type_id = attribute.entity_type_id',
                []
            )
            ->where('entity_type.entity_type_code = ?', self::PRODUCT_ENTITY_TYPE)
            ->where('attribute.attribute_code = ?', self::SOURCE_CODE)
            ->limit(1);
        $attributeId = filter_var($connection->fetchOne($select), FILTER_VALIDATE_INT);

        if ($attributeId === false || $attributeId < 1) {
            throw new RuntimeException('The product description attribute could not be resolved.');
        }

        return $attributeId;
    }

    /**
     * @param list<int> $productIds
     * @return array<string, string>
     */
    private function getDescriptionValues(array $productIds, int $attributeId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    $this->resourceConnection->getTableName('catalog_product_entity_text'),
                    ['entity_id', 'store_id', 'value']
                )
                ->where('attribute_id = ?', $attributeId)
                ->where('entity_id IN (?)', $productIds)
        );
        $values = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new RuntimeException('A product description row is not an array.');
            }

            $value = $row['value'] ?? null;

            if ($value === null) {
                continue;
            }

            if (!is_string($value)) {
                throw new RuntimeException('A product description value is not a string.');
            }

            $productId = $this->toInteger($row['entity_id'] ?? null, 'entity_id');
            $storeId = $this->toInteger($row['store_id'] ?? null, 'store_id');
            $values[$this->getValueKey($productId, $storeId)] = $value;
        }

        return $values;
    }

    /**
     * @param array<array-key, mixed> $assignments
     * @param array<string, string> $values
     * @return array<int, list<ScopedSource>>
     */
    private function buildScopedSources(array $assignments, array $values): array
    {
        $sources = [];

        foreach ($assignments as $assignment) {
            if (!is_array($assignment)) {
                throw new RuntimeException('A product store assignment is not an array.');
            }

            $productId = $this->toInteger($assignment['product_id'] ?? null, 'product_id');
            $storeId = $this->toInteger($assignment['store_id'] ?? null, 'store_id');
            $storeValueKey = $this->getValueKey($productId, $storeId);
            $defaultValueKey = $this->getValueKey($productId, self::DEFAULT_STORE_ID);
            $content = $values[$storeValueKey] ?? $values[$defaultValueKey] ?? '';
            $sources[$productId][] = new ScopedSource($storeId, $content);
        }

        return $sources;
    }

    /**
     * @param array<array-key, mixed> $values
     * @return list<int>
     */
    private function toIntegerList(array $values, string $field): array
    {
        $integers = [];

        foreach ($values as $value) {
            $integers[] = $this->toInteger($value, $field);
        }

        return $integers;
    }

    private function toInteger(mixed $value, string $field): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if ($integer === false || $integer < 0) {
            throw new RuntimeException(sprintf('Database field "%s" is not an unsigned integer.', $field));
        }

        return $integer;
    }

    private function getValueKey(int $productId, int $storeId): string
    {
        return $productId . ':' . $storeId;
    }
}
