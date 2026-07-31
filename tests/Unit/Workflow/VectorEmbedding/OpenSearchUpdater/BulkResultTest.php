<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Workflow\VectorEmbedding\OpenSearchUpdater;

use DavidBel\AiSearch\Workflow\VectorEmbedding\OpenSearchUpdater\BulkResult;
use DavidBel\AiSearch\Workflow\VectorEmbedding\OpenSearchUpdater\ChunkDocument;
use PHPUnit\Framework\TestCase;

class BulkResultTest extends TestCase
{
    public function testReportsSuccessfulAndFailedDocuments(): void
    {
        $result = new BulkResult(
            [
                self::document(1, 'product', 50),
                self::document(2, 'product', 50),
                self::document(3, 'category', 70),
                self::document(4, 'product', 60),
            ],
            [self::document(5, 'product', 80)]
        );

        self::assertSame([1, 2, 3, 4], $result->getSuccessfulBacklogIds());
        self::assertSame([5], $result->getFailedBacklogIds());
        self::assertSame(
            [50, 60],
            $result->getSuccessfulSourceEntityIds('product')
        );
        self::assertSame(4, $result->getSuccessfulCount());
    }

    private static function document(
        int $backlogId,
        string $sourceEntityType,
        int $sourceEntityId
    ): ChunkDocument {
        return new ChunkDocument(
            $backlogId,
            $backlogId + 100,
            $sourceEntityType,
            $sourceEntityId,
            1,
            'description',
            0,
            'content',
            'content-hash',
            [0.1]
        );
    }
}
