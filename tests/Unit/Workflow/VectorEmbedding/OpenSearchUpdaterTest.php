<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Workflow\VectorEmbedding;

use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingBatch;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingInput;
use DavidBel\AiSearch\Workflow\VectorEmbedding\OpenSearchUpdater;
use DavidBel\AiSearch\Workflow\VectorEmbedding\OpenSearchUpdater\BulkResult;
use DavidBel\AiSearch\Workflow\VectorEmbedding\OpenSearchUpdater\ChunkDocument;
use DavidBel\AiSearch\Workflow\VectorEmbedding\OpenSearchUpdater\ChunkIndex;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnexpectedValueException;

class OpenSearchUpdaterTest extends TestCase
{
    public function testMapsBatchToDocumentsAndMarksRejectedDocumentsFailed(): void
    {
        $batch = self::batch(1, 2);
        $vectors = [[0.1], [0.2]];
        $expectedDocuments = [
            self::document(1, [0.1]),
            self::document(2, [0.2]),
        ];
        $result = new BulkResult(
            [$expectedDocuments[0]],
            [$expectedDocuments[1]]
        );
        $chunkIndex = $this->createMock(ChunkIndex::class);
        $chunkIndex->expects(self::once())
            ->method('upsert')
            ->with(self::callback(
                static function (array $documents) use ($expectedDocuments): bool {
                    self::assertEquals($expectedDocuments, $documents);

                    return true;
                }
            ))
            ->willReturn($result);
        $resource = $this->createMock(EmbeddingBacklog::class);
        $resource->expects(self::once())
            ->method('markFailedByIds')
            ->with([2], 'opensearch');

        self::assertSame(
            $result,
            (new OpenSearchUpdater($chunkIndex))->update(
                $resource,
                $batch,
                $vectors
            )
        );
    }

    public function testRejectsMismatchedVectorsAndMarksWholeBatchFailed(): void
    {
        $batch = self::batch(1, 2);
        $chunkIndex = $this->createMock(ChunkIndex::class);
        $chunkIndex->expects(self::never())
            ->method('upsert');
        $resource = $this->createMock(EmbeddingBacklog::class);
        $resource->expects(self::once())
            ->method('markFailedByIds')
            ->with([1, 2], 'opensearch');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'Embedding vectors do not match the update batch.'
        );

        (new OpenSearchUpdater($chunkIndex))->update(
            $resource,
            $batch,
            [[0.1]]
        );
    }

    public function testMarksWholeBatchFailedWhenIndexingThrows(): void
    {
        $failure = new RuntimeException('OpenSearch unavailable');
        $batch = self::batch(5);
        $chunkIndex = $this->createMock(ChunkIndex::class);
        $chunkIndex->expects(self::once())
            ->method('upsert')
            ->willThrowException($failure);
        $resource = $this->createMock(EmbeddingBacklog::class);
        $resource->expects(self::once())
            ->method('markFailedByIds')
            ->with([5], 'opensearch');

        $this->expectExceptionObject($failure);

        (new OpenSearchUpdater($chunkIndex))->update(
            $resource,
            $batch,
            [[0.5]]
        );
    }

    private static function batch(int ...$backlogIds): EmbeddingBatch
    {
        $inputs = [];

        foreach ($backlogIds as $backlogId) {
            $inputs[] = new EmbeddingInput(
                $backlogId,
                '2026-07-31 10:00:00',
                $backlogId + 100,
                'product',
                $backlogId + 500,
                1,
                'description',
                $backlogId,
                'content-' . $backlogId,
                'hash-' . $backlogId
            );
        }

        return new EmbeddingBatch($inputs);
    }

    /**
     * @param list<float> $vector
     */
    private static function document(int $backlogId, array $vector): ChunkDocument
    {
        return new ChunkDocument(
            $backlogId,
            $backlogId + 100,
            'product',
            $backlogId + 500,
            1,
            'description',
            $backlogId,
            'content-' . $backlogId,
            'hash-' . $backlogId,
            $vector
        );
    }
}
