<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\Product;

use InvalidArgumentException;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use RuntimeException;

class SourceProvider
{
    private const string PRODUCT_ENTITY_TYPE = 'catalog_product';
    // TODO Make this configurable via admin UI
    private const string SOURCE_CODE = 'description';
    private const int DEFAULT_STORE_ID = 0;

    public function __construct(
        private readonly CollectionFactory $collectionFactory
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

        $productResource = $this->createProductResource();
        $connection = $productResource->getConnection();
        $select = $connection->select()
            ->from(
                $productResource->getEntityTable(),
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

        $productResource = $this->createProductResource();
        $connection = $productResource->getConnection();
        $attributeId = $this->getDescriptionAttributeId($productResource, $connection);
        $values = $this->getDescriptionValues(
            $productIds,
            $attributeId,
            $productResource,
            $connection
        );
        $assignments = $connection->fetchAll(
            $connection->select()
                ->from(
                    ['assignment' => $productResource->getProductWebsiteTable()],
                    ['product_id']
                )
                ->join(
                    ['store' => $productResource->getTable('store')],
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

    private function createProductResource(): ProductResource
    {
        /** @var ProductResource $productResource */
        $productResource = $this->collectionFactory->create()->getEntity();

        return $productResource;
    }

    private function getDescriptionAttributeId(
        ProductResource $productResource,
        AdapterInterface $connection
    ): int {
        $attributeTable = $productResource->getTable('eav_attribute');
        $entityTypeTable = $productResource->getTable('eav_entity_type');
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
    private function getDescriptionValues(
        array $productIds,
        int $attributeId,
        ProductResource $productResource,
        AdapterInterface $connection
    ): array {
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    $productResource->getTable('catalog_product_entity_text'),
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
