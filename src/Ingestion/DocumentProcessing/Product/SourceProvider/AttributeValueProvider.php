<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider;

use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use RuntimeException;

class AttributeValueProvider
{
    private const string PRODUCT_ENTITY_TYPE = 'catalog_product';
    private const array SUPPORTED_BACKEND_TYPES = [
        'text' => true,
        'varchar' => true,
    ];

    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @param list<string> $sourceCodes
     * @param list<int> $productIds
     * @return array<string, array<string, string>>
     */
    public function getValuesBySourceCode(array $sourceCodes, array $productIds): array
    {
        if ($sourceCodes === [] || $productIds === []) {
            return [];
        }

        $productResource = $this->createProductResource();
        $connection = $productResource->getConnection();
        $attributesById = $this->getAttributesById(
            $sourceCodes,
            $productResource,
            $connection
        );
        $valuesBySourceCode = array_fill_keys($sourceCodes, []);

        foreach ($this->getAttributesByBackendType($attributesById) as $backendType => $attributes) {
            $valuesBySourceCode = [
                ...$valuesBySourceCode,
                ...$this->getValues(
                    $attributes,
                    $productIds,
                    $backendType,
                    $productResource,
                    $connection
                ),
            ];
        }

        return $valuesBySourceCode;
    }

    private function createProductResource(): ProductResource
    {
        /** @var ProductResource $productResource */
        $productResource = $this->collectionFactory->create()->getEntity();

        return $productResource;
    }

    /**
     * @param list<string> $sourceCodes
     * @return array<int, array{code: string, backend_type: string}>
     */
    private function getAttributesById(
        array $sourceCodes,
        ProductResource $productResource,
        AdapterInterface $connection
    ): array {
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    ['attribute' => $productResource->getTable('eav_attribute')],
                    ['attribute_id', 'attribute_code', 'backend_type']
                )
                ->join(
                    ['entity_type' => $productResource->getTable('eav_entity_type')],
                    'entity_type.entity_type_id = attribute.entity_type_id',
                    []
                )
                ->where('entity_type.entity_type_code = ?', self::PRODUCT_ENTITY_TYPE)
                ->where('attribute.attribute_code IN (?)', $sourceCodes)
        );
        $attributesById = [];
        $resolvedSourceCodes = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new RuntimeException('A product source attribute row is invalid.');
            }

            $attributeId = $this->toPositiveInteger($row['attribute_id'] ?? null, 'attribute_id');
            $sourceCode = $this->toNonEmptyString($row['attribute_code'] ?? null, 'attribute_code');
            $backendType = $this->toNonEmptyString($row['backend_type'] ?? null, 'backend_type');

            if (!isset(self::SUPPORTED_BACKEND_TYPES[$backendType])) {
                throw new RuntimeException('A product source attribute backend type is not supported.');
            }

            $attributesById[$attributeId] = [
                'code' => $sourceCode,
                'backend_type' => $backendType,
            ];
            $resolvedSourceCodes[$sourceCode] = true;
        }

        if (array_diff($sourceCodes, array_keys($resolvedSourceCodes)) !== []) {
            throw new RuntimeException('One or more product source attributes could not be resolved.');
        }

        return $attributesById;
    }

    /**
     * @param array<int, array{code: string, backend_type: string}> $attributesById
     * @return array<string, array<int, string>>
     */
    private function getAttributesByBackendType(array $attributesById): array
    {
        $attributesByBackendType = [];

        foreach ($attributesById as $attributeId => $attribute) {
            $attributesByBackendType[$attribute['backend_type']][$attributeId] = $attribute['code'];
        }

        return $attributesByBackendType;
    }

    /**
     * @param array<int, string> $attributes
     * @param list<int> $productIds
     * @return array<string, array<string, string>>
     */
    private function getValues(
        array $attributes,
        array $productIds,
        string $backendType,
        ProductResource $productResource,
        AdapterInterface $connection
    ): array {
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    $productResource->getTable('catalog_product_entity_' . $backendType),
                    ['attribute_id', 'entity_id', 'store_id', 'value']
                )
                ->where('attribute_id IN (?)', array_keys($attributes))
                ->where('entity_id IN (?)', $productIds)
        );
        $valuesBySourceCode = [];

        foreach ($rows as $row) {
            $valueEntry = $this->getValueEntry($row, $attributes);

            if ($valueEntry === null) {
                continue;
            }

            $valuesBySourceCode[$valueEntry['source_code']][$valueEntry['key']] = $valueEntry['value'];
        }

        return $valuesBySourceCode;
    }

    /**
     * @param array<int, string> $attributes
     * @return array{source_code: string, key: string, value: string}|null
     */
    private function getValueEntry(mixed $row, array $attributes): ?array
    {
        if (!is_array($row)) {
            throw new RuntimeException('A product source value row is invalid.');
        }

        $value = $row['value'] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new RuntimeException('A product source value is not a string.');
        }

        $attributeId = $this->toPositiveInteger($row['attribute_id'] ?? null, 'attribute_id');
        $sourceCode = $attributes[$attributeId] ?? null;

        if ($sourceCode === null) {
            throw new RuntimeException('A product source attribute is unknown.');
        }

        $productId = $this->toPositiveInteger($row['entity_id'] ?? null, 'entity_id');
        $storeId = $this->toUnsignedInteger($row['store_id'] ?? null, 'store_id');

        return [
            'source_code' => $sourceCode,
            'key' => $this->getValueKey($productId, $storeId),
            'value' => $value,
        ];
    }

    private function getValueKey(int $productId, int $storeId): string
    {
        return $productId . ':' . $storeId;
    }

    private function toNonEmptyString(mixed $value, string $field): string
    {
        if (!is_string($value) || $value === '') {
            throw new RuntimeException(sprintf('Database field "%s" is not a non-empty string.', $field));
        }

        return $value;
    }

    private function toPositiveInteger(mixed $value, string $field): int
    {
        $integer = $this->toUnsignedInteger($value, $field);

        if ($integer < 1) {
            throw new RuntimeException(sprintf('Database field "%s" is not a positive integer.', $field));
        }

        return $integer;
    }

    private function toUnsignedInteger(mixed $value, string $field): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if ($integer === false || $integer < 0) {
            throw new RuntimeException(sprintf('Database field "%s" is not an unsigned integer.', $field));
        }

        return $integer;
    }
}
