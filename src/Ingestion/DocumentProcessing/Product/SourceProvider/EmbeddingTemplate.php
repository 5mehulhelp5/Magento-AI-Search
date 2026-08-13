<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider;

use DavidBel\AiSearch\Config\EmbeddedAttribute;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentSource;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentSource\StoreScopedSource;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\EmbeddingTemplate\AttributeValueResolver;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\EmbeddingTemplate\TemplateRenderer;

class EmbeddingTemplate
{
    public function __construct(
        private readonly AttributeValueResolver $attributeValueResolver,
        private readonly TemplateRenderer $templateRenderer,
        private readonly TitleResolver $titleResolver
    ) {
    }

    /**
     * @param list<\DavidBel\AiSearch\Config\EmbeddedAttribute> $embeddedAttributes
     * @param list<int> $productIds
     * @param array<int, list<Eligibility\EligibleScope>> $eligibleScopes
     * @param array<string, string> $titleValues
     * @return array<int, list<DocumentSource>>
     */
    public function buildSourcesByProductId(
        array $embeddedAttributes,
        array $productIds,
        array $eligibleScopes,
        array $titleValues
    ): array {
        $embeddingTemplates = $this->getEmbeddingTemplates($embeddedAttributes);
        $valuesByAttributeCode = $this->attributeValueResolver->getValuesByAttributeCode(
            $this->templateRenderer->getAttributeCodes($embeddingTemplates),
            $this->getSourceProductIds($eligibleScopes),
            $this->getStoreIds($eligibleScopes)
        );
        $sourcesByProductId = [];

        foreach ($productIds as $productId) {
            $sourcesByProductId[$productId] = $this->buildDocumentSources(
                $embeddingTemplates,
                $productId,
                $eligibleScopes[$productId] ?? [],
                $valuesByAttributeCode,
                $titleValues
            );
        }

        return $sourcesByProductId;
    }

    /**
     * @param list<\DavidBel\AiSearch\Config\EmbeddedAttribute> $embeddedAttributes
     * @return list<\DavidBel\AiSearch\Config\EmbeddedAttribute>
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
     * @param list<\DavidBel\AiSearch\Config\EmbeddedAttribute> $embeddingTemplates
     * @param list<Eligibility\EligibleScope> $eligibleScopes
     * @param array<string, array<string, list<string>>> $valuesByAttributeCode
     * @param array<string, string> $titleValues
     * @return list<DocumentSource>
     */
    private function buildDocumentSources(
        array $embeddingTemplates,
        int $productId,
        array $eligibleScopes,
        array $valuesByAttributeCode,
        array $titleValues
    ): array {
        $documentSources = [];

        foreach ($embeddingTemplates as $embeddingTemplate) {
            $documentSources[] = new DocumentSource(
                $embeddingTemplate->attributeCode,
                $embeddingTemplate->parsingStrategy,
                $this->buildStoreScopedSources(
                    $embeddingTemplate,
                    $productId,
                    $eligibleScopes,
                    $valuesByAttributeCode,
                    $titleValues
                )
            );
        }

        return $documentSources;
    }

    /**
     * @param list<Eligibility\EligibleScope> $eligibleScopes
     * @param array<string, array<string, list<string>>> $valuesByAttributeCode
     * @param array<string, string> $titleValues
     * @return list<StoreScopedSource>
     */
    private function buildStoreScopedSources(
        EmbeddedAttribute $embeddingTemplate,
        int $productId,
        array $eligibleScopes,
        array $valuesByAttributeCode,
        array $titleValues
    ): array {
        $storeScopedSources = [];

        foreach ($eligibleScopes as $eligibleScope) {
            $storeScopedSources[] = new StoreScopedSource(
                $eligibleScope->storeId,
                $this->templateRenderer->render(
                    $embeddingTemplate,
                    $productId,
                    $eligibleScope,
                    $valuesByAttributeCode
                ),
                $this->titleResolver->getTitle(
                    $titleValues,
                    $productId,
                    $eligibleScope->storeId
                )
            );
        }

        return $storeScopedSources;
    }

    /**
     * @param array<int, list<Eligibility\EligibleScope>> $eligibleScopes
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
     * @param list<Eligibility\EligibleScope> $productScopes
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
     * @param array<int, list<Eligibility\EligibleScope>> $eligibleScopes
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
     * @param list<Eligibility\EligibleScope> $productScopes
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
