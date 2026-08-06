<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\Product;

use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\Eligibility\EligibleScope;
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
        private readonly CollectionFactory $collectionFactory,
        private readonly Eligibility $eligibility
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
    public function getSourcesByProductIds(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $eligibleScopes = $this->eligibility->getEligibleScopesByProductIds($productIds);

        if ($eligibleScopes === []) {
            return [];
        }

        $productResource = $this->createProductResource();
        $connection = $productResource->getConnection();
        $attributeId = $this->getDescriptionAttributeId($productResource, $connection);
        $values = $this->getDescriptionValues(
            $this->getSourceProductIds($eligibleScopes),
            $attributeId,
            $productResource,
            $connection
        );

        return $this->buildScopedSources($eligibleScopes, $values);
    }

    /**
     * @param list<int> $productIds
     * @return list<int>
     */
    public function getAffectedProductIds(array $productIds): array
    {
        return $this->eligibility->getAffectedProductIds($productIds);
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
     * @param array<int, list<EligibleScope>> $eligibleScopes
     * @return list<int>
     */
    private function getSourceProductIds(array $eligibleScopes): array
    {
        $sourceProductIds = [];

        foreach ($eligibleScopes as $productScopes) {
            foreach ($productScopes as $productScope) {
                $sourceProductIds += array_fill_keys($productScope->sourceProductIds, true);
            }
        }

        return array_keys($sourceProductIds);
    }

    /**
     * @param array<int, list<EligibleScope>> $eligibleScopes
     * @param array<string, string> $values
     * @return array<int, list<ScopedSource>>
     */
    private function buildScopedSources(array $eligibleScopes, array $values): array
    {
        $sources = [];

        foreach ($eligibleScopes as $productId => $productScopes) {
            foreach ($productScopes as $productScope) {
                $sources[$productId][] = new ScopedSource(
                    $productScope->storeId,
                    $this->getScopeContent($productScope, $values)
                );
            }
        }

        return $sources;
    }

    /**
     * @param array<string, string> $values
     */
    private function getScopeContent(EligibleScope $eligibleScope, array $values): string
    {
        $contentsByHash = [];
        $contents = [];

        foreach ($eligibleScope->sourceProductIds as $sourceProductId) {
            $content = $this->getStoreValue(
                $values,
                $sourceProductId,
                $eligibleScope->storeId
            );

            if ($content === '' || $this->hasContent($contentsByHash, $content)) {
                continue;
            }

            $contentsByHash[hash('sha256', $content)][] = $content;
            $contents[] = $content;
        }

        return implode("\n\n", $contents);
    }

    /**
     * @param array<string, string> $values
     */
    private function getStoreValue(array $values, int $productId, int $storeId): string
    {
        return $values[$this->getValueKey($productId, $storeId)]
            ?? $values[$this->getValueKey($productId, self::DEFAULT_STORE_ID)]
            ?? '';
    }

    /**
     * @param array<string, list<string>> $contentsByHash
     */
    private function hasContent(array $contentsByHash, string $content): bool
    {
        $matchingContents = $contentsByHash[hash('sha256', $content)] ?? [];

        return in_array($content, $matchingContents, true);
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
