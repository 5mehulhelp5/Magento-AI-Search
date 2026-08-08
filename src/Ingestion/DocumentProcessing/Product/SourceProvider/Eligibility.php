<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider;

use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\Eligibility\DataProvider;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\Eligibility\EligibleScope;
use Magento\Bundle\Model\Product\Type as BundleProductType;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use RuntimeException;

class Eligibility
{
    private const array COMPOSITE_PRODUCT_TYPES = [
        BundleProductType::TYPE_CODE => true,
        Configurable::TYPE_CODE => true,
        Grouped::TYPE_CODE => true,
    ];

    public function __construct(
        private readonly DataProvider $dataProvider
    ) {
    }

    /**
     * @param list<int> $productIds
     * @return array<int, list<EligibleScope>>
     */
    public function getEligibleScopesByProductIds(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $scopeRows = $this->dataProvider->getEligibleScopeRows($productIds);

        if ($scopeRows === []) {
            return [];
        }

        $childIdsByParentId = $this->dataProvider->getChildIdsByParentId($productIds);
        $enabledChildIdsByStoreId = $this->dataProvider->getEnabledChildIdsByStoreId(
            $scopeRows,
            $childIdsByParentId
        );

        return $this->buildEligibleScopes(
            $scopeRows,
            $childIdsByParentId,
            $enabledChildIdsByStoreId
        );
    }

    /**
     * @param list<int> $productIds
     * @return list<int>
     */
    public function getAffectedProductIds(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $affectedProductIds = array_fill_keys($productIds, true);
        $affectedProductIds += array_fill_keys(
            $this->dataProvider->getParentIdsByChildIds($productIds),
            true
        );
        $result = array_keys($affectedProductIds);
        sort($result, SORT_NUMERIC);

        return $result;
    }

    /**
     * @param list<array<array-key, mixed>> $scopeRows
     * @param array<int, list<int>> $childIdsByParentId
     * @param array<int, array<int, true>> $enabledChildIdsByStoreId
     * @return array<int, list<EligibleScope>>
     */
    private function buildEligibleScopes(
        array $scopeRows,
        array $childIdsByParentId,
        array $enabledChildIdsByStoreId
    ): array {
        $eligibleScopes = [];

        foreach ($scopeRows as $scopeRow) {
            $productId = $this->toPositiveInteger($scopeRow['product_id'] ?? null, 'product_id');
            $storeId = $this->toPositiveInteger($scopeRow['store_id'] ?? null, 'store_id');
            $typeId = $scopeRow['type_id'] ?? null;

            if (!is_string($typeId)) {
                throw new RuntimeException('A product type is invalid.');
            }

            $sourceProductIds = $this->getSourceProductIds(
                $productId,
                $storeId,
                $typeId,
                $childIdsByParentId,
                $enabledChildIdsByStoreId
            );

            if ($sourceProductIds === []) {
                continue;
            }

            $eligibleScopes[$productId][] = new EligibleScope(
                $storeId,
                $sourceProductIds
            );
        }

        return $eligibleScopes;
    }

    /**
     * @param array<int, list<int>> $childIdsByParentId
     * @param array<int, array<int, true>> $enabledChildIdsByStoreId
     * @return list<int>
     */
    private function getSourceProductIds(
        int $productId,
        int $storeId,
        string $typeId,
        array $childIdsByParentId,
        array $enabledChildIdsByStoreId
    ): array {
        if (!isset(self::COMPOSITE_PRODUCT_TYPES[$typeId])) {
            return [$productId];
        }

        $childIds = $childIdsByParentId[$productId] ?? [];
        $enabledChildIds = $enabledChildIdsByStoreId[$storeId] ?? [];
        $eligibleChildIds = array_values(
            array_filter(
                $childIds,
                static fn(int $childId): bool => isset($enabledChildIds[$childId])
            )
        );

        if ($eligibleChildIds === []) {
            return [];
        }

        return [$productId, ...$eligibleChildIds];
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
