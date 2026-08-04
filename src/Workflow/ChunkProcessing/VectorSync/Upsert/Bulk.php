<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync\Upsert;

use DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync\Index;
use DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync\Item;
use DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync\Result;
use DavidBel\AiSearch\Workflow\ChunkProcessing\VectorSync\ResultFactory;
use UnexpectedValueException;

class Bulk
{
    public function __construct(
        private readonly Index $index,
        private readonly ResultFactory $resultFactory
    ) {
    }

    /**
     * @param list<Document> $documents
     */
    public function execute(array $documents): Result
    {
        if ($documents === []) {
            return $this->createResult([], []);
        }

        $response = $this->index->bulkQuery(
            $this->createBulkBody($documents)
        );

        return $this->createBulkResult($response, $documents);
    }

    /**
     * @param list<Document> $documents
     * @return list<array<string, mixed>>
     */
    private function createBulkBody(array $documents): array
    {
        $body = [];

        foreach ($documents as $document) {
            $body[] = [
                'index' => [
                    '_index' => $this->index->getName(),
                    '_id' => (string) $document->item->chunkId,
                ],
            ];
            $body[] = [
                'chunk_id' => $document->item->chunkId,
                'source_entity_type' => $document->item->sourceEntityType,
                'source_entity_id' => $document->item->sourceEntityId,
                'store_id' => $document->storeId,
                'source_code' => $document->sourceCode,
                'chunk_index' => $document->chunkIndex,
                'content' => $document->content,
                'content_hash' => $document->contentHash,
                'vector' => $document->vector,
            ];
        }

        return $body;
    }

    /**
     * @param array<array-key, mixed> $response
     * @param list<Document> $documents
     */
    private function createBulkResult(array $response, array $documents): Result
    {
        $errors = $response['errors'] ?? null;
        $items = $response['items'] ?? null;

        if (!is_bool($errors) || !is_array($items) || !array_is_list($items)) {
            throw new UnexpectedValueException('OpenSearch returned an invalid bulk response.');
        }

        if (count($items) !== count($documents)) {
            throw new UnexpectedValueException('OpenSearch returned an unexpected bulk item count.');
        }

        [$successfulDocuments, $failedDocuments] = $this->categorizeDocuments($items, $documents);

        if ($errors !== ($failedDocuments !== [])) {
            throw new UnexpectedValueException('OpenSearch returned inconsistent bulk error information.');
        }

        return $this->createResult($successfulDocuments, $failedDocuments);
    }

    /**
     * @param list<mixed> $items
     * @param list<Document> $documents
     * @return array{list<Document>, list<Document>}
     */
    private function categorizeDocuments(array $items, array $documents): array
    {
        $successfulDocuments = [];
        $failedDocuments = [];

        foreach ($items as $index => $item) {
            $document = $documents[$index];

            if ($this->isSuccessfulItem($item, $document)) {
                $successfulDocuments[] = $document;

                continue;
            }

            $failedDocuments[] = $document;
        }

        return [$successfulDocuments, $failedDocuments];
    }

    private function isSuccessfulItem(mixed $item, Document $document): bool
    {
        $operation = is_array($item) ? ($item['index'] ?? null) : null;

        if (!is_array($operation) || !$this->isExpectedDocument($operation, $document)) {
            throw new UnexpectedValueException('OpenSearch returned an invalid bulk item.');
        }

        return $this->isSuccessfulStatus($operation['status'] ?? null);
    }

    /**
     * @param array<array-key, mixed> $operation
     */
    private function isExpectedDocument(array $operation, Document $document): bool
    {
        return ($operation['_id'] ?? null) === (string) $document->item->chunkId;
    }

    private function isSuccessfulStatus(mixed $status): bool
    {
        return is_int($status) && $status >= 200 && $status < 300;
    }

    /**
     * @param list<Document> $successfulDocuments
     * @param list<Document> $failedDocuments
     */
    private function createResult(
        array $successfulDocuments,
        array $failedDocuments
    ): Result {
        return $this->resultFactory->create([
            'successfulItems' => $this->getItems($successfulDocuments),
            'failedItems' => $this->getItems($failedDocuments),
        ]);
    }

    /**
     * @param list<Document> $documents
     * @return list<Item>
     */
    private function getItems(array $documents): array
    {
        return array_map(
            static fn (Document $document): Item => $document->item,
            $documents
        );
    }
}
