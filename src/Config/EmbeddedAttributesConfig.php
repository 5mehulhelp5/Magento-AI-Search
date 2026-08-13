<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use UnexpectedValueException;

class EmbeddedAttributesConfig
{
    private const string XML_PATH_ENABLE_DOCUMENT_TITLE =
        'davidbel_ai_search_search_source/document_configuration/enable_document_title';
    private const string XML_PATH_DOCUMENT_TITLE =
        'davidbel_ai_search_search_source/document_configuration/document_title';
    private const string XML_PATH_ENABLE_DYNAMIC_DOCUMENT =
        'davidbel_ai_search_search_source/document_configuration/enable_dynamic_document';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isDocumentTitleEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLE_DOCUMENT_TITLE);
    }

    public function getDocumentTitleAttributeCode(): string
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_DOCUMENT_TITLE);

        if (!is_string($value)) {
            throw new UnexpectedValueException(
                sprintf(
                    'Configuration path "%s" must contain a string.',
                    self::XML_PATH_DOCUMENT_TITLE
                )
            );
        }

        return $value;
    }

    public function isDynamicDocumentEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLE_DYNAMIC_DOCUMENT);
    }

    /**
     * @return list<EmbeddedAttribute>
     */
    public function getAttributes(): array
    {
        $attributes = $this->getDocumentAttributes();

        if ($this->isDynamicDocumentEnabled()) {
            $attributes[] = $this->getDynamicDocumentAttribute();
        }

        return $attributes;
    }

    /**
     * @return list<EmbeddedAttribute>
     */
    private function getDocumentAttributes(): array
    {
        return [
            new EmbeddedAttribute(
                attributeCode: 'description',
                composite: true,
                parsingStrategy: 'html_to_text',
                template: null,
                children: null
            ),
            new EmbeddedAttribute(
                attributeCode: 'name',
                composite: false,
                parsingStrategy: 'text_as_is',
                template: null,
                children: null
            ),
        ];
    }

    private function getDynamicDocumentAttribute(): EmbeddedAttribute
    {
        return new EmbeddedAttribute(
            attributeCode: 'embedding_template',
            composite: false,
            parsingStrategy: 'text_as_is',
            template: null,
            children: $this->getDynamicDocumentChildren()
        );
    }

    /**
     * @return list<EmbeddedAttribute>
     */
    private function getDynamicDocumentChildren(): array
    {
        return [
            $this->createDynamicDocumentChild(
                'name',
                false,
                'text_as_is',
                'This product is called {name}.'
            ),
            $this->createDynamicDocumentChild(
                'color,size',
                true,
                'text_as_is',
                'It is available in {color}, with size options including {size}.'
            ),
            $this->createDynamicDocumentChild('material', true, 'text_as_is', 'It is made from {material}.'),
            $this->createDynamicDocumentChild(
                'pattern',
                true,
                'text_as_is',
                'Its design features a {pattern} pattern.'
            ),
            $this->createDynamicDocumentChild('activity', true, 'text_as_is', 'It is designed for {activity}.'),
            $this->createDynamicDocumentChild(
                'climate',
                true,
                'text_as_is',
                'It is well suited to {climate} conditions.'
            ),
            $this->createDynamicDocumentChild(
                'short_description',
                true,
                'html_to_text',
                'Here is a brief description: {short_description}'
            ),
        ];
    }

    private function createDynamicDocumentChild(
        string $attributeCode,
        bool $composite,
        string $parsingStrategy,
        string $template
    ): EmbeddedAttribute {
        return new EmbeddedAttribute(
            attributeCode: $attributeCode,
            composite: $composite,
            parsingStrategy: $parsingStrategy,
            template: $template,
            children: null
        );
    }
}
