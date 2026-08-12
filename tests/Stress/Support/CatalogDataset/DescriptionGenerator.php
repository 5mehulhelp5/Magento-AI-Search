<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Stress\Support\CatalogDataset;

class DescriptionGenerator
{
    public const int MINIMUM_CHARACTER_COUNT = 4_000;
    public const int MAXIMUM_CHARACTER_COUNT = 8_000;

    private const int TARGET_CHARACTER_COUNT = 6_000;
    private const int WORDS_PER_PARAGRAPH = 80;
    private const array WORDS = [
        'amber',
        'balanced',
        'canvas',
        'durable',
        'element',
        'flexible',
        'granite',
        'harbor',
        'insulated',
        'journey',
        'kinetic',
        'layered',
        'modern',
        'natural',
        'outdoor',
        'practical',
        'quiet',
        'resilient',
        'structured',
        'textured',
        'universal',
        'versatile',
        'weather',
        'woven',
    ];

    public function generate(string $identity): string
    {
        $identityHash = hash('sha256', $identity);
        $description = sprintf('Stress product %s has identity %s.', $identity, $identityHash);
        $state = (int) hexdec(substr($identityHash, 0, 8));
        $wordCount = 0;

        while (mb_strlen($description) < self::TARGET_CHARACTER_COUNT) {
            $state = ($state * 1_103_515_245 + 12_345) & 0x7fffffff;
            $separator = $wordCount > 0 && $wordCount % self::WORDS_PER_PARAGRAPH === 0
                ? "\n\n"
                : ' ';
            $description .= $separator . self::WORDS[$state % count(self::WORDS)];
            $wordCount++;
        }

        return $description;
    }
}
