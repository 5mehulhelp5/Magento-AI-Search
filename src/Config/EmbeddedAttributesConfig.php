<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Config;

class EmbeddedAttributesConfig
{
    private const string TITLE_ATTRIBUTE_CODE = 'name';

    public function getTitleAttributeCode(): string
    {
        return self::TITLE_ATTRIBUTE_CODE;
    }

    /**
     * @return list<EmbeddedAttribute>
     */
    public function getAttributes(): array
    {
        return [
            new EmbeddedAttribute(
                attributeCode: 'description',
                composite: true,
                template: null,
                children: null
            ),
            new EmbeddedAttribute(
                attributeCode: 'name',
                composite: false,
                template: null,
                children: null
            ),
            new EmbeddedAttribute(
                attributeCode: 'embedding_template',
                composite: false,
                template: null,
                children: [
                    new EmbeddedAttribute(
                        attributeCode: 'name',
                        composite: false,
                        template: 'This product is called {name}.',
                        children: null
                    ),
                    new EmbeddedAttribute(
                        attributeCode: 'color,size',
                        composite: true,
                        template: 'It is available in {color}, with size options including {size}.',
                        children: null
                    ),
                    new EmbeddedAttribute(
                        attributeCode: 'material',
                        composite: true,
                        template: 'It is made from {material}.',
                        children: null
                    ),
                    new EmbeddedAttribute(
                        attributeCode: 'pattern',
                        composite: true,
                        template: 'Its design features a {pattern} pattern.',
                        children: null
                    ),
                    new EmbeddedAttribute(
                        attributeCode: 'activity',
                        composite: true,
                        template: 'It is designed for {activity}.',
                        children: null
                    ),
                    new EmbeddedAttribute(
                        attributeCode: 'climate',
                        composite: true,
                        template: 'It is well suited to {climate} conditions.',
                        children: null
                    ),
                    new EmbeddedAttribute(
                        attributeCode: 'short_description',
                        composite: true,
                        template: 'Here is a brief description: {short_description}',
                        children: null
                    ),
                ]
            ),
        ];
    }
}
