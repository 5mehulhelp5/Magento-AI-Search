<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Ingestion\DocumentProcessing;

use DavidBel\AiSearch\Config\EmbedderConfig;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdater\Chunking;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdater\Chunking\ChunkingInterface;
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
                100,
                10,
                4
            )
            ->willReturn(['Product description']);

        $chunking = new Chunking(
            $this->createEmbedderConfig(),
            ['general' => $strategy]
        );

        self::assertSame(['Product description'], $chunking->chunk('Product description'));
    }

    public function testRejectsAnEmptyStrategyPool(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Chunking($this->createEmbedderConfig(), []);
    }

    private function createEmbedderConfig(): EmbedderConfig
    {
        $config = self::createStub(EmbedderConfig::class);
        $config->method('getMaximumChunkTokens')->willReturn(100);
        $config->method('getChunkOverlapTokens')->willReturn(10);
        $config->method('getEstimatedCharactersPerToken')->willReturn(4);

        return $config;
    }
}
