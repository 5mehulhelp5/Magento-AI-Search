<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Model\Chunking;

use DavidBel\AiSearch\Model\Chunking\GeneralChunking;
use PHPUnit\Framework\TestCase;

class GeneralChunkingTest extends TestCase
{
    private GeneralChunking $chunking;

    protected function setUp(): void
    {
        $this->chunking = new GeneralChunking();
    }

    public function testReturnsNoChunksForBlankText(): void
    {
        self::assertSame([], $this->chunking->chunk(" \n\t ", 10, 0, 4));
    }

    public function testNormalizesTextAndPreservesParagraphBoundaries(): void
    {
        $text = "\u{FEFF}First   paragraph.\r\n\r\n\r\nSecond paragraph.";

        self::assertSame(
            ["First paragraph.\n\nSecond paragraph."],
            $this->chunking->chunk($text, 100, 0, 4)
        );
    }

    public function testSplitsOversizedParagraphAtSentenceBoundaries(): void
    {
        $chunks = $this->chunking->chunk(
            'One sentence. Two sentence. Three sentence.',
            3,
            0,
            5
        );

        self::assertSame(
            ['One sentence.', 'Two sentence.', 'Three sentence.'],
            $chunks
        );
    }

    public function testHardSplitsAnOversizedWordWithoutBreakingUnicodeCharacters(): void
    {
        $text = str_repeat('é', 31);
        $chunks = $this->chunking->chunk($text, 2, 0, 5);

        self::assertCount(4, $chunks);
        self::assertSame($text, implode('', $chunks));

        foreach ($chunks as $chunk) {
            self::assertLessThanOrEqual(10, mb_strlen($chunk));
        }
    }

    public function testAddsBoundedOverlapAtAWordBoundary(): void
    {
        $chunks = $this->chunking->chunk(
            "alpha beta gamma.\n\ndelta.",
            5,
            2,
            4
        );

        self::assertSame(
            ['alpha beta gamma.', "gamma.\n\ndelta."],
            $chunks
        );

        foreach ($chunks as $chunk) {
            self::assertLessThanOrEqual(20, mb_strlen($chunk));
        }
    }
}
