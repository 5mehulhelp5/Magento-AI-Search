<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Ingestion\DocumentProcessing;

use DavidBel\AiSearch\Ingestion\DocumentProcessing\Chunking;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\Chunking\ChunkingInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ChunkingTest extends TestCase
{
    public function testDelegatesToFirstConfiguredStrategy(): void
    {
        $strategy = $this->createMock(ChunkingInterface::class);
        $strategy->expects(self::once())
            ->method('chunk')
            ->with(
                'Product description',
                Chunking::MAX_TOKENS,
                Chunking::OVERLAP_TOKENS,
                Chunking::ESTIMATED_CHARACTERS_PER_TOKEN
            )
            ->willReturn(['Product description']);

        $chunking = new Chunking(['general' => $strategy]);

        self::assertSame(['Product description'], $chunking->chunk('Product description'));
    }

    public function testRejectsAnEmptyStrategyPool(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Chunking([]);
    }
}
