<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\EmbeddingTemplate;

use DavidBel\AiSearch\Config\EmbeddedAttribute;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Parsing;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider\Eligibility\EligibleScope;
use RuntimeException;

class TemplateRenderer
{
    public function __construct(
        private readonly ValueFormatter $valueFormatter,
        private readonly Parsing $parsing
    ) {
    }

    /**
     * @param list<EmbeddedAttribute> $embeddingTemplates
     * @return list<string>
     */
    public function getAttributeCodes(array $embeddingTemplates): array
    {
        $attributeCodes = [];

        foreach ($embeddingTemplates as $embeddingTemplate) {
            $attributeCodes += array_fill_keys(
                $this->getTemplateAttributeCodes($embeddingTemplate),
                true
            );
        }

        return array_keys($attributeCodes);
    }

    /**
     * @return list<string>
     */
    private function getTemplateAttributeCodes(EmbeddedAttribute $embeddingTemplate): array
    {
        $attributeCodes = [];

        foreach ($this->getFragments($embeddingTemplate) as $fragment) {
            $attributeCodes += array_fill_keys(
                $this->getFragmentAttributeCodes($fragment),
                true
            );
        }

        return array_keys($attributeCodes);
    }

    /**
     * @param array<string, array<string, list<string>>> $valuesByAttributeCode
     */
    public function render(
        EmbeddedAttribute $embeddingTemplate,
        int $productId,
        EligibleScope $eligibleScope,
        array $valuesByAttributeCode
    ): string {
        $renderedFragments = [];

        foreach ($this->getFragments($embeddingTemplate) as $fragment) {
            $renderedFragment = $this->renderFragment(
                $fragment,
                $productId,
                $eligibleScope,
                $valuesByAttributeCode
            );

            if ($renderedFragment === '') {
                continue;
            }

            $renderedFragments[] = $renderedFragment;
        }

        return implode(' ', $renderedFragments);
    }

    /**
     * @return list<EmbeddedAttribute>
     */
    private function getFragments(EmbeddedAttribute $embeddingTemplate): array
    {
        if ($embeddingTemplate->children === null || $embeddingTemplate->children === []) {
            throw new RuntimeException('An embedding template must contain at least one fragment.');
        }

        return $embeddingTemplate->children;
    }

    /**
     * @param array<string, array<string, list<string>>> $valuesByAttributeCode
     */
    private function renderFragment(
        EmbeddedAttribute $fragment,
        int $productId,
        EligibleScope $eligibleScope,
        array $valuesByAttributeCode
    ): string {
        if ($fragment->template === null || $fragment->template === '') {
            throw new RuntimeException('An embedding template fragment must contain text.');
        }

        $renderedFragment = $fragment->template;

        foreach ($this->getFragmentAttributeCodes($fragment) as $attributeCode) {
            $placeholder = '{' . $attributeCode . '}';

            if (!str_contains($renderedFragment, $placeholder)) {
                throw new RuntimeException('An embedding template fragment is missing a configured placeholder.');
            }

            $value = $this->getAttributeValue(
                $attributeCode,
                $fragment,
                $productId,
                $eligibleScope,
                $valuesByAttributeCode
            );

            if ($value === '') {
                return '';
            }

            $renderedFragment = str_replace($placeholder, $value, $renderedFragment);
        }

        return trim($renderedFragment);
    }

    /**
     * @param array<string, array<string, list<string>>> $valuesByAttributeCode
     */
    private function getAttributeValue(
        string $attributeCode,
        EmbeddedAttribute $fragment,
        int $productId,
        EligibleScope $eligibleScope,
        array $valuesByAttributeCode
    ): string {
        $values = [];

        foreach ($this->getSourceProductIds($fragment, $productId, $eligibleScope) as $sourceProductId) {
            $valueKey = $sourceProductId . ':' . $eligibleScope->storeId;

            foreach ($valuesByAttributeCode[$attributeCode][$valueKey] ?? [] as $value) {
                $values[] = $this->parsing->parse($value, $fragment->parsingStrategy);
            }
        }

        return $this->valueFormatter->format($values);
    }

    /**
     * @return list<int>
     */
    private function getSourceProductIds(
        EmbeddedAttribute $fragment,
        int $productId,
        EligibleScope $eligibleScope
    ): array {
        if ($fragment->composite) {
            return $eligibleScope->sourceProductIds;
        }

        return [$productId];
    }

    /**
     * @return list<string>
     */
    private function getFragmentAttributeCodes(EmbeddedAttribute $fragment): array
    {
        $attributeCodes = [];

        foreach (explode(',', $fragment->attributeCode) as $attributeCode) {
            $attributeCode = trim($attributeCode);

            if ($attributeCode === '') {
                throw new RuntimeException('An embedding template attribute code must not be empty.');
            }

            if (isset($attributeCodes[$attributeCode])) {
                continue;
            }

            $attributeCodes[$attributeCode] = true;
        }

        return array_keys($attributeCodes);
    }
}
