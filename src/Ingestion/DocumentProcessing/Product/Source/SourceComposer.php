<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\Source;

use DavidBel\AiSearch\Config\EmbeddedAttribute;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\Eligibility\EligibleScope;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\ProductSource;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\ScopedSource;

class SourceComposer
{
    private const int DEFAULT_STORE_ID = 0;

    /**
     * @param list<EmbeddedAttribute> $embeddedAttributes
     * @param list<int> $productIds
     * @param array<int, list<EligibleScope>> $eligibleScopes
     * @param array<string, array<string, string>> $valuesBySourceCode
     * @return array<int, list<ProductSource>>
     */
    public function compose(
        array $embeddedAttributes,
        array $productIds,
        array $eligibleScopes,
        array $valuesBySourceCode
    ): array {
        $sourcesByProductId = [];

        foreach ($productIds as $productId) {
            $productScopes = $eligibleScopes[$productId] ?? [];
            $sourcesByProductId[$productId] = $this->composeProductSources(
                $embeddedAttributes,
                $productId,
                $productScopes,
                $valuesBySourceCode
            );
        }

        return $sourcesByProductId;
    }

    /**
     * @param list<EmbeddedAttribute> $embeddedAttributes
     * @param list<EligibleScope> $eligibleScopes
     * @param array<string, array<string, string>> $valuesBySourceCode
     * @return list<ProductSource>
     */
    private function composeProductSources(
        array $embeddedAttributes,
        int $productId,
        array $eligibleScopes,
        array $valuesBySourceCode
    ): array {
        $productSources = [];

        foreach ($embeddedAttributes as $embeddedAttribute) {
            $productSources[] = new ProductSource(
                $embeddedAttribute->attributeCode,
                $this->composeAttributeScopes(
                    $embeddedAttribute,
                    $productId,
                    $eligibleScopes,
                    $valuesBySourceCode[$embeddedAttribute->attributeCode] ?? []
                )
            );
        }

        return $productSources;
    }

    /**
     * @param list<EligibleScope> $eligibleScopes
     * @param array<string, string> $values
     * @return list<ScopedSource>
     */
    private function composeAttributeScopes(
        EmbeddedAttribute $embeddedAttribute,
        int $productId,
        array $eligibleScopes,
        array $values
    ): array {
        $scopedSources = [];

        foreach ($eligibleScopes as $eligibleScope) {
            $scopedSources[] = new ScopedSource(
                $eligibleScope->storeId,
                $this->getContent(
                    $this->getSourceProductIds($embeddedAttribute, $productId, $eligibleScope),
                    $eligibleScope->storeId,
                    $values
                )
            );
        }

        return $scopedSources;
    }

    /**
     * @return list<int>
     */
    private function getSourceProductIds(
        EmbeddedAttribute $embeddedAttribute,
        int $productId,
        EligibleScope $eligibleScope
    ): array {
        if ($embeddedAttribute->composite) {
            return $eligibleScope->sourceProductIds;
        }

        return [$productId];
    }

    /**
     * @param list<int> $sourceProductIds
     * @param array<string, string> $values
     */
    private function getContent(array $sourceProductIds, int $storeId, array $values): string
    {
        $contentsByHash = [];
        $contents = [];

        foreach ($sourceProductIds as $sourceProductId) {
            $content = $this->getStoreValue($values, $sourceProductId, $storeId);

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
        return in_array(
            $content,
            $contentsByHash[hash('sha256', $content)] ?? [],
            true
        );
    }

    private function getValueKey(int $productId, int $storeId): string
    {
        return $productId . ':' . $storeId;
    }
}
