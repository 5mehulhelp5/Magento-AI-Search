<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Upsert;

use DavidBel\AiSearch\Client\OpenSearch;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\FailedItem;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\OpenSearchErrorMapper;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Result;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\ResultFactory;
use UnexpectedValueException;

class Bulk
{
    public function __construct(
        private readonly OpenSearch $openSearch,
        private readonly OpenSearchErrorMapper $openSearchErrorMapper,
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

        $response = $this->openSearch->bulkQuery(
            $this->getIndexVersion($documents),
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
                    '_id' => (string) $document->item->chunkId,
                ],
            ];
            $body[] = [
                'source_entity_type' => $document->item->sourceEntityType,
                'source_entity_id' => $document->item->sourceEntityId,
                'store_id' => $document->storeId,
                'source_code' => $document->sourceCode,
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

        [$successfulDocuments, $failedItems] = $this->categorizeDocuments($items, $documents);

        if ($errors !== ($failedItems !== [])) {
            throw new UnexpectedValueException('OpenSearch returned inconsistent bulk error information.');
        }

        return $this->createResult($successfulDocuments, $failedItems);
    }

    /**
     * @param list<mixed> $items
     * @param list<Document> $documents
     * @return array{list<Document>, list<FailedItem>}
     */
    private function categorizeDocuments(array $items, array $documents): array
    {
        $successfulDocuments = [];
        $failedItems = [];

        foreach ($items as $index => $item) {
            $document = $documents[$index];
            $operation = $this->getOperation($item, $document);

            if ($this->isSuccessfulStatus($operation['status'] ?? null)) {
                $successfulDocuments[] = $document;

                continue;
            }

            $failedItems[] = new FailedItem(
                $document->item,
                $this->openSearchErrorMapper->map($operation)
            );
        }

        return [$successfulDocuments, $failedItems];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function getOperation(mixed $item, Document $document): array
    {
        $operation = is_array($item) ? ($item['index'] ?? null) : null;

        if (!is_array($operation) || !$this->isExpectedDocument($operation, $document)) {
            throw new UnexpectedValueException('OpenSearch returned an invalid bulk item.');
        }

        return $operation;
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
     * @param list<FailedItem> $failedItems
     */
    private function createResult(
        array $successfulDocuments,
        array $failedItems
    ): Result {
        return $this->resultFactory->create([
            'successfulItems' => $this->getItems($successfulDocuments),
            'failedItems' => $failedItems,
        ]);
    }

    /**
     * @param list<Document> $documents
     * @return list<\DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Item>
     */
    private function getItems(array $documents): array
    {
        $items = [];

        foreach ($documents as $document) {
            $items[] = $document->item;
        }

        return $items;
    }

    /**
     * @param non-empty-list<Document> $documents
     */
    private function getIndexVersion(array $documents): int
    {
        $indexVersion = $documents[0]->item->indexVersion;

        foreach ($documents as $document) {
            if ($document->item->indexVersion !== $indexVersion) {
                throw new UnexpectedValueException(
                    'An OpenSearch bulk request must contain one index version.'
                );
            }
        }

        return $indexVersion;
    }
}
