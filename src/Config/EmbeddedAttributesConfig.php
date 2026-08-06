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
    /**
     * @return list<EmbeddedAttribute>
     */
    public function getAttributes(): array
    {
        // TODO Load embedded attributes from Admin configuration
        return [
            new EmbeddedAttribute(
                attributeCode: 'description',
                composite: true
            ),
            new EmbeddedAttribute(
                attributeCode: 'name',
                composite: false
            ),
        ];
    }
}
