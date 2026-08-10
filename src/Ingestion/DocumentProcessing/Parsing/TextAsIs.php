<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\Parsing;

class TextAsIs implements ParsingInterface
{
    public function parse(string $text): string
    {
        return trim($text);
    }
}
