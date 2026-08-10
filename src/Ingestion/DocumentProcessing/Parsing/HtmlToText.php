<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\Parsing;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use RuntimeException;

class HtmlToText implements ParsingInterface
{
    private const array IGNORED_ELEMENTS = [
        'head' => true,
        'noscript' => true,
        'script' => true,
        'style' => true,
        'template' => true,
    ];
    private const array ELEMENT_SEPARATORS = [
        'address' => "\n\n",
        'article' => "\n\n",
        'aside' => "\n\n",
        'blockquote' => "\n\n",
        'br' => "\n",
        'caption' => "\n",
        'dd' => "\n",
        'div' => "\n\n",
        'dl' => "\n\n",
        'dt' => "\n",
        'figcaption' => "\n",
        'figure' => "\n\n",
        'footer' => "\n\n",
        'h1' => "\n\n",
        'h2' => "\n\n",
        'h3' => "\n\n",
        'h4' => "\n\n",
        'h5' => "\n\n",
        'h6' => "\n\n",
        'header' => "\n\n",
        'hr' => "\n\n",
        'li' => "\n",
        'main' => "\n\n",
        'nav' => "\n\n",
        'ol' => "\n\n",
        'p' => "\n\n",
        'pre' => "\n\n",
        'section' => "\n\n",
        'table' => "\n\n",
        'tbody' => "\n",
        'td' => ' ',
        'tfoot' => "\n",
        'th' => ' ',
        'thead' => "\n",
        'tr' => "\n",
        'ul' => "\n\n",
    ];

    public function parse(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrorHandling = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML(
                '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'
                . $text
                . '</body></html>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorHandling);
        }

        if (!$loaded) {
            throw new RuntimeException('HTML content could not be parsed.');
        }

        $body = $document->getElementsByTagName('body')->item(0);

        if ($body === null) {
            throw new RuntimeException('Parsed HTML content does not contain a body.');
        }

        return $this->normalize($this->extractText($body));
    }

    private function extractText(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return $node->nodeValue ?? '';
        }

        $elementName = $node instanceof DOMElement ? strtolower($node->tagName) : null;

        if ($elementName !== null && isset(self::IGNORED_ELEMENTS[$elementName])) {
            return '';
        }

        $text = '';

        foreach ($node->childNodes as $childNode) {
            $text .= $this->extractText($childNode);
        }

        if ($elementName === null) {
            return $text;
        }

        return $text . (self::ELEMENT_SEPARATORS[$elementName] ?? '');
    }

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
        $text = $this->replace('/[^\S\n]+/u', ' ', $text);
        $text = $this->replace('/ *\n */u', "\n", $text);
        $text = $this->replace('/\n{3,}/u', "\n\n", $text);

        return trim($text);
    }

    private function replace(string $pattern, string $replacement, string $subject): string
    {
        $result = preg_replace($pattern, $replacement, $subject);

        if ($result === null) {
            throw new RuntimeException('Parsed HTML text could not be normalized.');
        }

        return $result;
    }
}
