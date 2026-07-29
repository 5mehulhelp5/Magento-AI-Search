<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Workflow;

use DavidBel\AiSearch\Workflow\VectorEmbedding;
use PHPUnit\Framework\TestCase;

class VectorEmbeddingTest extends TestCase
{
    public function testReportsThatNoEmbeddingsWereProcessed(): void
    {
        self::assertSame(0, (new VectorEmbedding())->execute());
    }
}
