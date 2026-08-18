<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer\ProductAttributeOptionIndexer;

use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use RuntimeException;

class AttributeOptionFilterProvider
{
    private const string PRODUCT_ENTITY_TYPE = 'catalog_product';
    private const array SUPPORTED_BACKEND_TYPES = [
        'int' => true,
        'text' => true,
        'varchar' => true,
    ];
    private const array SUPPORTED_FRONTEND_INPUTS = [
        'multiselect' => true,
        'select' => true,
    ];

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly DynamicDocumentAttributeCodeProvider $attributeCodeProvider
    ) {
    }

    /**
     * @param list<int> $optionIds
     * @return array<string, array<int, array{frontend_input: string, option_ids: list<int>}>>
     */
    public function getByBackendType(array $optionIds): array
    {
        $attributeCodes = $this->attributeCodeProvider->getAttributeCodes();

        if ($attributeCodes === []) {
            return [];
        }

        $productResource = $this->createProductResource();
        $rows = $productResource->getConnection()->fetchAll(
            $productResource->getConnection()->select()
                ->from(
                    ['option' => $productResource->getTable('eav_attribute_option')],
                    ['option_id']
                )
                ->join(
                    ['attribute' => $productResource->getTable('eav_attribute')],
                    'attribute.attribute_id = option.attribute_id',
                    ['attribute_id', 'backend_type', 'frontend_input']
                )
                ->join(
                    ['entity_type' => $productResource->getTable('eav_entity_type')],
                    'entity_type.entity_type_id = attribute.entity_type_id',
                    []
                )
                ->where('option.option_id IN (?)', $optionIds)
                ->where('entity_type.entity_type_code = ?', self::PRODUCT_ENTITY_TYPE)
                ->where('attribute.attribute_code IN (?)', $attributeCodes)
                ->where('attribute.frontend_input IN (?)', array_keys(self::SUPPORTED_FRONTEND_INPUTS))
        );
        $filtersByBackendType = [];

        foreach ($rows as $row) {
            $optionFilter = $this->getOptionFilterFromRow($row);

            if ($optionFilter === null) {
                continue;
            }

            $filtersByBackendType = $this->addOptionFilter(
                $filtersByBackendType,
                $optionFilter
            );
        }

        return $this->normalizeOptionFilters($filtersByBackendType);
    }

    private function createProductResource(): ProductResource
    {
        /** @var ProductResource $productResource */
        $productResource = $this->collectionFactory->create()->getEntity();

        return $productResource;
    }

    /**
     * @return array{backend_type: string, attribute_id: int, frontend_input: string, option_id: int}|null
     */
    private function getOptionFilterFromRow(mixed $row): ?array
    {
        if (!is_array($row)) {
            throw new RuntimeException('A product attribute option row is invalid.');
        }

        $backendType = $this->toNonEmptyString($row['backend_type'] ?? null, 'backend_type');
        $frontendInput = $this->toNonEmptyString($row['frontend_input'] ?? null, 'frontend_input');

        if (!isset(self::SUPPORTED_BACKEND_TYPES[$backendType])) {
            return null;
        }

        if (!isset(self::SUPPORTED_FRONTEND_INPUTS[$frontendInput])) {
            return null;
        }

        return [
            'attribute_id' => $this->toPositiveInteger($row['attribute_id'] ?? null, 'attribute_id'),
            'backend_type' => $backendType,
            'frontend_input' => $frontendInput,
            'option_id' => $this->toPositiveInteger($row['option_id'] ?? null, 'option_id'),
        ];
    }

    /**
     * @param array<string, array<int, array{frontend_input: string, option_ids: array<int, int>}>> $filters
     * @param array{backend_type: string, attribute_id: int, frontend_input: string, option_id: int} $optionFilter
     * @return array<string, array<int, array{frontend_input: string, option_ids: array<int, int>}>>
     */
    private function addOptionFilter(array $filters, array $optionFilter): array
    {
        $backendType = $optionFilter['backend_type'];
        $attributeId = $optionFilter['attribute_id'];
        $frontendInput = $optionFilter['frontend_input'];
        $optionId = $optionFilter['option_id'];
        $existingFilter = $filters[$backendType][$attributeId] ?? null;

        if ($existingFilter !== null && $existingFilter['frontend_input'] !== $frontendInput) {
            throw new RuntimeException(
                'A product attribute input type changed while option changes were processed.'
            );
        }

        $filters[$backendType][$attributeId]['frontend_input'] = $frontendInput;
        $filters[$backendType][$attributeId]['option_ids'][$optionId] = $optionId;

        return $filters;
    }

    /**
     * @param array<string, array<int, array{frontend_input: string, option_ids: array<int, int>}>> $filters
     * @return array<string, array<int, array{frontend_input: string, option_ids: list<int>}>>
     */
    private function normalizeOptionFilters(array $filters): array
    {
        $normalizedFilters = [];
        ksort($filters);

        foreach ($filters as $backendType => $attributeFilters) {
            ksort($attributeFilters, SORT_NUMERIC);

            foreach ($attributeFilters as $attributeId => $attributeFilter) {
                $optionIds = array_values($attributeFilter['option_ids']);
                sort($optionIds, SORT_NUMERIC);
                $normalizedFilters[$backendType][$attributeId] = [
                    'frontend_input' => $attributeFilter['frontend_input'],
                    'option_ids' => $optionIds,
                ];
            }
        }

        return $normalizedFilters;
    }

    private function toPositiveInteger(mixed $value, string $field): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if ($integer === false || $integer < 1) {
            throw new RuntimeException(sprintf('Database field "%s" is not a positive integer.', $field));
        }

        return $integer;
    }

    private function toNonEmptyString(mixed $value, string $field): string
    {
        if (!is_string($value) || $value === '') {
            throw new RuntimeException(sprintf('Database field "%s" is not a non-empty string.', $field));
        }

        return $value;
    }
}
