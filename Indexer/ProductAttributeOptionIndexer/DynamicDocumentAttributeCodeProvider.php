<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer\ProductAttributeOptionIndexer;

use DavidBel\AiSearch\Config\EmbeddedAttribute;
use DavidBel\AiSearch\Config\EmbeddedAttributesConfig;
use DavidBel\AiSearch\Config\IndexingScopeConfig;

class DynamicDocumentAttributeCodeProvider
{
    public function __construct(
        private readonly EmbeddedAttributesConfig $embeddedAttributesConfig,
        private readonly IndexingScopeConfig $indexingScopeConfig
    ) {
    }

    /**
     * @return list<string>
     */
    public function getAttributeCodes(): array
    {
        $attributeCodes = [];

        foreach ($this->indexingScopeConfig->getStoreIdsForIndexing() as $storeId) {
            $dynamicDocument = $this->embeddedAttributesConfig->getDynamicDocument($storeId);

            if ($dynamicDocument === null) {
                continue;
            }

            foreach ($this->getChildAttributeCodes($dynamicDocument) as $attributeCode) {
                $attributeCodes[$attributeCode] = $attributeCode;
            }
        }

        ksort($attributeCodes);

        return array_values($attributeCodes);
    }

    /**
     * @return list<string>
     */
    private function getChildAttributeCodes(EmbeddedAttribute $embeddedAttribute): array
    {
        if ($embeddedAttribute->children === null) {
            return [];
        }

        $attributeCodes = [];

        foreach ($embeddedAttribute->children as $child) {
            foreach ($this->getAttributeCodesFrom($child) as $attributeCode) {
                $attributeCodes[$attributeCode] = $attributeCode;
            }
        }

        return array_values($attributeCodes);
    }

    /**
     * @return list<string>
     */
    private function getAttributeCodesFrom(EmbeddedAttribute $embeddedAttribute): array
    {
        $attributeCodes = $this->splitAttributeCodes($embeddedAttribute->attributeCode);

        foreach ($this->getChildAttributeCodes($embeddedAttribute) as $attributeCode) {
            $attributeCodes[$attributeCode] = $attributeCode;
        }

        return array_values($attributeCodes);
    }

    /**
     * @return array<string, string>
     */
    private function splitAttributeCodes(string $configuredAttributeCodes): array
    {
        $attributeCodes = [];

        foreach (explode(',', $configuredAttributeCodes) as $configuredAttributeCode) {
            $attributeCode = trim($configuredAttributeCode);

            if ($attributeCode === '') {
                continue;
            }

            $attributeCodes[$attributeCode] = $attributeCode;
        }

        return $attributeCodes;
    }
}
