<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Client\Embedding;

use DavidBel\AiSearch\Client\Embedding\Base\EmbedderClientInterface;
use DavidBel\AiSearch\Client\Embedding\Base\EmbedderClientPool;
use DavidBel\AiSearch\Config\EmbedderConfig;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class EmbedderClientPoolTest extends TestCase
{
    public function testReturnsTheConfiguredClient(): void
    {
        $config = self::createStub(EmbedderConfig::class);
        $config->method('getEmbeddingApiProtocol')->willReturn('openai');
        $client = self::createStub(EmbedderClientInterface::class);

        self::assertSame($client, (new EmbedderClientPool($config, ['openai' => $client]))->getClient());
    }

    public function testRejectsAnUnsupportedProtocol(): void
    {
        $config = self::createStub(EmbedderConfig::class);
        $config->method('getEmbeddingApiProtocol')->willReturn('unsupported');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Embedding API protocol "unsupported" is not supported.');

        (new EmbedderClientPool($config, []))->getClient();
    }
}
