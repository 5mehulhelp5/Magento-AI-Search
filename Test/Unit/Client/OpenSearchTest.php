<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Client;

use DavidBel\AiSearch\Client\OpenSearch;
use DavidBel\AiSearch\Config\EmbedderConfig;
use DavidBel\AiSearch\Config\SearchConfig;
use DavidBel\AiSearch\Indexer\Versioning\IndexName;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex\QueryConfigurationSnapshot;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndexProvider;
use Magento\Elasticsearch\SearchAdapter\ConnectionManager;
use Magento\OpenSearch\Model\SearchClient;
use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use UnexpectedValueException;

class OpenSearchTest extends TestCase
{
    public function testCreatesIndexWithVectorMapping(): void
    {
        $client = $this->createMock(SearchClient::class);
        $client->expects(self::once())->method('indexExists')->with('alias_v2')->willReturn(false);
        $client->expects(self::once())
            ->method('createIndex')
            ->with('alias_v2', $this->expectedIndexConfiguration());

        $this->createOpenSearch($client)->createIndex($this->physicalIndex());
    }

    public function testKeepsExistingIndexWithMatchingFingerprint(): void
    {
        $client = $this->createMock(SearchClient::class);
        $client->method('indexExists')->willReturn(true);
        $client->expects(self::once())
            ->method('getMapping')
            ->with(['index' => 'alias_v2'])
            ->willReturn([
                'alias_v2' => [
                    'mappings' => [
                        '_meta' => ['davidbel_ai_search' => ['fingerprint' => 'fingerprint']],
                    ],
                ],
            ]);
        $client->expects(self::never())->method('createIndex');

        $this->createOpenSearch($client)->createIndex($this->physicalIndex());
    }

    /**
     * @param array<array-key, mixed> $mapping
     */
    #[DataProvider('invalidMappings')]
    public function testRejectsInvalidOrDifferentExistingMapping(array $mapping, string $message): void
    {
        $client = self::createStub(SearchClient::class);
        $client->method('indexExists')->willReturn(true);
        $client->method('getMapping')->willReturn($mapping);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        $this->createOpenSearch($client)->createIndex($this->physicalIndex());
    }

    /**
     * @return array<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidMappings(): array
    {
        return [
            'missing index' => [[], 'invalid index mapping'],
            'invalid mappings' => [['alias_v2' => ['mappings' => null]], 'invalid index mapping'],
            'missing metadata' => [['alias_v2' => ['mappings' => []]], 'different configuration'],
            'invalid metadata' => [
                ['alias_v2' => ['mappings' => ['_meta' => ['davidbel_ai_search' => null]]]],
                'different configuration',
            ],
            'different fingerprint' => [
                [
                    'alias_v2' => [
                        'mappings' => [
                            '_meta' => ['davidbel_ai_search' => ['fingerprint' => 'different']],
                        ],
                    ],
                ],
                'different configuration',
            ],
        ];
    }

    public function testBulkQueryUsesWritablePhysicalIndex(): void
    {
        $openSearchClient = $this->createMock(Client::class);
        $openSearchClient->expects(self::once())
            ->method('bulk')
            ->with(['index' => 'alias_v2', 'body' => [['index' => []]], 'refresh' => 'wait_for'])
            ->willReturn(['errors' => false]);
        $client = $this->createMock(SearchClient::class);
        $client->expects(self::once())
            ->method('getOpenSearchClient')
            ->willReturn($openSearchClient);
        $provider = self::createStub(PhysicalIndexProvider::class);
        $provider->method('getForIngestion')->willReturn($this->physicalIndex());

        self::assertSame(
            ['errors' => false],
            $this->createOpenSearch($client, $provider)->bulkQuery(2, [['index' => []]])
        );
    }

    public function testBulkQueryRequiresWritableIndex(): void
    {
        $provider = self::createStub(PhysicalIndexProvider::class);
        $provider->method('getForIngestion')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('writable OpenSearch index');

        $this->createOpenSearch(self::createStub(SearchClient::class), $provider)->bulkQuery(2, []);
    }

    public function testBulkQueryRejectsObsoleteIndexVersion(): void
    {
        $provider = self::createStub(PhysicalIndexProvider::class);
        $provider->method('getForIngestion')->willReturn($this->physicalIndex());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('obsolete OpenSearch index version');

        $this->createOpenSearch(self::createStub(SearchClient::class), $provider)->bulkQuery(1, []);
    }

    public function testSearchTargetsPhysicalIndex(): void
    {
        $client = $this->createMock(SearchClient::class);
        $client->expects(self::once())
            ->method('query')
            ->with(['index' => 'alias_v2', 'body' => ['query' => ['match_all' => new stdClass()]]])
            ->willReturn(['hits' => []]);

        self::assertSame(
            ['hits' => []],
            $this->createOpenSearch($client)->search(
                $this->physicalIndex(),
                ['query' => ['match_all' => new stdClass()]]
            )
        );
    }

    public function testActivatesIndexWhenAliasDoesNotExist(): void
    {
        $indices = $this->createMock(IndicesNamespace::class);
        $indices->expects(self::once())
            ->method('updateAliases')
            ->with([
                'body' => [
                    'actions' => [['add' => ['alias' => 'alias', 'index' => 'alias_v2']]],
                ],
            ]);
        $client = $this->createSearchClientWithIndices($indices);
        $client->expects(self::once())->method('existsAlias')->with('alias')->willReturn(false);

        $this->createOpenSearch($client)->activateIndex($this->physicalIndex());
    }

    public function testReplacesAllExistingAliasIndexesInSortedOrder(): void
    {
        $indices = $this->createMock(IndicesNamespace::class);
        $indices->expects(self::once())
            ->method('updateAliases')
            ->with([
                'body' => [
                    'actions' => [
                        ['remove' => ['alias' => 'alias', 'index' => 'alias_v1']],
                        ['remove' => ['alias' => 'alias', 'index' => 'alias_v3']],
                        ['add' => ['alias' => 'alias', 'index' => 'alias_v2']],
                    ],
                ],
            ]);
        $client = $this->createSearchClientWithIndices($indices);
        $client->expects(self::once())->method('existsAlias')->with('alias')->willReturn(true);
        $client->expects(self::once())
            ->method('getAlias')
            ->with('alias')
            ->willReturn(['alias_v3' => [], 'alias_v1' => []]);

        $this->createOpenSearch($client)->activateIndex($this->physicalIndex());
    }

    public function testDoesNotUpdateAliasAlreadyOnTargetIndex(): void
    {
        $indices = $this->createMock(IndicesNamespace::class);
        $indices->expects(self::never())->method('updateAliases');
        $client = $this->createMock(SearchClient::class);
        $client->expects(self::never())->method('getOpenSearchClient');
        $client->expects(self::once())->method('existsAlias')->with('alias')->willReturn(true);
        $client->expects(self::once())
            ->method('getAlias')
            ->with('alias')
            ->willReturn(['alias_v2' => []]);

        $this->createOpenSearch($client)->activateIndex($this->physicalIndex());
    }

    public function testRejectsInvalidAliasIndexName(): void
    {
        $client = self::createStub(SearchClient::class);
        $client->method('existsAlias')->willReturn(true);
        $client->method('getAlias')->willReturn([10 => []]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('invalid index alias');

        $this->createOpenSearch($client)->activateIndex($this->physicalIndex());
    }

    public function testDeletesOnlyExistingIndex(): void
    {
        $missingClient = $this->createMock(SearchClient::class);
        $missingClient->method('indexExists')->willReturn(false);
        $missingClient->expects(self::never())->method('deleteIndex');
        $this->createOpenSearch($missingClient)->deleteIndex('missing');

        $existingClient = $this->createMock(SearchClient::class);
        $existingClient->method('indexExists')->willReturn(true);
        $existingClient->expects(self::once())->method('deleteIndex')->with('existing');
        $this->createOpenSearch($existingClient)->deleteIndex('existing');
    }

    public function testReturnsOnlySortedVersionIndexNames(): void
    {
        $indices = $this->createMock(IndicesNamespace::class);
        $indices->expects(self::once())
            ->method('get')
            ->with([
                'index' => 'alias_v*',
                'allow_no_indices' => true,
                'ignore_unavailable' => true,
            ])
            ->willReturn([
                'alias_v10' => [],
                'alias_v2' => [],
                'alias_v0' => [],
                'another_v1' => [],
                12 => [],
        ]);
        $client = $this->createSearchClientWithIndices($indices);

        self::assertSame(['alias_v10', 'alias_v2'], $this->createOpenSearch($client)->getVersionIndexNames());
    }

    public function testRejectsNonOpenSearchMagentoConnection(): void
    {
        $connectionManager = self::createStub(ConnectionManager::class);
        $connectionManager->method('getConnection')->willReturn(new stdClass());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not configured to use OpenSearch');

        $this->createOpenSearchFromManager($connectionManager)->indexExists('alias');
    }

    /**
     * @return array<string, mixed>
     */
    private function expectedIndexConfiguration(): array
    {
        return [
            'settings' => ['index' => ['knn' => true]],
            'mappings' => [
                'dynamic' => 'strict',
                '_meta' => [
                    'davidbel_ai_search' => ['version' => 2, 'fingerprint' => 'fingerprint'],
                ],
                'properties' => [
                    'source_entity_type' => ['type' => 'keyword'],
                    'source_entity_id' => ['type' => 'long'],
                    'store_id' => ['type' => 'integer'],
                    'source_code' => ['type' => 'keyword'],
                    'vector' => [
                        'type' => 'knn_vector',
                        'dimension' => 768,
                        'method' => ['name' => 'hnsw', 'engine' => 'lucene', 'space_type' => 'cosinesimil'],
                    ],
                ],
            ],
        ];
    }

    private function physicalIndex(): PhysicalIndex
    {
        return new PhysicalIndex(
            2,
            'alias_v2',
            'fingerprint',
            new QueryConfigurationSnapshot('model', 768, '{text}')
        );
    }

    private function createSearchClientWithIndices(
        IndicesNamespace $indices
    ): SearchClient&MockObject {
        $openSearchClient = $this->createMock(Client::class);
        $openSearchClient->expects(self::once())
            ->method('indices')
            ->willReturn($indices);
        $client = $this->createMock(SearchClient::class);
        $client->expects(self::once())
            ->method('getOpenSearchClient')
            ->willReturn($openSearchClient);

        return $client;
    }

    private function createOpenSearch(
        SearchClient $client,
        ?PhysicalIndexProvider $provider = null
    ): OpenSearch {
        $connectionManager = self::createStub(ConnectionManager::class);
        $connectionManager->method('getConnection')->willReturn($client);

        return $this->createOpenSearchFromManager($connectionManager, $provider);
    }

    private function createOpenSearchFromManager(
        ConnectionManager $connectionManager,
        ?PhysicalIndexProvider $provider = null
    ): OpenSearch {
        $embedderConfig = self::createStub(EmbedderConfig::class);
        $embedderConfig->method('getVectorDimensions')->willReturn(768);
        $searchConfig = self::createStub(SearchConfig::class);
        $searchConfig->method('getVectorMethod')->willReturn('hnsw');
        $searchConfig->method('getVectorEngine')->willReturn('lucene');
        $searchConfig->method('getVectorSpace')->willReturn('cosinesimil');
        $indexName = self::createStub(IndexName::class);
        $indexName->method('getAlias')->willReturn('alias');

        return new OpenSearch(
            $connectionManager,
            $embedderConfig,
            $searchConfig,
            $indexName,
            $provider ?? self::createStub(PhysicalIndexProvider::class)
        );
    }
}
