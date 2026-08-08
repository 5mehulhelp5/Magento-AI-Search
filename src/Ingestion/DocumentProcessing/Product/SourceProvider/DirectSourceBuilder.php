<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider;

use DavidBel\AiSearch\Config\EmbeddedAttribute;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\Eligibility\EligibleScope;

class DirectSourceBuilder
{
    private const int DEFAULT_STORE_ID = 0;

    public function __construct(
        private readonly TitleResolver $titleResolver
    ) {
    }

    /**
     * @param list<EmbeddedAttribute> $embeddedAttributes
     * @param list<int> $productIds
     * @param array<int, list<EligibleScope>> $eligibleScopes
     * @param array<string, array<string, string>> $valuesBySourceCode
     * @param array<string, string> $titleValues
     * @return array<int, list<ProductSource>>
     */
    public function buildSourcesByProductId(
        array $embeddedAttributes,
        array $productIds,
        array $eligibleScopes,
        array $valuesBySourceCode,
        array $titleValues
    ): array {
        $sourcesByProductId = [];

        foreach ($productIds as $productId) {
            $productScopes = $eligibleScopes[$productId] ?? [];
            $sourcesByProductId[$productId] = $this->buildProductSources(
                $embeddedAttributes,
                $productId,
                $productScopes,
                $valuesBySourceCode,
                $titleValues
            );
        }

        return $sourcesByProductId;
    }

    /**
     * @param list<EmbeddedAttribute> $embeddedAttributes
     * @param list<EligibleScope> $eligibleScopes
     * @param array<string, array<string, string>> $valuesBySourceCode
     * @param array<string, string> $titleValues
     * @return list<ProductSource>
     */
    private function buildProductSources(
        array $embeddedAttributes,
        int $productId,
        array $eligibleScopes,
        array $valuesBySourceCode,
        array $titleValues
    ): array {
        $productSources = [];

        foreach ($embeddedAttributes as $embeddedAttribute) {
            $productSources[] = new ProductSource(
                $embeddedAttribute->attributeCode,
                $this->buildStoreScopedSources(
                    $embeddedAttribute,
                    $productId,
                    $eligibleScopes,
                    $valuesBySourceCode[$embeddedAttribute->attributeCode] ?? [],
                    $titleValues
                )
            );
        }

        return $productSources;
    }

    /**
     * @param list<EligibleScope> $eligibleScopes
     * @param array<string, string> $values
     * @param array<string, string> $titleValues
     * @return list<StoreScopedSource>
     */
    private function buildStoreScopedSources(
        EmbeddedAttribute $embeddedAttribute,
        int $productId,
        array $eligibleScopes,
        array $values,
        array $titleValues
    ): array {
        $storeScopedSources = [];

        foreach ($eligibleScopes as $eligibleScope) {
            $storeScopedSources[] = new StoreScopedSource(
                $eligibleScope->storeId,
                $this->getContent(
                    $this->getSourceProductIds($embeddedAttribute, $productId, $eligibleScope),
                    $eligibleScope->storeId,
                    $values
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
