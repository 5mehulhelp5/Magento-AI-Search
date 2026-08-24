<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Ingestion\DocumentProcessing\Chunking;

use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdater\Chunking\GeneralChunking;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

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

    public function testRejectsInvalidUtf8(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->chunking->chunk("\xC3\x28", 10, 0, 4);
    }

    public function testSplitsLongSentenceUsingWords(): void
    {
        self::assertSame(
            ['alpha beta', 'gamma', 'delta'],
            $this->chunking->chunk('alpha beta gamma delta', 10, 0, 1)
        );
    }

    public function testOmitsOverlapWhenNextChunkUsesAllAvailableSpace(): void
    {
        self::assertSame(
            ['alpha beta', '12345678'],
            $this->chunking->chunk("alpha beta\n\n12345678", 10, 5, 1)
        );
    }

    public function testUsesContinuousTailWhenThereIsNoWordBoundary(): void
    {
        self::assertSame(
            ['abcdefghij', "hij\n\n12345"],
            $this->chunking->chunk("abcdefghij\n\n12345", 10, 3, 1)
        );
    }

    public function testKeepsWholeTailWhenItFits(): void
    {
        $method = new ReflectionMethod(GeneralChunking::class, 'tailAtWordBoundary');

        self::assertSame('short tail', $method->invoke($this->chunking, ' short tail ', 20));
    }
}
