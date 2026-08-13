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
use DavidBel\AiSearch\Config\SearchConfig;
use Magento\Framework\Serialize\SerializerInterface;
use UnexpectedValueException;

class ConfigurationFingerprint
{
    public function __construct(
        private readonly EmbedderConfig $embedderConfig,
        private readonly EmbeddedAttributesConfig $embeddedAttributesConfig,
        private readonly SearchConfig $searchConfig,
        private readonly IndexName $indexName,
        private readonly SerializerInterface $serializer
    ) {
    }

    public function get(): string
    {
        $serializedConfiguration = $this->serializer->serialize([
            'index_alias' => $this->indexName->getAlias(),
            'index_schema_version' => $this->searchConfig->getIndexSchemaVersion(),
            'title_attribute_code' => $this->getDocumentTitleAttributeCode(),
            'embedded_attributes' => $this->getEmbeddedAttributes(),
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
     * @return list<array<string, mixed>>
     */
    private function getEmbeddedAttributes(): array
    {
        $attributes = [];

        foreach ($this->embeddedAttributesConfig->getAttributes() as $attribute) {
            $attributes[] = $this->getEmbeddedAttribute($attribute);
        }

        return $attributes;
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
