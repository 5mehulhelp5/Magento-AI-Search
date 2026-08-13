<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\Parsing;

class TextAsIs implements ParsingInterface
{
    private const string CODE = 'text_as_is';

    public function getCode(): string
    {
        return self::CODE;
    }

    public function parse(string $text): string
    {
        return trim($text);
    }
}
