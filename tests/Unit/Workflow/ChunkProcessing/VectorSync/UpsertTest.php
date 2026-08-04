<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Workflow\ChunkProcessing\VectorSync;

use DavidBel\AiSearch\Workflow\ChunkProcessing\ProcessingBatch;
use DavidBel\AiSearch\Workflow\ChunkProcessing\ProcessingItem;
use DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync\Result;
use DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync\Upsert;
use DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync\Upsert\Bulk;
use DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync\Upsert\Document;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class UpsertTest extends TestCase
{
    public function testMapsProcessingItemsAndVectorsToDocuments(): void
    {
        $result = self::createStub(Result::class);
        $bulk = $this->createMock(Bulk::class);
        $bulk->expects(self::once())
            ->method('execute')
            ->with(self::callback(static function (array $documents): bool {
                self::assertCount(1, $documents);
                self::assertContainsOnlyInstancesOf(Document::class, $documents);
                self::assertSame(10, $documents[0]->item->backlogId);
                self::assertSame(2, $documents[0]->item->backlogVersion);
                self::assertSame(42, $documents[0]->item->chunkId);
                self::assertSame([0.1, 0.2], $documents[0]->vector);

                return true;
            }))
            ->willReturn($result);

        self::assertSame(
            $result,
            (new Upsert($bulk))->execute(
                new ProcessingBatch([$this->createItem()]),
                [[0.1, 0.2]]
            )
        );
    }

    public function testRejectsAVectorCountThatDoesNotMatchTheBatch(): void
    {
        $bulk = $this->createMock(Bulk::class);
        $bulk->expects(self::never())->method('execute');
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Embedding vectors do not match the upsert batch.');

        (new Upsert($bulk))->execute(new ProcessingBatch([$this->createItem()]), []);
    }

    private function createItem(): ProcessingItem
    {
        return new ProcessingItem(
            10,
            2,
            '2026-08-04 10:00:00',
            42,
            'product',
            99,
            1,
            'catalog_product_99',
            0,
            'text',
            'hash'
        );
    }
}
