<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Client\Embedding\Base;

readonly class EmbeddingInput
{
    public function __construct(
        public ?string $title,
        public string $text
    ) {
    }
}
