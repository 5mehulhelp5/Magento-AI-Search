<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\Product;

use DavidBel\AiSearch\Config\EmbeddedAttributesConfig;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\AttributeValueProvider;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\DirectSourceBuilder;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\Eligibility;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\EmbeddingTemplate;
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
        private readonly DirectSourceBuilder $directSourceBuilder,
        private readonly EmbeddingTemplate $embeddingTemplate
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
     * @return array<int, list<\DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentSource>>
     */
    public function getSourcesByProductIds(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $eligibleScopes = $this->eligibility->getEligibleScopesByProductIds($productIds);
        $embeddedAttributes = $this->embeddedAttributesConfig->getAttributes();
        $directAttributes = $this->getDirectAttributes($embeddedAttributes);
        $titleAttributeCode = $this->getDocumentTitleAttributeCode();
        $valuesBySourceCode = $this->attributeValueProvider->getValuesBySourceCode(
            $this->getRequiredAttributeCodes($directAttributes, $titleAttributeCode),
            $this->getSourceProductIds($eligibleScopes)
        );
        $titleValues = $titleAttributeCode === null
            ? []
            : ($valuesBySourceCode[$titleAttributeCode] ?? []);
        $directSources = $this->directSourceBuilder->buildSourcesByProductId(
            $directAttributes,
            $productIds,
            $eligibleScopes,
            $valuesBySourceCode,
            $titleValues
        );
        $templateSources = $this->embeddingTemplate->buildSourcesByProductId(
            $this->getDynamicDocumentsByStoreId($eligibleScopes),
            $productIds,
            $eligibleScopes,
            $titleValues
        );

        return $this->mergeSources($productIds, $directSources, $templateSources);
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
     * @param array<int, list<SourceProvider\Eligibility\EligibleScope>> $eligibleScopes
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
     * @param array<int, list<SourceProvider\Eligibility\EligibleScope>> $eligibleScopes
     * @return array<int, \DavidBel\AiSearch\Config\EmbeddedAttribute>
     */
    private function getDynamicDocumentsByStoreId(array $eligibleScopes): array
    {
        $storeIds = [];

        foreach ($eligibleScopes as $productScopes) {
            foreach ($productScopes as $productScope) {
                $storeIds[$productScope->storeId] = true;
            }
        }

        $dynamicDocuments = [];

        foreach (array_keys($storeIds) as $storeId) {
            $dynamicDocument = $this->embeddedAttributesConfig->getDynamicDocument($storeId);

            if ($dynamicDocument === null) {
                continue;
            }

            $dynamicDocuments[$storeId] = $dynamicDocument;
        }

        return $dynamicDocuments;
    }

    /**
     * @param list<\DavidBel\AiSearch\Config\EmbeddedAttribute> $embeddedAttributes
     * @return list<string>
     */
    private function getRequiredAttributeCodes(
        array $embeddedAttributes,
        ?string $titleAttributeCode
    ): array {
        $attributeCodes = $titleAttributeCode === null ? [] : [$titleAttributeCode => true];

        foreach ($embeddedAttributes as $embeddedAttribute) {
            $attributeCodes[$embeddedAttribute->attributeCode] = true;
        }

        return array_keys($attributeCodes);
    }

    private function getDocumentTitleAttributeCode(): ?string
    {
        if (!$this->embeddedAttributesConfig->isDocumentTitleEnabled()) {
            return null;
        }

        return $this->embeddedAttributesConfig->getDocumentTitleAttributeCode();
    }

    /**
     * @param list<\DavidBel\AiSearch\Config\EmbeddedAttribute> $embeddedAttributes
     * @return list<\DavidBel\AiSearch\Config\EmbeddedAttribute>
     */
    private function getDirectAttributes(array $embeddedAttributes): array
    {
        $directAttributes = [];

        foreach ($embeddedAttributes as $embeddedAttribute) {
            if ($embeddedAttribute->children !== null) {
                continue;
            }

            $directAttributes[] = $embeddedAttribute;
        }

        return $directAttributes;
    }

    /**
     * @param list<int> $productIds
     * @param array<int, list<\DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentSource>> $directSources
     * @param array<int, list<\DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentSource>> $templateSources
     * @return array<int, list<\DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentSource>>
     */
    private function mergeSources(
        array $productIds,
        array $directSources,
        array $templateSources
    ): array {
        $sourcesByProductId = [];

        foreach ($productIds as $productId) {
            $sourcesByProductId[$productId] = [
                ...($directSources[$productId] ?? []),
                ...($templateSources[$productId] ?? []),
            ];
        }

        return $sourcesByProductId;
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
