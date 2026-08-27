<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Client;

use DavidBel\AiSearch\Config\EmbedderConfig;
use DavidBel\AiSearch\Config\SearchConfig;
use DavidBel\AiSearch\Indexer\Versioning\IndexName;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndexProvider;
use Magento\Elasticsearch\SearchAdapter\ConnectionManager;
use Magento\OpenSearch\Model\SearchClient;
use RuntimeException;
use UnexpectedValueException;

class OpenSearch
{
    private ?SearchClient $client = null;

    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly EmbedderConfig $embedderConfig,
        private readonly SearchConfig $searchConfig,
        private readonly IndexName $indexName,
        private readonly PhysicalIndexProvider $physicalIndexProvider
    ) {
    }

    public function createIndex(PhysicalIndex $physicalIndex): void
    {
        if ($this->indexExists($physicalIndex->indexName)) {
            $this->assertMatchingFingerprint($physicalIndex);

            return;
        }

        $this->getClient()->createIndex(
            $physicalIndex->indexName,
            [
                'settings' => [
                    'index' => [
                        'knn' => true,
                    ],
                ],
                'mappings' => [
                    'dynamic' => 'strict',
                    '_meta' => [
                        'davidbel_ai_search' => [
                            'version' => $physicalIndex->number,
                            'fingerprint' => $physicalIndex->configurationFingerprint,
                        ],
                    ],
                    'properties' => $this->getMappingProperties(),
                ],
            ]
        );
    }

    public function indexExists(string $indexName): bool
    {
        return $this->getClient()->indexExists($indexName);
    }

    /**
     * @param list<array<string, mixed>> $body
     * @return array<array-key, mixed>
     */
    public function bulkQuery(int $indexVersion, array $body): array
    {
        $physicalIndex = $this->physicalIndexProvider->getForIngestion();

        if ($physicalIndex === null) {
            throw new RuntimeException('A writable OpenSearch index is not available.');
        }

        if ($physicalIndex->number !== $indexVersion) {
            throw new RuntimeException(
                'The backlog item belongs to an obsolete OpenSearch index version.'
            );
        }

        return $this->getClient()->getOpenSearchClient()->bulk([
            'index' => $physicalIndex->indexName,
            'body' => $body,
            'refresh' => 'wait_for',
        ]);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<array-key, mixed>
     */
    public function search(PhysicalIndex $physicalIndex, array $body): array
    {
        return $this->getClient()->query([
            'index' => $physicalIndex->indexName,
            'body' => $body,
        ]);
    }

    public function activateIndex(PhysicalIndex $physicalIndex): void
    {
        $alias = $this->indexName->getAlias();
        $currentIndexNames = $this->getAliasIndexNames($alias);

        if ($currentIndexNames === [$physicalIndex->indexName]) {
            return;
        }

        $actions = [];

        foreach ($currentIndexNames as $currentIndexName) {
            $actions[] = [
                'remove' => [
                    'alias' => $alias,
                    'index' => $currentIndexName,
                ],
            ];
        }

        $actions[] = [
            'add' => [
                'alias' => $alias,
                'index' => $physicalIndex->indexName,
            ],
        ];

        $this->getClient()->getOpenSearchClient()->indices()->updateAliases([
            'body' => ['actions' => $actions],
        ]);
    }

    public function deleteIndex(string $indexName): void
    {
        if (!$this->indexExists($indexName)) {
            return;
        }

        $this->getClient()->deleteIndex($indexName);
    }

    /**
     * @return list<string>
     */
    public function getVersionIndexNames(): array
    {
        $alias = $this->indexName->getAlias();
        $indexes = $this->getClient()->getOpenSearchClient()->indices()->get([
            'index' => $alias . '_v*',
            'allow_no_indices' => true,
            'ignore_unavailable' => true,
        ]);

        $indexNames = [];
        $pattern = sprintf('/^%s_v[1-9][0-9]*$/', preg_quote($alias, '/'));

        foreach (array_keys($indexes) as $indexName) {
            if (!is_string($indexName) || preg_match($pattern, $indexName) !== 1) {
                continue;
            }

            $indexNames[] = $indexName;
        }

        sort($indexNames);

        return $indexNames;
    }

    /**
     * @return array<string, mixed>
     */
    private function getMappingProperties(): array
    {
        return [
            'source_entity_type' => ['type' => 'keyword'],
            'source_entity_id' => ['type' => 'long'],
            'store_id' => ['type' => 'integer'],
            'source_code' => ['type' => 'keyword'],
            'vector' => [
                'type' => 'knn_vector',
                'dimension' => $this->embedderConfig->getVectorDimensions(),
                'method' => [
                    'name' => $this->searchConfig->getVectorMethod(),
                    'engine' => $this->searchConfig->getVectorEngine(),
                    'space_type' => $this->searchConfig->getVectorSpace(),
                ],
            ],
        ];
    }

    private function assertMatchingFingerprint(PhysicalIndex $physicalIndex): void
    {
        $mapping = $this->getClient()->getMapping(['index' => $physicalIndex->indexName]);
        $indexMapping = $mapping[$physicalIndex->indexName] ?? null;

        if (!is_array($indexMapping)) {
            throw new UnexpectedValueException('OpenSearch returned an invalid index mapping.');
        }

        $mappings = $indexMapping['mappings'] ?? null;

        if (!is_array($mappings)) {
            throw new UnexpectedValueException('OpenSearch returned an invalid index mapping.');
        }

        $metadata = $mappings['_meta'] ?? null;
        $moduleMetadata = is_array($metadata) ? ($metadata['davidbel_ai_search'] ?? null) : null;
        $fingerprint = is_array($moduleMetadata) ? ($moduleMetadata['fingerprint'] ?? null) : null;

        if ($fingerprint !== $physicalIndex->configurationFingerprint) {
            throw new UnexpectedValueException('The existing OpenSearch index has a different configuration.');
        }
    }

    /**
     * @return list<string>
     */
    private function getAliasIndexNames(string $alias): array
    {
        if (!$this->getClient()->existsAlias($alias)) {
            return [];
        }

        $aliasData = $this->getClient()->getAlias($alias);
        $indexNames = [];

        foreach (array_keys($aliasData) as $indexName) {
            if (!is_string($indexName)) {
                throw new UnexpectedValueException('OpenSearch returned an invalid index alias.');
            }

            $indexNames[] = $indexName;
        }

        sort($indexNames);

        return $indexNames;
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
