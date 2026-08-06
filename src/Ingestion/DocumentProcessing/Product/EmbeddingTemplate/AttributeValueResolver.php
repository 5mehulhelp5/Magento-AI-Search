<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\EmbeddingTemplate;

use RuntimeException;

class AttributeValueResolver
{
    private const int DEFAULT_STORE_ID = 0;
    private const array OPTION_FRONTEND_INPUTS = [
        'multiselect' => true,
        'select' => true,
    ];

    public function __construct(
        private readonly AttributeDataProvider $attributeDataProvider
    ) {
    }

    /**
     * @param list<string> $attributeCodes
     * @param list<int> $productIds
     * @param list<int> $storeIds
     * @return array<string, array<string, list<string>>>
     */
    public function getValuesByAttributeCode(
        array $attributeCodes,
        array $productIds,
        array $storeIds
    ): array {
        if ($attributeCodes === [] || $productIds === [] || $storeIds === []) {
            return [];
        }

        $attributeData = $this->attributeDataProvider->get(
            $attributeCodes,
            $productIds,
            $storeIds
        );

        return $this->resolveValues(
            $attributeCodes,
            $attributeData,
            $productIds,
            $storeIds
        );
    }

    /**
     * @param list<string> $attributeCodes
     * @param list<int> $productIds
     * @param list<int> $storeIds
     * @return array<string, array<string, list<string>>>
     */
    private function resolveValues(
        array $attributeCodes,
        AttributeData $attributeData,
        array $productIds,
        array $storeIds
    ): array {
        $valuesByAttributeCode = array_fill_keys($attributeCodes, []);

        foreach ($attributeData->attributesById as $attribute) {
            $valuesByAttributeCode[$attribute['code']] = $this->resolveAttributeValues(
                $attribute,
                $attributeData,
                $productIds,
                $storeIds
            );
        }

        return $valuesByAttributeCode;
    }

    /**
     * @param array{code: string, backend_type: string, frontend_input: string} $attribute
     * @param list<int> $productIds
     * @param list<int> $storeIds
     * @return array<string, list<string>>
     */
    private function resolveAttributeValues(
        array $attribute,
        AttributeData $attributeData,
        array $productIds,
        array $storeIds
    ): array {
        $resolvedValues = [];

        foreach ($productIds as $productId) {
            $resolvedValues += $this->resolveProductValues(
                $attribute,
                $attributeData,
                $productId,
                $storeIds
            );
        }

        return $resolvedValues;
    }

    /**
     * @param array{code: string, backend_type: string, frontend_input: string} $attribute
     * @param list<int> $storeIds
     * @return array<string, list<string>>
     */
    private function resolveProductValues(
        array $attribute,
        AttributeData $attributeData,
        int $productId,
        array $storeIds
    ): array {
        $resolvedValues = [];
        $rawValues = $attributeData->rawValues[$attribute['code']] ?? [];

        foreach ($storeIds as $storeId) {
            $rawValue = $this->getScopedRawValue($rawValues, $productId, $storeId);

            if ($rawValue === null || $rawValue === '') {
                continue;
            }

            $values = $this->resolveValue(
                $rawValue,
                $storeId,
                $attribute,
                $attributeData->optionLabels
            );

            if ($values === []) {
                continue;
            }

            $resolvedValues[$this->getValueKey($productId, $storeId)] = $values;
        }

        return $resolvedValues;
    }

    /**
     * @param array<string, string> $attributeValues
     */
    private function getScopedRawValue(array $attributeValues, int $productId, int $storeId): ?string
    {
        $storeKey = $this->getValueKey($productId, $storeId);

        if (array_key_exists($storeKey, $attributeValues)) {
            return $attributeValues[$storeKey];
        }

        return $attributeValues[$this->getValueKey($productId, self::DEFAULT_STORE_ID)] ?? null;
    }

    /**
     * @param array{code: string, backend_type: string, frontend_input: string} $attribute
     * @param array<int, array<int, string>> $optionLabels
     * @return list<string>
     */
    private function resolveValue(
        string $rawValue,
        int $storeId,
        array $attribute,
        array $optionLabels
    ): array {
        if (!isset(self::OPTION_FRONTEND_INPUTS[$attribute['frontend_input']])) {
            return [$rawValue];
        }

        $labels = [];

        foreach ($this->getOptionIdsFromValue($rawValue) as $optionId) {
            $label = $optionLabels[$optionId][$storeId]
                ?? $optionLabels[$optionId][self::DEFAULT_STORE_ID]
                ?? null;

            if ($label === null) {
                throw new RuntimeException('An embedding template option label could not be resolved.');
            }

            $labels[] = $label;
        }

        return $labels;
    }

    /**
     * @return list<int>
     */
    private function getOptionIdsFromValue(string $rawValue): array
    {
        $optionIds = [];

        foreach (explode(',', $rawValue) as $value) {
            $value = trim($value);

            if ($value === '') {
                continue;
            }

            $optionId = $this->toUnsignedInteger($value, 'option_id');

            if ($optionId === 0) {
                continue;
            }

            $optionIds[] = $optionId;
        }

        return $optionIds;
    }

    private function getValueKey(int $productId, int $storeId): string
    {
        return $productId . ':' . $storeId;
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
