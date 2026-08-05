<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Cron;

use DavidBel\AiSearch\Cron\VectorEmbedding as VectorEmbeddingCron;
use DavidBel\AiSearch\Workflow\VectorEmbedding as VectorEmbeddingWorkflow;
use PHPUnit\Framework\TestCase;

class VectorEmbeddingTest extends TestCase
{
    public function testDelegatesToTheVectorEmbeddingWorkflow(): void
    {
        $vectorEmbedding = $this->createMock(VectorEmbeddingWorkflow::class);
        $vectorEmbedding->expects(self::once())
            ->method('execute');

        (new VectorEmbeddingCron($vectorEmbedding))->execute();
    }
}
