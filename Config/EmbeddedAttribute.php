<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Config;

readonly class EmbeddedAttribute
{
    /**
     * @param list<EmbeddedAttribute>|null $children
     */
    public function __construct(
        public string $attributeCode,
        public bool $composite,
        public string $parsingStrategy,
        public ?string $template,
        public ?array $children
    ) {
    }
}
