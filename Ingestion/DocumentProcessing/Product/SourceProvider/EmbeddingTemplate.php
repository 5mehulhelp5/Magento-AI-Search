<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider;

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
     * @param array<int, \DavidBel\AiSearch\Config\EmbeddedAttribute> $dynamicDocumentsByStoreId
     * @param list<int> $productIds
     * @param array<int, list<Eligibility\EligibleScope>> $eligibleScopes
     * @param array<string, string> $titleValues
     * @return array<int, list<DocumentSource>>
     */
    public function buildSourcesByProductId(
        array $dynamicDocumentsByStoreId,
        array $productIds,
        array $eligibleScopes,
        array $titleValues
    ): array {
        $valuesByAttributeCode = $this->attributeValueResolver->getValuesByAttributeCode(
            $this->templateRenderer->getAttributeCodes(
                array_values($dynamicDocumentsByStoreId)
            ),
            $this->getSourceProductIds($eligibleScopes),
            $this->getStoreIds($eligibleScopes)
        );
        $sourcesByProductId = [];

        foreach ($productIds as $productId) {
            $sourcesByProductId[$productId] = $this->buildDocumentSources(
                $dynamicDocumentsByStoreId,
                $productId,
                $eligibleScopes[$productId] ?? [],
                $valuesByAttributeCode,
                $titleValues
            );
        }

        return $sourcesByProductId;
    }

    /**
     * @param array<int, \DavidBel\AiSearch\Config\EmbeddedAttribute> $dynamicDocumentsByStoreId
     * @param list<Eligibility\EligibleScope> $eligibleScopes
     * @param array<string, array<string, list<string>>> $valuesByAttributeCode
     * @param array<string, string> $titleValues
     * @return list<DocumentSource>
     */
    private function buildDocumentSources(
        array $dynamicDocumentsByStoreId,
        int $productId,
        array $eligibleScopes,
        array $valuesByAttributeCode,
        array $titleValues
    ): array {
        $storeScopedSources = $this->buildStoreScopedSources(
            $dynamicDocumentsByStoreId,
            $productId,
            $eligibleScopes,
            $valuesByAttributeCode,
            $titleValues
        );

        if ($storeScopedSources === []) {
            return [];
        }

        $dynamicDocument = $dynamicDocumentsByStoreId[$storeScopedSources[0]->storeId];

        return [
            new DocumentSource(
                $dynamicDocument->attributeCode,
                $dynamicDocument->parsingStrategy,
                $storeScopedSources
            ),
        ];
    }

    /**
     * @param array<int, \DavidBel\AiSearch\Config\EmbeddedAttribute> $dynamicDocumentsByStoreId
     * @param list<Eligibility\EligibleScope> $eligibleScopes
     * @param array<string, array<string, list<string>>> $valuesByAttributeCode
     * @param array<string, string> $titleValues
     * @return list<StoreScopedSource>
     */
    private function buildStoreScopedSources(
        array $dynamicDocumentsByStoreId,
        int $productId,
        array $eligibleScopes,
        array $valuesByAttributeCode,
        array $titleValues
    ): array {
        $storeScopedSources = [];

        foreach ($eligibleScopes as $eligibleScope) {
            $dynamicDocument = $dynamicDocumentsByStoreId[$eligibleScope->storeId] ?? null;

            if ($dynamicDocument === null) {
                continue;
            }

            $storeScopedSources[] = new StoreScopedSource(
                $eligibleScope->storeId,
                $this->templateRenderer->render(
                    $dynamicDocument,
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
