<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync;

use Magento\Elasticsearch\SearchAdapter\ConnectionManager;
use Magento\OpenSearch\Model\SearchClient;
use RuntimeException;

class Index
{
    private const string INDEX_NAME = 'davidbel_ai_search_chunks';
    private const int VECTOR_DIMENSIONS = 768;

    private ?SearchClient $client = null;
    private bool $indexReady = false;

    public function __construct(
        private readonly ConnectionManager $connectionManager
    ) {
    }

    public function getName(): string
    {
        return self::INDEX_NAME;
    }

    /**
     * @param list<array<string, mixed>> $body
     * @return array<array-key, mixed>
     */
    public function bulkQuery(array $body): array
    {
        $this->ensureIndex();

        return $this->getClient()->bulkQuery([
            'body' => $body,
            'refresh' => 'wait_for',
        ]);
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
}
