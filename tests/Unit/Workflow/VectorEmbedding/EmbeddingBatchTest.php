<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Workflow\VectorEmbedding;

use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingBatch;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingInput;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EmbeddingBatchTest extends TestCase
{
    public function testRejectsAnEmptyBatch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'An embedding batch must contain at least one input.'
        );

        new EmbeddingBatch([]);
    }

    public function testExposesBacklogIdsContentsAndLastInput(): void
    {
        $first = self::input(10, 'first');
        $last = self::input(20, 'last');
        $batch = new EmbeddingBatch([$first, $last]);

        self::assertSame([10, 20], $batch->getBacklogIds());
        self::assertSame(['first', 'last'], $batch->getContents());
        self::assertSame($last, $batch->getLastInput());
    }

    private static function input(int $backlogId, string $content): EmbeddingInput
    {
        return new EmbeddingInput(
            $backlogId,
            '2026-07-31 10:00:00',
            100,
            'product',
            200,
            1,
            'description',
            0,
            $content,
            'content-hash'
        );
    }
}
