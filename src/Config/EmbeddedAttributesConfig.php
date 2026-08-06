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
    // TODO Load embedded attributes and templates from Admin configuration

    /**
     * @return list<EmbeddedAttribute>
     */
    public function getAttributes(): array
    {
        return [
            new EmbeddedAttribute(
                attributeCode: 'description',
                composite: true,
                template: null
            ),
            new EmbeddedAttribute(
                attributeCode: 'name',
                composite: false,
                template: null
            ),
        ];
    }

    /**
     * @return list<EmbeddedAttribute>
     */
    public function getTemplates(): array
    {
        return [
            new EmbeddedAttribute(
                attributeCode: 'embedding_template',
                composite: true,
                template: [
                    'This product is called {name}.',
                    'It is available in {color}, with size options including {size}.',
                    'It is made from {material}.',
                    'Its design features a {pattern} pattern.',
                    'It is designed for {activity}.',
                    'It is well suited to {climate} conditions.',
                    'Here is a brief description: {short_description}',
                ]
            ),
        ];
    }
}
