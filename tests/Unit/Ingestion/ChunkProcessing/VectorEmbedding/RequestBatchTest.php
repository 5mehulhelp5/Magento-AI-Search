<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Ingestion\ChunkProcessing\VectorEmbedding;

use DavidBel\AiSearch\Client\Embedding\EmbeddingInput;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingBatch;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingItem;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorEmbedding\RequestBatch;
use PHPUnit\Framework\TestCase;

class RequestBatchTest extends TestCase
{
    public function testDeduplicatesInputsAndExpandsVectorsInOriginalOrder(): void
    {
        $requestBatch = new RequestBatch(new ProcessingBatch([
            $this->createItem(10, 'same text'),
            $this->createItem(20, 'same text'),
            $this->createItem(30, 'same text', 'Title'),
            $this->createItem(40, 'other text'),
        ]));

        self::assertSame(
            [
                [null, 'same text'],
                ['Title', 'same text'],
                [null, 'other text'],
            ],
            array_map(
                static fn (EmbeddingInput $input): array => [$input->title, $input->text],
                $requestBatch->getUniqueInputs()
            )
        );
        self::assertSame(
            [[0.1], [0.1], [0.2], [0.3]],
            $requestBatch->expandVectors([[0.1], [0.2], [0.3]])
        );
    }

    private function createItem(
        int $backlogId,
        string $content,
        ?string $title = null
    ): ProcessingItem {
        return new ProcessingItem(
            $backlogId,
            1,
            '2026-08-19 10:00:00',
            $backlogId + 100,
            'product',
            $backlogId,
            1,
            'catalog_product_' . $backlogId,
            0,
            $content,
            'hash-' . $backlogId,
            $title
        );
    }
}
