<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer\Versioning;

use DavidBel\AiSearch\Config\EmbeddedAttribute;
use DavidBel\AiSearch\Config\EmbeddedAttributesConfig;
use DavidBel\AiSearch\Config\EmbedderConfig;
use DavidBel\AiSearch\Config\IndexVersionConfig;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdater\Chunking;
use Magento\Framework\Serialize\SerializerInterface;
use UnexpectedValueException;

class ConfigurationFingerprint
{
    public function __construct(
        private readonly EmbedderConfig $embedderConfig,
        private readonly EmbeddedAttributesConfig $embeddedAttributesConfig,
        private readonly IndexVersionConfig $indexVersionConfig,
        private readonly IndexName $indexName,
        private readonly SerializerInterface $serializer
    ) {
    }

    public function get(): string
    {
        $serializedConfiguration = $this->serializer->serialize([
            'index_alias' => $this->indexName->getAlias(),
            'index_schema_version' => $this->indexVersionConfig->getIndexSchemaVersion(),
            'embedded_attributes' => $this->getEmbeddedAttributes(),
            'chunking' => [
                'max_tokens' => Chunking::MAX_TOKENS,
                'overlap_tokens' => Chunking::OVERLAP_TOKENS,
                'estimated_characters_per_token' => Chunking::ESTIMATED_CHARACTERS_PER_TOKEN,
            ],
            'embedding' => [
                'model' => $this->embedderConfig->getModel(),
                'vector_dimensions' => $this->embedderConfig->getVectorDimensions(),
                'document_template' => $this->embedderConfig->getDocumentTemplate(),
                'query_template' => $this->embedderConfig->getQueryTemplate(),
            ],
            'index' => [
                'vector_method' => $this->indexVersionConfig->getVectorMethod(),
                'vector_engine' => $this->indexVersionConfig->getVectorEngine(),
                'vector_space' => $this->indexVersionConfig->getVectorSpace(),
            ],
        ]);

        if (!is_string($serializedConfiguration)) {
            throw new UnexpectedValueException('The index version configuration could not be serialized.');
        }

        return hash('sha256', $serializedConfiguration);
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
            'template' => $attribute->template,
            'children' => $children,
        ];
    }
}
