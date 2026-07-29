<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow\DocumentProcessing\Chunking;

use InvalidArgumentException;

readonly class GeneralChunking implements ChunkingInterface
{
    private const string PARAGRAPH_SEPARATOR = "\n\n";
    private const string SENTENCE_SEPARATOR = ' ';

    /**
     * @return list<string>
     */
    public function chunk(
        string $text,
        int $maxTokens,
        int $overlapTokens,
        int $estimatedCharactersPerToken
    ): array {
        $normalizedText = $this->normalizeText($text);

        if ($normalizedText === '') {
            return [];
        }

        $maxCharacters = $maxTokens * $estimatedCharactersPerToken;
        $parts = $this->expandParagraphs($this->splitParagraphs($normalizedText), $maxCharacters);
        $chunks = $this->packParts($parts, self::PARAGRAPH_SEPARATOR, $maxCharacters);

        return $this->applyOverlap(
            $chunks,
            $overlapTokens * $estimatedCharactersPerToken,
            $maxCharacters
        );
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace(
            ['/^\x{FEFF}/u', '/[ \t]+/u', '/ *\n */u', '/\n{3,}/u'],
            ['', ' ', "\n", self::PARAGRAPH_SEPARATOR],
            $text
        );

        if ($text === null) {
            throw new InvalidArgumentException('Text must be valid UTF-8.');
        }

        return trim($text);
    }

    /**
     * @return list<string>
     */
    private function splitParagraphs(string $text): array
    {
        return $this->splitByPattern('/\n[ \t]*\n/u', $text);
    }

    /**
     * @return list<string>
     */
    private function splitSentences(string $text): array
    {
        return $this->splitByPattern('/(?<=[.!?。！？])\s+/u', $text);
    }

    /**
     * @return list<string>
     */
    private function splitWords(string $text): array
    {
        return $this->splitByPattern('/\s+/u', $text);
    }

    /**
     * @return list<string>
     */
    private function splitByPattern(string $pattern, string $text): array
    {
        $splitParts = preg_split($pattern, $text);
        $parts = $splitParts === false ? [] : $splitParts;
        $trimmedParts = array_map(trim(...), $parts);

        return array_values(array_filter($trimmedParts, static fn (string $part): bool => $part !== ''));
    }

    /**
     * @param list<string> $paragraphs
     * @return list<string>
     */
    private function expandParagraphs(array $paragraphs, int $maxCharacters): array
    {
        $parts = [];

        foreach ($paragraphs as $paragraph) {
            $expanded = mb_strlen($paragraph) <= $maxCharacters
                ? [$paragraph]
                : $this->splitOversizedParagraph($paragraph, $maxCharacters);
            array_push($parts, ...$expanded);
        }

        return $parts;
    }

    /**
     * @return list<string>
     */
    private function splitOversizedParagraph(string $paragraph, int $maxCharacters): array
    {
        $sentences = $this->expandSentences($this->splitSentences($paragraph), $maxCharacters);

        return $this->packParts($sentences, self::SENTENCE_SEPARATOR, $maxCharacters);
    }

    /**
     * @param list<string> $sentences
     * @return list<string>
     */
    private function expandSentences(array $sentences, int $maxCharacters): array
    {
        $parts = [];

        foreach ($sentences as $sentence) {
            $expanded = mb_strlen($sentence) <= $maxCharacters
                ? [$sentence]
                : $this->splitOversizedSentence($sentence, $maxCharacters);
            array_push($parts, ...$expanded);
        }

        return $parts;
    }

    /**
     * @return list<string>
     */
    private function splitOversizedSentence(string $sentence, int $maxCharacters): array
    {
        $words = $this->expandWords($this->splitWords($sentence), $maxCharacters);

        return $this->packParts($words, self::SENTENCE_SEPARATOR, $maxCharacters);
    }

    /**
     * @param list<string> $words
     * @return list<string>
     */
    private function expandWords(array $words, int $maxCharacters): array
    {
        $parts = [];

        foreach ($words as $word) {
            $expanded = mb_strlen($word) <= $maxCharacters
                ? [$word]
                : $this->hardSplit($word, $maxCharacters);
            array_push($parts, ...$expanded);
        }

        return $parts;
    }

    /**
     * @return list<string>
     */
    private function hardSplit(string $text, int $maxCharacters): array
    {
        $parts = [];
        $length = mb_strlen($text);

        for ($offset = 0; $offset < $length; $offset += $maxCharacters) {
            $parts[] = mb_substr($text, $offset, $maxCharacters);
        }

        return $parts;
    }

    /**
     * @param list<string> $parts
     * @return list<string>
     */
    private function packParts(array $parts, string $separator, int $maxCharacters): array
    {
        $chunks = [];
        $current = '';

        foreach ($parts as $part) {
            $candidate = $current === '' ? $part : $current . $separator . $part;

            if (mb_strlen($candidate) <= $maxCharacters) {
                $current = $candidate;
                continue;
            }

            $chunks[] = $current;
            $current = $part;
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * @param list<string> $chunks
     * @return list<string>
     */
    private function applyOverlap(array $chunks, int $overlapCharacters, int $maxCharacters): array
    {
        if ($overlapCharacters === 0 || count($chunks) < 2) {
            return $chunks;
        }

        $result = [$chunks[0]];

        foreach (array_slice($chunks, 1) as $index => $chunk) {
            $availableCharacters = $maxCharacters - mb_strlen($chunk) - mb_strlen(self::PARAGRAPH_SEPARATOR);
            $overlap = $this->tailAtWordBoundary(
                $chunks[$index],
                min($overlapCharacters, max(0, $availableCharacters))
            );
            $result[] = $overlap === '' ? $chunk : $overlap . self::PARAGRAPH_SEPARATOR . $chunk;
        }

        return $result;
    }

    private function tailAtWordBoundary(string $text, int $maxCharacters): string
    {
        if ($maxCharacters === 0) {
            return '';
        }

        if (mb_strlen($text) <= $maxCharacters) {
            return trim($text);
        }

        $tail = mb_substr($text, -$maxCharacters);
        $parts = preg_split('/\s+/u', $tail, 2);

        if ($parts === false || !isset($parts[1])) {
            return trim($tail);
        }

        return trim($parts[1]);
    }
}
