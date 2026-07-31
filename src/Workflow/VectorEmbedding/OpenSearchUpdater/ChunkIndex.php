<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow\VectorEmbedding\OpenSearchUpdater;

use Magento\Elasticsearch\SearchAdapter\ConnectionManager;
use Magento\OpenSearch\Model\SearchClient;
use RuntimeException;
use UnexpectedValueException;

class ChunkIndex
{
    private const string INDEX_NAME = 'davidbel_ai_search_chunks';
    private const int VECTOR_DIMENSIONS = 768;

    private ?SearchClient $client = null;
    private bool $indexReady = false;

    public function __construct(
        private readonly ConnectionManager $connectionManager
    ) {
    }

    /**
     * @param list<ChunkDocument> $documents
     */
    public function upsert(array $documents): BulkResult
    {
        if ($documents === []) {
            return new BulkResult([], []);
        }

        $this->ensureIndex();
        $response = $this->getClient()->bulkQuery([
            'body' => $this->createBulkBody($documents),
            'refresh' => 'wait_for',
        ]);

        return $this->createBulkResult($response, $documents);
    }

    private function ensureIndex(): void
    {
        if ($this->indexReady) {
            return;
        }

        if (!$this->getClient()->indexExists(self::INDEX_NAME)) {
            $this->createIndex();
        }

        $this->indexReady = true;
    }

    private function createIndex(): void
    {
        $this->getClient()->createIndex(
            self::INDEX_NAME,
            [
                'settings' => [
                    'index' => [
                        'knn' => true,
                    ],
                ],
                'mappings' => [
                    'dynamic' => 'strict',
                    'properties' => $this->getMappingProperties(),
                ],
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getMappingProperties(): array
    {
        return [
            'chunk_id' => ['type' => 'long'],
            'source_entity_type' => ['type' => 'keyword'],
            'source_entity_id' => ['type' => 'long'],
            'store_id' => ['type' => 'integer'],
            'source_code' => ['type' => 'keyword'],
            'chunk_index' => ['type' => 'integer'],
            'content' => ['type' => 'text'],
            'content_hash' => ['type' => 'keyword'],
            'vector' => [
                'type' => 'knn_vector',
                'dimension' => self::VECTOR_DIMENSIONS,
                'method' => [
                    'name' => 'hnsw',
                    'engine' => 'faiss',
                    'space_type' => 'l2',
                ],
            ],
        ];
    }

    private function getClient(): SearchClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $client = $this->connectionManager->getConnection();

        if (!$client instanceof SearchClient) {
            throw new RuntimeException('Magento is not configured to use OpenSearch.');
        }

        $this->client = $client;

        return $this->client;
    }

    /**
     * @param list<ChunkDocument> $documents
     * @return list<array<string, mixed>>
     */
    private function createBulkBody(array $documents): array
    {
        $body = [];

        foreach ($documents as $document) {
            $body[] = [
                'index' => [
                    '_index' => self::INDEX_NAME,
                    '_id' => (string) $document->chunkId,
                ],
            ];
            $body[] = [
                'chunk_id' => $document->chunkId,
                'source_entity_type' => $document->sourceEntityType,
                'source_entity_id' => $document->sourceEntityId,
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
     * @param list<ChunkDocument> $documents
     */
    private function createBulkResult(array $response, array $documents): BulkResult
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

        return new BulkResult($successfulDocuments, $failedDocuments);
    }

    /**
     * @param list<mixed> $items
     * @param list<ChunkDocument> $documents
     * @return array{list<ChunkDocument>, list<ChunkDocument>}
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

    private function isSuccessfulItem(mixed $item, ChunkDocument $document): bool
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
    private function isExpectedDocument(array $operation, ChunkDocument $document): bool
    {
        return ($operation['_id'] ?? null) === (string) $document->chunkId;
    }

    private function isSuccessfulStatus(mixed $status): bool
    {
        return is_int($status) && $status >= 200 && $status < 300;
    }
}
