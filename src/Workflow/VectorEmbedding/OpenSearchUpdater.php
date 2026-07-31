<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow\VectorEmbedding;

use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as EmbeddingBacklogResource;
use DavidBel\AiSearch\Workflow\VectorEmbedding\OpenSearchUpdater\BulkResult;
use DavidBel\AiSearch\Workflow\VectorEmbedding\OpenSearchUpdater\ChunkDocument;
use DavidBel\AiSearch\Workflow\VectorEmbedding\OpenSearchUpdater\ChunkIndex;
use Throwable;
use UnexpectedValueException;

class OpenSearchUpdater
{
    private const string OPENSEARCH_ERROR_CATEGORY = 'opensearch';

    public function __construct(
        private readonly ChunkIndex $chunkIndex
    ) {
    }

    /**
     * @param list<list<float>> $vectors
     */
    public function update(
        EmbeddingBacklogResource $resource,
        EmbeddingBatch $batch,
        array $vectors
    ): BulkResult {
        try {
            $result = $this->chunkIndex->upsert(
                $this->createDocuments($batch, $vectors)
            );
        } catch (Throwable $throwable) {
            $resource->markFailedByIds(
                $batch->getBacklogIds(),
                self::OPENSEARCH_ERROR_CATEGORY
            );

            throw $throwable;
        }

        $resource->markFailedByIds(
            $result->getFailedBacklogIds(),
            self::OPENSEARCH_ERROR_CATEGORY
        );

        return $result;
    }

    /**
     * @param list<list<float>> $vectors
     * @return list<ChunkDocument>
     */
    private function createDocuments(EmbeddingBatch $batch, array $vectors): array
    {
        $inputs = $batch->getInputs();

        if (count($vectors) !== count($inputs)) {
            throw new UnexpectedValueException('Embedding vectors do not match the update batch.');
        }

        $documents = [];

        foreach ($inputs as $index => $input) {
            $documents[] = new ChunkDocument(
                $input->backlogId,
                $input->chunkId,
                $input->sourceEntityType,
                $input->sourceEntityId,
                $input->storeId,
                $input->sourceCode,
                $input->chunkIndex,
                $input->content,
                $input->contentHash,
                $vectors[$index]
            );
        }

        return $documents;
    }
}
