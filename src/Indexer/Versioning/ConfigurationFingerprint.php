<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer\Versioning;

use DavidBel\AiSearch\Config\EmbeddedAttribute;
use DavidBel\AiSearch\Config\EmbeddedAttributesConfig;
use DavidBel\AiSearch\Config\EmbedderConfig;
use DavidBel\AiSearch\Config\IndexingScopeConfig;
use DavidBel\AiSearch\Config\SearchConfig;
use Magento\Framework\Serialize\SerializerInterface;
use UnexpectedValueException;

class ConfigurationFingerprint
{
    public function __construct(
        private readonly EmbedderConfig $embedderConfig,
        private readonly EmbeddedAttributesConfig $embeddedAttributesConfig,
        private readonly IndexingScopeConfig $indexingScopeConfig,
        private readonly SearchConfig $searchConfig,
        private readonly IndexName $indexName,
        private readonly SerializerInterface $serializer
    ) {
    }

    public function get(): string
    {
        $indexedStoreIds = $this->indexingScopeConfig->getStoreIdsForIndexing();
        $serializedConfiguration = $this->serializer->serialize([
            'index_alias' => $this->indexName->getAlias(),
            'index_schema_version' => $this->searchConfig->getIndexSchemaVersion(),
            'indexed_store_ids' => $indexedStoreIds,
            'title_attribute_code' => $this->getDocumentTitleAttributeCode(),
            'embedded_attributes' => $this->getEmbeddedAttributesByStoreId($indexedStoreIds),
            'chunking' => [
                'max_tokens' => $this->embedderConfig->getMaximumChunkTokens(),
                'overlap_tokens' => $this->embedderConfig->getChunkOverlapTokens(),
                'estimated_characters_per_token' => $this->embedderConfig
                    ->getEstimatedCharactersPerToken(),
            ],
            'embedding' => [
                'model' => $this->embedderConfig->getEmbeddingModel(),
                'vector_dimensions' => $this->embedderConfig->getVectorDimensions(),
                'document_template' => $this->embedderConfig->getEmbedderDocumentTemplate(),
            ],
            'index' => [
                'vector_method' => $this->searchConfig->getVectorMethod(),
                'vector_engine' => $this->searchConfig->getVectorEngine(),
                'vector_space' => $this->searchConfig->getVectorSpace(),
            ],
        ]);

        if (!is_string($serializedConfiguration)) {
            throw new UnexpectedValueException('The index version configuration could not be serialized.');
        }

        return hash('sha256', $serializedConfiguration);
    }

    private function getDocumentTitleAttributeCode(): ?string
    {
        if (!$this->embeddedAttributesConfig->isDocumentTitleEnabled()) {
            return null;
        }

        return $this->embeddedAttributesConfig->getDocumentTitleAttributeCode();
    }

    /**
     * @param list<int> $storeIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function getEmbeddedAttributesByStoreId(array $storeIds): array
    {
        $attributesByStoreId = [];

        foreach ($storeIds as $storeId) {
            $attributes = [];

            foreach ($this->embeddedAttributesConfig->getAttributes($storeId) as $attribute) {
                $attributes[] = $this->getEmbeddedAttribute($attribute);
            }

            $attributesByStoreId[$storeId] = $attributes;
        }

        return $attributesByStoreId;
    }

    /**
     * @return array<string, mixed>
     */
    private function getEmbeddedAttribute(EmbeddedAttribute $attribute): array
    {
        $children = null;

        if ($attribute->children !== null) {
            $children = [];

            foreach ($attribute->children as $child) {
                $children[] = $this->getEmbeddedAttribute($child);
            }
        }

        return [
            'attribute_code' => $attribute->attributeCode,
            'composite' => $attribute->composite,
            'parsing_strategy' => $attribute->parsingStrategy,
            'template' => $attribute->template,
            'children' => $children,
        ];
    }
}
