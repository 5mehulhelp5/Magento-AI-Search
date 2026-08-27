<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Search;

use DavidBel\AiSearch\Client\Embedding\Base\EmbedderClientInterface;
use DavidBel\AiSearch\Client\Embedding\Base\EmbedderClientPool;
use DavidBel\AiSearch\Client\OpenSearch;
use DavidBel\AiSearch\Config\SemanticSearchResultConfig;
use DavidBel\AiSearch\Indexer\Versioning;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex\QueryConfigurationSnapshot;
use DavidBel\AiSearch\Search\Candidates;
use DavidBel\AiSearch\Search\CatalogQueryModifier;
use DavidBel\AiSearch\Search\QueryEmbedding;
use DavidBel\AiSearch\Search\QuickSearch;
use DavidBel\AiSearch\Search\RequestReader;
use DavidBel\AiSearch\Search\SemanticSearch;
use DavidBel\AiSearch\Search\VectorSearch;
use GuzzleHttp\Promise\Create;
use Magento\Framework\Search\Request\Dimension;
use Magento\Framework\Search\Request\Query\BoolExpression;
use Magento\Framework\Search\Request\Query\Filter;
use Magento\Framework\Search\Request\Query\MatchQuery;
use Magento\Framework\Search\Request\QueryInterface;
use Magento\Framework\Search\RequestInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnexpectedValueException;

class SearchTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function requestNames(): array
    {
        return [
            'storefront' => ['quick_search_container', true],
            'graphql' => ['graphql_product_search', true],
            'graphql aggregation' => ['graphql_product_search_with_aggregation', true],
            'unrelated' => ['catalog_view_container', false],
        ];
    }

    #[DataProvider('requestNames')]
    public function testIdentifiesSemanticSearchRequests(string $name, bool $expected): void
    {
        $request = self::createStub(RequestInterface::class);
        $request->method('getName')->willReturn($name);

        self::assertSame($expected, (new RequestReader())->isSemanticSearchRequest($request));
    }

    public function testReadsANestedQueryAndStringStoreId(): void
    {
        $match = new MatchQuery('search', '  red shoes  ', null, []);
        $filter = self::createStub(Filter::class);
        $filter->method('getReferenceType')->willReturn(Filter::REFERENCE_QUERY);
        $filter->method('getReference')->willReturn($match);
        $query = new BoolExpression('bool', null, [], [$filter]);
        $request = self::createStub(RequestInterface::class);
        $request->method('getQuery')->willReturn($query);
        $request->method('getDimensions')->willReturn([new Dimension('other', 1), new Dimension('scope', '2')]);
        $reader = new RequestReader();

        self::assertSame('red shoes', $reader->getQueryText($request));
        self::assertSame(2, $reader->getStoreId($request));
    }

    public function testReturnsAnEmptyQueryWhenNoSearchMatchExists(): void
    {
        $query = self::createStub(QueryInterface::class);
        $request = self::createStub(RequestInterface::class);
        $request->method('getQuery')->willReturn($query);

        self::assertSame('', (new RequestReader())->getQueryText($request));
    }

    public function testReturnsEmptyQueryWhenBooleanListHasNoSearchMatch(): void
    {
        $query = new BoolExpression(
            'bool',
            null,
            [self::createStub(QueryInterface::class)],
            []
        );
        $request = self::createStub(RequestInterface::class);
        $request->method('getQuery')->willReturn($query);

        self::assertSame('', (new RequestReader())->getQueryText($request));
    }

    public function testReadsAnIntegerStoreId(): void
    {
        $request = self::createStub(RequestInterface::class);
        $request->method('getDimensions')->willReturn([new Dimension('scope', 3)]);

        self::assertSame(3, (new RequestReader())->getStoreId($request));
    }

    public function testRejectsAMissingStoreDimension(): void
    {
        $request = self::createStub(RequestInterface::class);
        $request->method('getDimensions')->willReturn([]);

        $this->expectException(UnexpectedValueException::class);

        (new RequestReader())->getStoreId($request);
    }

    public function testRejectsAnInvalidStoreId(): void
    {
        $request = self::createStub(RequestInterface::class);
        $request->method('getDimensions')->willReturn([new Dimension('scope', 'invalid')]);

        $this->expectException(UnexpectedValueException::class);

        (new RequestReader())->getStoreId($request);
    }

    public function testCreatesAQueryEmbedding(): void
    {
        $indexConfiguration = new QueryConfigurationSnapshot('model', 2, 'old {text}');
        $requestConfiguration = new QueryConfigurationSnapshot('model', 2, 'new {text}');
        $client = $this->createMock(EmbedderClientInterface::class);
        $client->expects(self::once())
            ->method('embedQueryAsync')
            ->with('shoes', 10, $requestConfiguration)
            ->willReturn(Create::promiseFor([[0.1, 0.2]]));
        $pool = self::createStub(EmbedderClientPool::class);
        $pool->method('getClient')->willReturn($client);
        $config = self::createStub(SemanticSearchResultConfig::class);
        $config->method('getEmbedderQueryTemplate')->willReturn('new {text}');
        $config->method('getRequestTimeoutSeconds')->willReturn(10);

        self::assertSame(
            [0.1, 0.2],
            (new QueryEmbedding($pool, $config))->execute('shoes', 2, $indexConfiguration)
        );
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidEmbeddingResponses(): array
    {
        return [
            'not an array' => [null, 'unexpected vector count'],
            'empty' => [[], 'unexpected vector count'],
            'multiple vectors' => [[[0.1], [0.2]], 'unexpected vector count'],
            'not a vector' => [['invalid'], 'invalid vector'],
            'associative vector' => [[['value' => 0.1]], 'invalid vector'],
            'non-float value' => [[[1]], 'invalid vector value'],
        ];
    }

    #[DataProvider('invalidEmbeddingResponses')]
    public function testRejectsInvalidEmbeddingResponses(mixed $response, string $message): void
    {
        $client = self::createStub(EmbedderClientInterface::class);
        $client->method('embedQueryAsync')->willReturn(Create::promiseFor($response));
        $pool = self::createStub(EmbedderClientPool::class);
        $pool->method('getClient')->willReturn($client);
        $config = self::createStub(SemanticSearchResultConfig::class);
        $config->method('getEmbedderQueryTemplate')->willReturn('query');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        (new QueryEmbedding($pool, $config))->execute(
            'shoes',
            2,
            new QueryConfigurationSnapshot('model', 1, 'query')
        );
    }

    public function testSearchesVectorsAndKeepsTheHighestRelevantProductScores(): void
    {
        $index = $this->createPhysicalIndex();
        $config = $this->createSemanticSearchResultConfig(true);
        $openSearch = $this->createMock(OpenSearch::class);
        $openSearch->expects(self::once())
            ->method('search')
            ->with($index, $this->expectedVectorQuery(true))
            ->willReturn([
                'hits' => ['hits' => [
                    ['_source' => ['source_entity_id' => 10], '_score' => 0.8],
                    ['_source' => ['source_entity_id' => 10], '_score' => 0.7],
                    ['_source' => ['source_entity_id' => 10], '_score' => 0.9],
                    ['_source' => ['source_entity_id' => 20], '_score' => 0.2],
                    ['_source' => ['source_entity_id' => 30], '_score' => 1],
                ]],
            ]);

        self::assertSame(
            [10 => 0.9, 30 => 1.0],
            (new VectorSearch($openSearch, $config))->execute([0.1, 0.2], 2, $index)
                ->scoresByProductId
        );
    }

    public function testSearchesWithoutResultCollapsing(): void
    {
        $index = $this->createPhysicalIndex();
        $openSearch = $this->createMock(OpenSearch::class);
        $openSearch->expects(self::once())
            ->method('search')
            ->with($index, $this->expectedVectorQuery(false))
            ->willReturn(['hits' => ['hits' => []]]);

        self::assertSame(
            [],
            (new VectorSearch($openSearch, $this->createSemanticSearchResultConfig(false)))
                ->execute([0.1, 0.2], 2, $index)
                ->scoresByProductId
        );
    }

    /**
     * @return array<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidSearchResponses(): array
    {
        return [
            'missing hit container' => [[], 'invalid hit list'],
            'invalid hits' => [['hits' => ['hits' => 'invalid']], 'invalid hit list'],
            'invalid hit' => [['hits' => ['hits' => [null]]], 'invalid product hit'],
            'invalid source' => [['hits' => ['hits' => [['_source' => null, '_score' => 1]]]], 'invalid product hit'],
            'invalid score type' => [
                ['hits' => ['hits' => [['_source' => ['source_entity_id' => 1], '_score' => '1']]]],
                'invalid product hit',
            ],
            'invalid score value' => [
                ['hits' => ['hits' => [['_source' => ['source_entity_id' => 1], '_score' => INF]]]],
                'invalid product score',
            ],
        ];
    }

    /**
     * @param array<array-key, mixed> $response
     */
    #[DataProvider('invalidSearchResponses')]
    public function testRejectsInvalidVectorSearchResponses(array $response, string $message): void
    {
        $openSearch = self::createStub(OpenSearch::class);
        $openSearch->method('search')->willReturn($response);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        (new VectorSearch($openSearch, $this->createSemanticSearchResultConfig(false)))
            ->execute([0.1], 2, $this->createPhysicalIndex());
    }

    public function testReplacesCatalogSearchScoringWithSemanticCandidates(): void
    {
        $query = ['body' => ['query' => ['bool' => [
            'must' => [['term' => ['visibility' => 4]]],
            'should' => [['match' => ['name' => 'shoe']]],
            'minimum_should_match' => 1,
        ]]]];
        $result = (new CatalogQueryModifier())->execute($query, new Candidates([10 => 0.9]));

        self::assertSame(
            [
                'body' => [
                    'query' => [
                        'script_score' => [
                            'query' => ['bool' => ['must' => [
                                ['term' => ['visibility' => 4]],
                                ['ids' => ['values' => ['10']]],
                            ]]],
                            'script' => [
                                'lang' => 'painless',
                                'source' => "params.scores[doc['_id'].value]",
                                'params' => ['scores' => [10 => 0.9]],
                            ],
                        ],
                    ],
                ],
            ],
            $result
        );
    }

    public function testCreatesAMatchNoneConditionWithoutCandidates(): void
    {
        $query = ['body' => ['query' => ['bool' => []]]];
        $result = (new CatalogQueryModifier())->execute($query, new Candidates([]));

        self::assertEquals(
            ['body' => ['query' => ['bool' => ['must' => [['match_none' => new \stdClass()]]]]]],
            $result
        );
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidCatalogQueries(): array
    {
        return [
            'body' => [[], 'unexpected catalog search query'],
            'query' => [['body' => []], 'unexpected catalog search query'],
            'bool' => [['body' => ['query' => []]], 'unexpected catalog search query'],
            'must' => [
                ['body' => ['query' => ['bool' => ['must' => 'invalid']]]],
                'invalid catalog search conditions',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $query
     */
    #[DataProvider('invalidCatalogQueries')]
    public function testRejectsInvalidCatalogQueries(array $query, string $message): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        (new CatalogQueryModifier())->execute($query, new Candidates([]));
    }

    public function testExecutesACompleteQuickSearch(): void
    {
        $request = self::createStub(RequestInterface::class);
        $reader = self::createStub(RequestReader::class);
        $reader->method('isSemanticSearchRequest')->willReturn(true);
        $reader->method('getStoreId')->willReturn(2);
        $reader->method('getQueryText')->willReturn('shoes');
        $config = self::createStub(SemanticSearchResultConfig::class);
        $config->method('isEnabled')->willReturn(true);
        $index = $this->createPhysicalIndex();
        $versioning = self::createStub(Versioning::class);
        $versioning->method('getSearchIndex')->willReturn($index);
        $embedding = $this->createMock(QueryEmbedding::class);
        $embedding->expects(self::once())->method('execute')->willReturn([0.1]);
        $vectorSearch = $this->createMock(VectorSearch::class);
        $vectorSearch->expects(self::once())->method('execute')->willReturn(new Candidates([10 => 0.9]));
        $modifier = $this->createMock(CatalogQueryModifier::class);
        $modifier->expects(self::once())->method('execute')->willReturn(['modified' => true]);
        $semanticSearch = new SemanticSearch($embedding, $vectorSearch, $versioning, $config);
        $quickSearch = new QuickSearch($reader, $semanticSearch, $modifier, $config);

        self::assertSame(['modified' => true], $quickSearch->execute($request, ['body' => []]));
    }

    public function testSkipsANonSemanticQuickSearch(): void
    {
        $reader = self::createStub(RequestReader::class);
        $reader->method('isSemanticSearchRequest')->willReturn(false);

        self::assertSame(
            ['original' => true],
            $this->createQuickSearch($reader)->execute(
                self::createStub(RequestInterface::class),
                ['original' => true]
            )
        );
    }

    public function testSkipsADisabledQuickSearch(): void
    {
        $reader = self::createStub(RequestReader::class);
        $reader->method('isSemanticSearchRequest')->willReturn(true);
        $reader->method('getStoreId')->willReturn(2);

        self::assertSame(
            ['original' => true],
            $this->createQuickSearch($reader)->execute(
                self::createStub(RequestInterface::class),
                ['original' => true]
            )
        );
    }

    public function testSkipsAnEmptyQuickSearchQuery(): void
    {
        $reader = self::createStub(RequestReader::class);
        $reader->method('isSemanticSearchRequest')->willReturn(true);
        $reader->method('getStoreId')->willReturn(2);
        $config = self::createStub(SemanticSearchResultConfig::class);
        $config->method('isEnabled')->willReturn(true);

        self::assertSame(
            ['original' => true],
            $this->createQuickSearch($reader, $config)->execute(
                self::createStub(RequestInterface::class),
                ['original' => true]
            )
        );
    }

    public function testRejectsAQuickSearchWithoutAnIndex(): void
    {
        $reader = self::createStub(RequestReader::class);
        $reader->method('isSemanticSearchRequest')->willReturn(true);
        $reader->method('getStoreId')->willReturn(2);
        $reader->method('getQueryText')->willReturn('shoes');
        $config = self::createStub(SemanticSearchResultConfig::class);
        $config->method('isEnabled')->willReturn(true);

        $semanticSearch = new SemanticSearch(
            self::createStub(QueryEmbedding::class),
            self::createStub(VectorSearch::class),
            self::createStub(Versioning::class),
            $config
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A semantic search index is not available.');

        (new QuickSearch(
            $reader,
            $semanticSearch,
            self::createStub(CatalogQueryModifier::class),
            $config
        ))->execute(self::createStub(RequestInterface::class), ['original' => true]);
    }

    private function createPhysicalIndex(): PhysicalIndex
    {
        return new PhysicalIndex(
            1,
            'chunks_v1',
            'fingerprint',
            new QueryConfigurationSnapshot('model', 2, 'query')
        );
    }

    private function createSemanticSearchResultConfig(bool $collapse): SemanticSearchResultConfig
    {
        $config = self::createStub(SemanticSearchResultConfig::class);
        $config->method('getProductResultLimit')->willReturn(10);
        $config->method('getChunkCandidateLimit')->willReturn(20);
        $config->method('shouldCollapseResultsByProduct')->willReturn($collapse);
        $config->method('getMinimumScore')->willReturn(0.5);

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    private function expectedVectorQuery(bool $collapse): array
    {
        $query = [
            'size' => 10,
            '_source' => ['source_entity_id'],
            'query' => ['knn' => ['vector' => [
                'vector' => [0.1, 0.2],
                'k' => 20,
                'filter' => ['bool' => ['filter' => [
                    ['term' => ['source_entity_type' => 'product']],
                    ['term' => ['store_id' => 2]],
                ]]],
            ]]],
        ];

        if ($collapse) {
            $query['collapse'] = ['field' => 'source_entity_id'];
        }

        return $query;
    }

    private function createQuickSearch(
        RequestReader $requestReader,
        ?SemanticSearchResultConfig $config = null
    ): QuickSearch {
        return new QuickSearch(
            $requestReader,
            self::createStub(SemanticSearch::class),
            self::createStub(CatalogQueryModifier::class),
            $config ?? self::createStub(SemanticSearchResultConfig::class)
        );
    }
}
