<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Controller;

use DavidBel\AiSearch\Api\ChunkRepositoryInterface;
use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Api\Data\ChunkSearchResultsInterface;
use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Api\Data\DocumentSearchResultsInterface;
use DavidBel\AiSearch\Api\DocumentRepositoryInterface;
use DavidBel\AiSearch\Config\SearchConfig;
use DavidBel\AiSearch\Config\SemanticSearchResultConfig;
use DavidBel\AiSearch\Controller\Adminhtml\Search\TestSemanticSearch\ResultProvider;
use DavidBel\AiSearch\Controller\Adminhtml\Search\TestSemanticSearch\ResultProvider\RelatedDocuments;
use DavidBel\AiSearch\Controller\Adminhtml\Search\TestSemanticSearch\ResultProvider\SearchScores;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use Magento\Backend\Model\UrlInterface;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class AdminSearchResultProviderTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(SearchCriteriaBuilderFactory::class);
    }

    public function testRelatedDocumentsReturnsEarlyWithoutProducts(): void
    {
        $factory = $this->createMock(SearchCriteriaBuilderFactory::class);
        $factory->expects(self::never())->method('create');
        $documents = $this->createMock(DocumentRepositoryInterface::class);
        $documents->expects(self::never())->method('getList');

        self::assertSame(
            [],
            (new RelatedDocuments(
                $factory,
                $documents,
                self::createStub(ChunkRepositoryInterface::class)
            ))->getByProductIds([], 1)
        );
    }

    public function testRelatedDocumentsGroupsAndSortsDocumentsAndChunks(): void
    {
        $firstDocument = $this->document(20, 10, 'name');
        $secondDocument = $this->document(10, 10, 'description');
        $documentResults = self::createStub(DocumentSearchResultsInterface::class);
        $documentResults->method('getItems')->willReturn([$firstDocument, $secondDocument]);
        $documents = self::createStub(DocumentRepositoryInterface::class);
        $documents->method('getList')->willReturn($documentResults);
        $firstChunk = $this->chunk(101, 10, 1, 'second');
        $secondChunk = $this->chunk(100, 10, 0, 'first');
        $chunkResults = self::createStub(ChunkSearchResultsInterface::class);
        $chunkResults->method('getItems')->willReturn([$firstChunk, $secondChunk]);
        $chunks = self::createStub(ChunkRepositoryInterface::class);
        $chunks->method('getList')->willReturn($chunkResults);

        self::assertSame(
            $this->expectedRelatedDocuments(),
            (new RelatedDocuments(
                $this->createCriteriaFactory(),
                $documents,
                $chunks
            ))->getByProductIds([10], 1)
        );
    }

    public function testRelatedDocumentsDoesNotLoadChunksWithoutDocuments(): void
    {
        $documentResults = self::createStub(DocumentSearchResultsInterface::class);
        $documentResults->method('getItems')->willReturn([]);
        $documents = self::createStub(DocumentRepositoryInterface::class);
        $documents->method('getList')->willReturn($documentResults);
        $chunks = $this->createMock(ChunkRepositoryInterface::class);
        $chunks->expects(self::never())->method('getList');

        self::assertSame(
            [],
            (new RelatedDocuments(
                $this->createCriteriaFactory(),
                $documents,
                $chunks
            ))->getByProductIds([10], 1)
        );
    }

    public function testBuildsAdminSearchResultAndRestoresPreviousStore(): void
    {
        $provider = $this->createResultProvider();

        $result = $provider->getSearchResults('shoes', 1);

        self::assertSame('shoes', $result['query']);
        self::assertSame(['id' => 1, 'name' => 'Store', 'code' => 'store'], $result['store']);
        self::assertSame(1, $result['total_count']);
        self::assertSame(1, $result['displayed_count']);
        self::assertSame(20, $result['result_limit']);
        self::assertSame(
            [
                'collapse_results_by_product' => true,
                'minimum_score' => 0.5,
                'embedder_query_template' => 'Query: {text}',
                'vector_engine' => 'lucene',
                'vector_space' => 'cosinesimil',
            ],
            $result['configuration']
        );
        self::assertSame(10, $result['products'][0]['id']);
        self::assertNull($result['products'][0]['score']);
        self::assertSame([], $result['products'][0]['documents']);
    }

    private function createResultProvider(): ResultProvider
    {
        $resolver = $this->searchLayerResolver();
        $store = $this->store(1, 'Store', 'store');
        $previousStore = $this->store(2, 'Previous', 'previous');
        $storeRepository = self::createStub(StoreRepositoryInterface::class);
        $storeRepository->method('getById')->willReturn($store);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($previousStore);
        $storeManager->expects(self::exactly(2))->method('setCurrentStore');
        $backendUrl = self::createStub(UrlInterface::class);
        $backendUrl->method('getUrl')->willReturn('edit-url');
        $related = self::createStub(RelatedDocuments::class);
        $related->method('getByProductIds')->willReturn([
            10 => [['id' => 1, 'chunks' => [['id' => 100, 'index' => 0, 'content' => 'content']]]],
        ]);
        [$resultConfig, $searchConfig] = $this->searchConfigurations();
        $scores = new SearchScores();
        $scores->scoresByProductId = [10 => 0.9];
        $scores->scoresByChunkId = [100 => 0.8];

        return new ResultProvider(
            $resolver,
            $storeManager,
            $storeRepository,
            $backendUrl,
            $related,
            $resultConfig,
            $searchConfig,
            $scores
        );
    }

    private function searchLayerResolver(): Resolver
    {
        $product = self::createStub(Product::class);
        $product->method('getId')->willReturn(10);
        $product->method('getName')->willReturn('Product');
        $product->method('getSku')->willReturn('sku');
        $product->method('getTypeId')->willReturn('simple');
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())->method('setPageSize')->with(20)->willReturnSelf();
        $collection->expects(self::once())->method('setCurPage')->with(1)->willReturnSelf();
        $collection->expects(self::once())->method('load')->willReturnSelf();
        $collection->method('getItems')->willReturn([$product]);
        $collection->method('getSize')->willReturn(1);
        $layer = self::createStub(Layer::class);
        $layer->method('getProductCollection')->willReturn($collection);
        $resolver = $this->createMock(Resolver::class);
        $resolver->expects(self::once())->method('create')->with(Resolver::CATALOG_LAYER_SEARCH);
        $resolver->method('get')->willReturn($layer);

        return $resolver;
    }

    /**
     * @return array{SemanticSearchResultConfig, SearchConfig}
     */
    private function searchConfigurations(): array
    {
        $resultConfig = self::createStub(SemanticSearchResultConfig::class);
        $resultConfig->method('shouldCollapseResultsByProduct')->willReturn(true);
        $resultConfig->method('getMinimumScore')->willReturn(0.5);
        $resultConfig->method('getEmbedderQueryTemplate')->willReturn('Query: {text}');
        $searchConfig = self::createStub(SearchConfig::class);
        $searchConfig->method('getVectorEngine')->willReturn('lucene');
        $searchConfig->method('getVectorSpace')->willReturn('cosinesimil');

        return [$resultConfig, $searchConfig];
    }

    /**
     * @return array<int, list<array<string, mixed>>>
     */
    private function expectedRelatedDocuments(): array
    {
        return [10 => [
            [
                'id' => 10,
                'store_id' => 1,
                'source_code' => 'description',
                'title' => 'Title',
                'created_at' => 'created',
                'updated_at' => 'updated',
                'chunks' => [
                    [
                        'id' => 100,
                        'index' => 0,
                        'content' => 'first',
                        'created_at' => 'created',
                        'updated_at' => 'updated',
                    ],
                    [
                        'id' => 101,
                        'index' => 1,
                        'content' => 'second',
                        'created_at' => 'created',
                        'updated_at' => 'updated',
                    ],
                ],
            ],
            [
                'id' => 20,
                'store_id' => 1,
                'source_code' => 'name',
                'title' => 'Title',
                'created_at' => 'created',
                'updated_at' => 'updated',
                'chunks' => [],
            ],
        ]];
    }

    private function createCriteriaFactory(): SearchCriteriaBuilderFactory
    {
        $criteria = self::createStub(SearchCriteriaInterface::class);
        $builder = self::createStub(SearchCriteriaBuilder::class);
        $builder->method('addFilter')->willReturnSelf();
        $builder->method('create')->willReturn($criteria);
        $factory = self::createStub(SearchCriteriaBuilderFactory::class);
        $factory->method('create')->willReturn($builder);

        return $factory;
    }

    private function document(int $id, int $productId, string $sourceCode): DocumentInterface
    {
        $document = self::createStub(DocumentInterface::class);
        $document->method('getDocumentId')->willReturn($id);
        $document->method('getSourceEntityId')->willReturn($productId);
        $document->method('getStoreId')->willReturn(1);
        $document->method('getSourceCode')->willReturn($sourceCode);
        $document->method('getTitle')->willReturn('Title');
        $document->method('getCreatedAt')->willReturn('created');
        $document->method('getUpdatedAt')->willReturn('updated');

        return $document;
    }

    private function chunk(
        int $id,
        int $documentId,
        int $index,
        string $content
    ): ChunkInterface {
        $chunk = self::createStub(ChunkInterface::class);
        $chunk->method('getChunkId')->willReturn($id);
        $chunk->method('getDocumentId')->willReturn($documentId);
        $chunk->method('getChunkIndex')->willReturn($index);
        $chunk->method('getContent')->willReturn($content);
        $chunk->method('getCreatedAt')->willReturn('created');
        $chunk->method('getUpdatedAt')->willReturn('updated');

        return $chunk;
    }

    private function store(int $id, string $name, string $code): StoreInterface
    {
        $store = self::createStub(StoreInterface::class);
        $store->method('getId')->willReturn($id);
        $store->method('getName')->willReturn($name);
        $store->method('getCode')->willReturn($code);

        return $store;
    }
}
