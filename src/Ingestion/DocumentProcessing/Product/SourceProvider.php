<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\Product;

use DavidBel\AiSearch\Config\EmbeddedAttribute;
use DavidBel\AiSearch\Config\EmbeddedAttributesConfig;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\Source\AttributeValueProvider;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\Source\SourceComposer;
use InvalidArgumentException;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use RuntimeException;

class SourceProvider
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly Eligibility $eligibility,
        private readonly EmbeddedAttributesConfig $embeddedAttributesConfig,
        private readonly AttributeValueProvider $attributeValueProvider,
        private readonly SourceComposer $sourceComposer
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
     * @return array<int, list<ProductSource>>
     */
    public function getSourcesByProductIds(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $eligibleScopes = $this->eligibility->getEligibleScopesByProductIds($productIds);
        $embeddedAttributes = $this->embeddedAttributesConfig->getAttributes();
        $valuesBySourceCode = $this->attributeValueProvider->getValuesBySourceCode(
            $this->getAttributeCodes($embeddedAttributes),
            $this->getSourceProductIds($eligibleScopes)
        );

        return $this->sourceComposer->compose(
            $embeddedAttributes,
            $productIds,
            $eligibleScopes,
            $valuesBySourceCode
        );
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

    /**
     * @param array<int, list<Eligibility\EligibleScope>> $eligibleScopes
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
     * @param list<EmbeddedAttribute> $embeddedAttributes
     * @return list<string>
     */
    private function getAttributeCodes(array $embeddedAttributes): array
    {
        $attributeCodes = [];

        foreach ($embeddedAttributes as $embeddedAttribute) {
            $attributeCodes[] = $embeddedAttribute->attributeCode;
        }

        return $attributeCodes;
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
}
