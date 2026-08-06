<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\EmbeddingTemplate;

use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use RuntimeException;

class AttributeDataProvider
{
    private const string PRODUCT_ENTITY_TYPE = 'catalog_product';
    private const int DEFAULT_STORE_ID = 0;
    private const array SUPPORTED_BACKEND_TYPES = [
        'int' => true,
        'text' => true,
        'varchar' => true,
    ];
    private const array SUPPORTED_FRONTEND_INPUTS = [
        'multiselect' => true,
        'select' => true,
        'text' => true,
        'textarea' => true,
    ];
    private const array OPTION_FRONTEND_INPUTS = [
        'multiselect' => true,
        'select' => true,
    ];

    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @param list<string> $attributeCodes
     * @param list<int> $productIds
     * @param list<int> $storeIds
     */
    public function get(
        array $attributeCodes,
        array $productIds,
        array $storeIds
    ): AttributeData {
        $productResource = $this->createProductResource();
        $connection = $productResource->getConnection();
        $attributesById = $this->getAttributesById(
            $attributeCodes,
            $productResource,
            $connection
        );

        return new AttributeData(
            $attributesById,
            $this->getRawValues(
                $attributesById,
                $productIds,
                $storeIds,
                $productResource,
                $connection
            ),
            $this->getOptionLabels(
                $this->getOptionAttributeIds($attributesById),
                $storeIds,
                $productResource,
                $connection
            )
        );
    }

    private function createProductResource(): ProductResource
    {
        /** @var ProductResource $productResource */
        $productResource = $this->collectionFactory->create()->getEntity();

        return $productResource;
    }

    /**
     * @param list<string> $attributeCodes
     * @return array<int, array{code: string, backend_type: string, frontend_input: string}>
     */
    private function getAttributesById(
        array $attributeCodes,
        ProductResource $productResource,
        AdapterInterface $connection
    ): array {
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    ['attribute' => $productResource->getTable('eav_attribute')],
                    ['attribute_id', 'attribute_code', 'backend_type', 'frontend_input']
                )
                ->join(
                    ['entity_type' => $productResource->getTable('eav_entity_type')],
                    'entity_type.entity_type_id = attribute.entity_type_id',
                    []
                )
                ->where('entity_type.entity_type_code = ?', self::PRODUCT_ENTITY_TYPE)
                ->where('attribute.attribute_code IN (?)', $attributeCodes)
        );
        $attributesById = [];
        $resolvedAttributeCodes = [];

        foreach ($rows as $row) {
            $attribute = $this->getAttribute($row);
            $attributesById[$attribute['attribute_id']] = [
                'code' => $attribute['code'],
                'backend_type' => $attribute['backend_type'],
                'frontend_input' => $attribute['frontend_input'],
            ];
            $resolvedAttributeCodes[$attribute['code']] = true;
        }

        if (array_diff($attributeCodes, array_keys($resolvedAttributeCodes)) !== []) {
            throw new RuntimeException('One or more embedding template attributes could not be resolved.');
        }

        return $attributesById;
    }

    /**
     * @return array{attribute_id: int, code: string, backend_type: string, frontend_input: string}
     */
    private function getAttribute(mixed $row): array
    {
        if (!is_array($row)) {
            throw new RuntimeException('An embedding template attribute row is invalid.');
        }

        $backendType = $this->toNonEmptyString($row['backend_type'] ?? null, 'backend_type');
        $frontendInput = $this->toNonEmptyString($row['frontend_input'] ?? null, 'frontend_input');

        if (!isset(self::SUPPORTED_BACKEND_TYPES[$backendType])) {
            throw new RuntimeException('An embedding template attribute backend type is not supported.');
        }

        if (!isset(self::SUPPORTED_FRONTEND_INPUTS[$frontendInput])) {
            throw new RuntimeException('An embedding template attribute input type is not supported.');
        }

        return [
            'attribute_id' => $this->toPositiveInteger($row['attribute_id'] ?? null, 'attribute_id'),
            'code' => $this->toNonEmptyString($row['attribute_code'] ?? null, 'attribute_code'),
            'backend_type' => $backendType,
            'frontend_input' => $frontendInput,
        ];
    }

    /**
     * @param array<int, array{code: string, backend_type: string, frontend_input: string}> $attributesById
     * @param list<int> $productIds
     * @param list<int> $storeIds
     * @return array<string, array<string, string>>
     */
    private function getRawValues(
        array $attributesById,
        array $productIds,
        array $storeIds,
        ProductResource $productResource,
        AdapterInterface $connection
    ): array {
        $rawValues = [];

        foreach ($this->getAttributeIdsByBackendType($attributesById) as $backendType => $attributeIds) {
            $rows = $connection->fetchAll(
                $connection->select()
                    ->from(
                        ['attribute_value' => $productResource->getTable(
                            'catalog_product_entity_' . $backendType
                        )],
                        ['attribute_id', 'store_id', 'value']
                    )
                    ->join(
                        ['product' => $productResource->getEntityTable()],
                        sprintf(
                            'product.%1$s = attribute_value.%1$s',
                            $productResource->getLinkField()
                        ),
                        ['entity_id']
                    )
                    ->where('attribute_value.attribute_id IN (?)', $attributeIds)
                    ->where('product.entity_id IN (?)', $productIds)
                    ->where('attribute_value.store_id IN (?)', $this->getScopedStoreIds($storeIds))
            );

            foreach ($rows as $row) {
                $value = $this->getRawValue($row, $attributesById);

                if ($value === null) {
                    continue;
                }

                $rawValues[$value['attribute_code']][$value['key']] = $value['value'];
            }
        }

        return $rawValues;
    }

    /**
     * @param list<int> $storeIds
     * @return list<int>
     */
    private function getScopedStoreIds(array $storeIds): array
    {
        $storeIds[] = self::DEFAULT_STORE_ID;

        return array_values(array_unique($storeIds));
    }

    /**
     * @param array<int, array{code: string, backend_type: string, frontend_input: string}> $attributesById
     * @return array<string, list<int>>
     */
    private function getAttributeIdsByBackendType(array $attributesById): array
    {
        $attributeIdsByBackendType = [];

        foreach ($attributesById as $attributeId => $attribute) {
            $attributeIdsByBackendType[$attribute['backend_type']][] = $attributeId;
        }

        return $attributeIdsByBackendType;
    }

    /**
     * @param array<int, array{code: string, backend_type: string, frontend_input: string}> $attributesById
     * @return array{attribute_code: string, key: string, value: string}|null
     */
    private function getRawValue(mixed $row, array $attributesById): ?array
    {
        if (!is_array($row)) {
            throw new RuntimeException('An embedding template attribute value row is invalid.');
        }

        $rawValue = $row['value'] ?? null;

        if ($rawValue === null) {
            return null;
        }

        $attributeId = $this->toPositiveInteger($row['attribute_id'] ?? null, 'attribute_id');
        $attribute = $attributesById[$attributeId] ?? null;

        if ($attribute === null) {
            throw new RuntimeException('An embedding template attribute is unknown.');
        }

        $productId = $this->toPositiveInteger($row['entity_id'] ?? null, 'entity_id');
        $storeId = $this->toUnsignedInteger($row['store_id'] ?? null, 'store_id');

        return [
            'attribute_code' => $attribute['code'],
            'key' => $this->getValueKey($productId, $storeId),
            'value' => $this->toScalarString($rawValue, 'value'),
        ];
    }

    /**
     * @param array<int, array{code: string, backend_type: string, frontend_input: string}> $attributesById
     * @return list<int>
     */
    private function getOptionAttributeIds(array $attributesById): array
    {
        $attributeIds = [];

        foreach ($attributesById as $attributeId => $attribute) {
            if (!isset(self::OPTION_FRONTEND_INPUTS[$attribute['frontend_input']])) {
                continue;
            }

            $attributeIds[] = $attributeId;
        }

        return $attributeIds;
    }

    /**
     * @param list<int> $attributeIds
     * @param list<int> $storeIds
     * @return array<int, array<int, string>>
     */
    private function getOptionLabels(
        array $attributeIds,
        array $storeIds,
        ProductResource $productResource,
        AdapterInterface $connection
    ): array {
        if ($attributeIds === []) {
            return [];
        }

        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    ['option' => $productResource->getTable('eav_attribute_option')],
                    []
                )
                ->join(
                    ['label' => $productResource->getTable('eav_attribute_option_value')],
                    'label.option_id = option.option_id',
                    ['option_id', 'store_id', 'value']
                )
                ->where('option.attribute_id IN (?)', $attributeIds)
                ->where('label.store_id IN (?)', $this->getScopedStoreIds($storeIds))
        );
        $labels = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new RuntimeException('An embedding template option label row is invalid.');
            }

            $optionId = $this->toPositiveInteger($row['option_id'] ?? null, 'option_id');
            $storeId = $this->toUnsignedInteger($row['store_id'] ?? null, 'store_id');
            $labels[$optionId][$storeId] = $this->toNonEmptyString($row['value'] ?? null, 'value');
        }

        return $labels;
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

    private function toScalarString(mixed $value, string $field): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        throw new RuntimeException(sprintf('Database field "%s" is not a string or integer.', $field));
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
