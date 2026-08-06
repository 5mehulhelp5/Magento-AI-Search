<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\Product;

use DavidBel\AiSearch\Config\EmbeddedAttribute;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\EmbeddingTemplate\AttributeValueResolver;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\EmbeddingTemplate\TemplateRenderer;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\Eligibility\EligibleScope;

class EmbeddingTemplate
{
    public function __construct(
        private readonly AttributeValueResolver $attributeValueResolver,
        private readonly TemplateRenderer $templateRenderer
    ) {
    }

    /**
     * @param list<EmbeddedAttribute> $embeddedAttributes
     * @param list<int> $productIds
     * @param array<int, list<EligibleScope>> $eligibleScopes
     * @return array<int, list<ProductSource>>
     */
    public function buildSourcesByProductId(
        array $embeddedAttributes,
        array $productIds,
        array $eligibleScopes
    ): array {
        $embeddingTemplates = $this->getEmbeddingTemplates($embeddedAttributes);
        $valuesByAttributeCode = $this->attributeValueResolver->getValuesByAttributeCode(
            $this->templateRenderer->getAttributeCodes($embeddingTemplates),
            $this->getSourceProductIds($eligibleScopes),
            $this->getStoreIds($eligibleScopes)
        );
        $sourcesByProductId = [];

        foreach ($productIds as $productId) {
            $sourcesByProductId[$productId] = $this->buildProductSources(
                $embeddingTemplates,
                $productId,
                $eligibleScopes[$productId] ?? [],
                $valuesByAttributeCode
            );
        }

        return $sourcesByProductId;
    }

    /**
     * @param list<EmbeddedAttribute> $embeddedAttributes
     * @return list<EmbeddedAttribute>
     */
    private function getEmbeddingTemplates(array $embeddedAttributes): array
    {
        $embeddingTemplates = [];

        foreach ($embeddedAttributes as $embeddedAttribute) {
            if ($embeddedAttribute->children === null) {
                continue;
            }

            $embeddingTemplates[] = $embeddedAttribute;
        }

        return $embeddingTemplates;
    }

    /**
     * @param list<EmbeddedAttribute> $embeddingTemplates
     * @param list<EligibleScope> $eligibleScopes
     * @param array<string, array<string, list<string>>> $valuesByAttributeCode
     * @return list<ProductSource>
     */
    private function buildProductSources(
        array $embeddingTemplates,
        int $productId,
        array $eligibleScopes,
        array $valuesByAttributeCode
    ): array {
        $productSources = [];

        foreach ($embeddingTemplates as $embeddingTemplate) {
            $productSources[] = new ProductSource(
                $embeddingTemplate->attributeCode,
                $this->buildScopedSources(
                    $embeddingTemplate,
                    $productId,
                    $eligibleScopes,
                    $valuesByAttributeCode
                )
            );
        }

        return $productSources;
    }

    /**
     * @param list<EligibleScope> $eligibleScopes
     * @param array<string, array<string, list<string>>> $valuesByAttributeCode
     * @return list<ScopedSource>
     */
    private function buildScopedSources(
        EmbeddedAttribute $embeddingTemplate,
        int $productId,
        array $eligibleScopes,
        array $valuesByAttributeCode
    ): array {
        $scopedSources = [];

        foreach ($eligibleScopes as $eligibleScope) {
            $scopedSources[] = new ScopedSource(
                $eligibleScope->storeId,
                $this->templateRenderer->render(
                    $embeddingTemplate,
                    $productId,
                    $eligibleScope,
                    $valuesByAttributeCode
                )
            );
        }

        return $scopedSources;
    }

    /**
     * @param array<int, list<EligibleScope>> $eligibleScopes
     * @return list<int>
     */
    private function getSourceProductIds(array $eligibleScopes): array
    {
        $sourceProductIds = [];

        foreach ($eligibleScopes as $productScopes) {
            $sourceProductIds += array_fill_keys(
                $this->getScopeProductIds($productScopes),
                true
            );
        }

        return array_keys($sourceProductIds);
    }

    /**
     * @param list<EligibleScope> $productScopes
     * @return list<int>
     */
    private function getScopeProductIds(array $productScopes): array
    {
        $productIds = [];

        foreach ($productScopes as $productScope) {
            $productIds += array_fill_keys($productScope->sourceProductIds, true);
        }

        return array_keys($productIds);
    }

    /**
     * @param array<int, list<EligibleScope>> $eligibleScopes
     * @return list<int>
     */
    private function getStoreIds(array $eligibleScopes): array
    {
        $storeIds = [];

        foreach ($eligibleScopes as $productScopes) {
            $storeIds += array_fill_keys($this->getScopeStoreIds($productScopes), true);
        }

        return array_keys($storeIds);
    }

    /**
     * @param list<EligibleScope> $productScopes
     * @return list<int>
     */
    private function getScopeStoreIds(array $productScopes): array
    {
        $storeIds = [];

        foreach ($productScopes as $productScope) {
            $storeIds[$productScope->storeId] = true;
        }

        return array_keys($storeIds);
    }
}
