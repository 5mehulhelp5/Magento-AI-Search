<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Workflow\VectorEmbedding\OpenSearchUpdater;

use DavidBel\AiSearch\Workflow\VectorEmbedding\OpenSearchUpdater\ChunkDocument;
use DavidBel\AiSearch\Workflow\VectorEmbedding\OpenSearchUpdater\ChunkIndex;
use Magento\AdvancedSearch\Model\Client\ClientInterface;
use Magento\Elasticsearch\SearchAdapter\ConnectionManager;
use Magento\OpenSearch\Model\SearchClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnexpectedValueException;

class ChunkIndexTest extends TestCase
{
    private const string INDEX_NAME = 'davidbel_ai_search_chunks';

    public function testReturnsEmptyResultWithoutConnectingForNoDocuments(): void
    {
        $connectionManager = $this->createMock(ConnectionManager::class);
        $connectionManager->expects(self::never())
            ->method('getConnection');

        $result = (new ChunkIndex($connectionManager))->upsert([]);

        self::assertSame([], $result->getSuccessfulBacklogIds());
        self::assertSame([], $result->getFailedBacklogIds());
    }

    public function testCreatesIndexAndSendsBulkDocuments(): void
    {
        $documents = [self::document(1), self::document(2)];
        $client = $this->createMock(SearchClient::class);
        $client->expects(self::once())
            ->method('indexExists')
            ->with(self::INDEX_NAME)
            ->willReturn(false);
        $client->expects(self::once())
            ->method('createIndex')
            ->with(self::INDEX_NAME, self::indexDefinition());
        $client->expects(self::once())
            ->method('bulkQuery')
            ->with([
                'body' => self::bulkBody(),
                'refresh' => 'wait_for',
            ])
            ->willReturn([
                'errors' => true,
                'items' => [
                    ['index' => ['_id' => '101', 'status' => 201]],
                    ['index' => ['_id' => '102', 'status' => 400]],
                ],
            ]);

        $result = $this->createIndex($client)->upsert($documents);

        self::assertSame([1], $result->getSuccessfulBacklogIds());
        self::assertSame([2], $result->getFailedBacklogIds());
    }

    public function testReusesReadyIndexAndOpenSearchConnection(): void
    {
        $document = self::document(1);
        $response = [
            'errors' => false,
            'items' => [
                ['index' => ['_id' => '101', 'status' => 200]],
            ],
        ];
        $client = $this->createMock(SearchClient::class);
        $client->expects(self::once())
            ->method('indexExists')
            ->willReturn(true);
        $client->expects(self::never())
            ->method('createIndex');
        $client->expects(self::exactly(2))
            ->method('bulkQuery')
            ->willReturn($response);
        $index = $this->createIndex($client);

        self::assertSame(1, $index->upsert([$document])->getSuccessfulCount());
        self::assertSame(1, $index->upsert([$document])->getSuccessfulCount());
    }

    public function testRejectsNonOpenSearchMagentoConnection(): void
    {
        $connectionManager = $this->createMock(ConnectionManager::class);
        $connectionManager->expects(self::once())
            ->method('getConnection')
            ->willReturn(self::createStub(ClientInterface::class));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Magento is not configured to use OpenSearch.'
        );

        (new ChunkIndex($connectionManager))->upsert([self::document(1)]);
    }

    public function testCategorizesSuccessfulStatusBoundaries(): void
    {
        $response = [
            'errors' => true,
            'items' => [
                ['index' => ['_id' => '101', 'status' => 200]],
                ['index' => ['_id' => '102', 'status' => 299]],
                ['index' => ['_id' => '103', 'status' => 199]],
                ['index' => ['_id' => '104', 'status' => 300]],
            ],
        ];
        $result = $this->createIndexForResponse($response)->upsert([
            self::document(1),
            self::document(2),
            self::document(3),
            self::document(4),
        ]);

        self::assertSame([1, 2], $result->getSuccessfulBacklogIds());
        self::assertSame([3, 4], $result->getFailedBacklogIds());
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidBulkResponses(): iterable
    {
        yield 'missing metadata' => [
            [],
            'OpenSearch returned an invalid bulk response.',
        ];
        yield 'non-list items' => [
            ['errors' => false, 'items' => ['item' => []]],
            'OpenSearch returned an invalid bulk response.',
        ];
        yield 'unexpected item count' => [
            ['errors' => false, 'items' => []],
            'OpenSearch returned an unexpected bulk item count.',
        ];
        yield 'invalid item' => [
            ['errors' => true, 'items' => [null]],
            'OpenSearch returned an invalid bulk item.',
        ];
        yield 'wrong document ID' => [
            [
                'errors' => false,
                'items' => [
                    ['index' => ['_id' => '999', 'status' => 200]],
                ],
            ],
            'OpenSearch returned an invalid bulk item.',
        ];
        yield 'errors false with failed item' => [
            [
                'errors' => false,
                'items' => [
                    ['index' => ['_id' => '101', 'status' => 500]],
                ],
            ],
            'OpenSearch returned inconsistent bulk error information.',
        ];
        yield 'errors true with successful item' => [
            [
                'errors' => true,
                'items' => [
                    ['index' => ['_id' => '101', 'status' => 200]],
                ],
            ],
            'OpenSearch returned inconsistent bulk error information.',
        ];
    }

    /**
     * @param array<array-key, mixed> $response
     */
    #[DataProvider('invalidBulkResponses')]
    public function testRejectsInvalidBulkResponse(
        array $response,
        string $exceptionMessage
    ): void {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $this->createIndexForResponse($response)->upsert([
            self::document(1),
        ]);
    }

    /**
     * @param array<array-key, mixed> $response
     */
    private function createIndexForResponse(array $response): ChunkIndex
    {
        $client = $this->createMock(SearchClient::class);
        $client->expects(self::once())
            ->method('indexExists')
            ->willReturn(true);
        $client->expects(self::once())
            ->method('bulkQuery')
            ->willReturn($response);

        return $this->createIndex($client);
    }

    private function createIndex(SearchClient $client): ChunkIndex
    {
        $connectionManager = $this->createMock(ConnectionManager::class);
        $connectionManager->expects(self::once())
            ->method('getConnection')
            ->willReturn($client);

        return new ChunkIndex($connectionManager);
    }

    private static function document(int $backlogId): ChunkDocument
    {
        return new ChunkDocument(
            $backlogId,
            $backlogId + 100,
            'product',
            $backlogId + 500,
            1,
            'description',
            $backlogId,
            'content-' . $backlogId,
            'hash-' . $backlogId,
            [0.1, 0.2]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function indexDefinition(): array
    {
        return [
            'settings' => [
                'index' => ['knn' => true],
            ],
            'mappings' => [
                'dynamic' => 'strict',
                'properties' => [
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
                        'dimension' => 768,
                        'method' => [
                            'name' => 'hnsw',
                            'engine' => 'faiss',
                            'space_type' => 'l2',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function bulkBody(): array
    {
        return [
            ['index' => ['_index' => self::INDEX_NAME, '_id' => '101']],
            [
                'chunk_id' => 101,
                'source_entity_type' => 'product',
                'source_entity_id' => 501,
                'store_id' => 1,
                'source_code' => 'description',
                'chunk_index' => 1,
                'content' => 'content-1',
                'content_hash' => 'hash-1',
                'vector' => [0.1, 0.2],
            ],
            ['index' => ['_index' => self::INDEX_NAME, '_id' => '102']],
            [
                'chunk_id' => 102,
                'source_entity_type' => 'product',
                'source_entity_id' => 502,
                'store_id' => 1,
                'source_code' => 'description',
                'chunk_index' => 2,
                'content' => 'content-2',
                'content_hash' => 'hash-2',
                'vector' => [0.1, 0.2],
            ],
        ];
    }
}
