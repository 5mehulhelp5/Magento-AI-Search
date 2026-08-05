<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync;

use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingBatch;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Item;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Upsert\Bulk;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Upsert\Document;
use UnexpectedValueException;

class Upsert
{
    public function __construct(
        private readonly Bulk $bulk
    ) {
    }

    /**
     * @param list<list<float>> $vectors
     */
    public function execute(ProcessingBatch $batch, array $vectors): Result
    {
        return $this->bulk->execute(
            $this->createDocuments($batch, $vectors)
        );
    }

    /**
     * @param list<list<float>> $vectors
     * @return list<Document>
     */
    private function createDocuments(ProcessingBatch $batch, array $vectors): array
    {
        $items = $batch->getItems();

        if (count($vectors) !== count($items)) {
            throw new UnexpectedValueException('Embedding vectors do not match the upsert batch.');
        }

        $documents = [];

        foreach ($items as $index => $item) {
            $documents[] = new Document(
                new Item(
                    $item->backlogId,
                    $item->backlogVersion,
                    $item->backlogUpdatedAt,
                    $item->chunkId,
                    $item->sourceEntityType,
                    $item->sourceEntityId
                ),
                $item->storeId,
                $item->sourceCode,
                $item->chunkIndex,
                $item->content,
                $item->contentHash,
                $vectors[$index]
            );
        }

        return $documents;
    }
}
