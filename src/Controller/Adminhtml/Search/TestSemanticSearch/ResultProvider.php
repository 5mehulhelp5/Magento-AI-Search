<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Controller\Adminhtml\Search\TestSemanticSearch;

use DavidBel\AiSearch\Config\SearchConfig;
use DavidBel\AiSearch\Config\SearchResultConfig;
use DavidBel\AiSearch\Controller\Adminhtml\Search\TestSemanticSearch\ResultProvider\RelatedDocuments;
use DavidBel\AiSearch\Controller\Adminhtml\Search\TestSemanticSearch\ResultProvider\SearchScores;
use Magento\Backend\Model\UrlInterface;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

class ResultProvider
{
    private const int RESULT_LIMIT = 20;

    public function __construct(
        private readonly Resolver $layerResolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly UrlInterface $backendUrl,
        private readonly RelatedDocuments $relatedDocuments,
        private readonly SearchResultConfig $searchResultConfig,
        private readonly SearchConfig $searchConfig,
        private readonly SearchScores $searchScores
    ) {
    }

    /**
     * @return array{
     *     query: string,
     *     store: array{id: int, name: string, code: string},
     *     total_count: int,
     *     displayed_count: int,
     *     result_limit: int,
     *     configuration: array{
     *         collapse_results_by_product: bool,
     *         minimum_score: float,
     *         embedder_query_template: string,
     *         vector_engine: string,
     *         vector_space: string
     *     },
     *     products: list<array{
     *         position: int,
     *         id: int,
     *         name: string,
     *         sku: string,
     *         type: string,
     *         score: float|null,
     *         edit_url: string,
     *         documents: list<array<string, mixed>>
     *     }>
     * }
     */
    public function getSearchResults(string $query, int $storeId): array
    {
        $store = $this->storeRepository->getById($storeId);
        $previousStore = $this->storeManager->getStore();
        $this->storeManager->setCurrentStore($store);

        try {
            return $this->runSearchForCurrentStore($query, $store, $storeId);
        } finally {
            $this->storeManager->setCurrentStore($previousStore);
        }
    }

    /**
     * @return array{
     *     query: string,
     *     store: array{id: int, name: string, code: string},
     *     total_count: int,
     *     displayed_count: int,
     *     result_limit: int,
     *     configuration: array{
     *         collapse_results_by_product: bool,
     *         minimum_score: float,
     *         embedder_query_template: string,
     *         vector_engine: string,
     *         vector_space: string
     *     },
     *     products: list<array{
     *         position: int,
     *         id: int,
     *         name: string,
     *         sku: string,
     *         type: string,
     *         score: float|null,
     *         edit_url: string,
     *         documents: list<array<string, mixed>>
     *     }>
     * }
     */
    private function runSearchForCurrentStore(
        string $query,
        StoreInterface $store,
        int $storeId
    ): array {
        $this->searchScores->scoresByProductId = [];
        $this->searchScores->scoresByChunkId = [];
        $this->layerResolver->create(Resolver::CATALOG_LAYER_SEARCH);
        $collection = $this->layerResolver->get()->getProductCollection();
        $collection->setPageSize(self::RESULT_LIMIT);
        $collection->setCurPage(1);
        $collection->load();

        /** @var array<array-key, \Magento\Catalog\Model\Product> $productItems */
        $productItems = $collection->getItems();
        $productIds = $this->getProductIds($productItems);
        $documentsByProductId = $this->relatedDocuments->getByProductIds(
            $productIds,
            $storeId
        );
        $products = $this->getProductResults(
            $productItems,
            $documentsByProductId,
            $storeId
        );

        return [
            'query' => $query,
            'store' => [
                'id' => $storeId,
                'name' => $store->getName(),
                'code' => $store->getCode(),
            ],
            'total_count' => $collection->getSize(),
            'displayed_count' => count($products),
            'result_limit' => self::RESULT_LIMIT,
            'configuration' => $this->getSearchConfiguration($storeId),
            'products' => $products,
        ];
    }

    /**
     * @return array{
     *     collapse_results_by_product: bool,
     *     minimum_score: float,
     *     embedder_query_template: string,
     *     vector_engine: string,
     *     vector_space: string
     * }
     */
    private function getSearchConfiguration(int $storeId): array
    {
        return [
            'collapse_results_by_product' =>
                $this->searchResultConfig->shouldCollapseResultsByProduct($storeId),
            'minimum_score' => $this->searchResultConfig->getMinimumScore($storeId),
            'embedder_query_template' => $this->searchResultConfig->getEmbedderQueryTemplate($storeId),
            'vector_engine' => $this->searchConfig->getVectorEngine(),
            'vector_space' => $this->searchConfig->getVectorSpace(),
        ];
    }

    /**
     * @param array<array-key, \Magento\Catalog\Model\Product> $items
     * @return list<int>
     */
    private function getProductIds(array $items): array
    {
        $productIds = [];

        foreach ($items as $product) {
            $productIds[] = $product->getId();
        }

        return $productIds;
    }

    /**
     * @param array<array-key, \Magento\Catalog\Model\Product> $items
     * @param array<int, list<array<string, mixed>>> $documentsByProductId
     * @return list<array{
     *     position: int,
     *     id: int,
     *     name: string,
     *     sku: string,
     *     type: string,
     *     score: float|null,
     *     edit_url: string,
     *     documents: list<array<string, mixed>>
     * }>
     */
    private function getProductResults(array $items, array $documentsByProductId, int $storeId): array
    {
        $products = [];
        $position = 1;

        foreach ($items as $product) {
            $productId = $product->getId();
            /** @var string $productType */
            $productType = $product->getTypeId();
            $products[] = [
                'position' => $position,
                'id' => $productId,
                'name' => $product->getName(),
                'sku' => $product->getSku(),
                'type' => $productType,
                'score' => $this->searchScores->scoresByProductId[$productId] ?? null,
                'edit_url' => $this->backendUrl->getUrl(
                    'catalog/product/edit',
                    [
                        'id' => $productId,
                        'store' => $storeId,
                    ]
                ),
                'documents' => $this->getDocumentsWithReturnedChunks(
                    $documentsByProductId[$productId] ?? []
                ),
            ];
            $position++;
        }

        return $products;
    }

    /**
     * @param list<array<string, mixed>> $documents
     * @return list<array<string, mixed>>
     */
    private function getDocumentsWithReturnedChunks(array $documents): array
    {
        $returnedDocuments = [];

        foreach ($documents as $document) {
            /**
             * @var list<array{
             *     id: int,
             *     index: int,
             *     content: string,
             *     created_at: string|null,
             *     updated_at: string|null
             * }> $chunks
            */
            $chunks = $document['chunks'];
            $returnedChunks = $this->getReturnedChunks($chunks);

            if ($returnedChunks === []) {
                continue;
            }

            $document['chunks'] = $returnedChunks;
            $returnedDocuments[] = $document;
        }

        return $returnedDocuments;
    }

    /**
     * @param list<array{
     *     id: int,
     *     index: int,
     *     content: string,
     *     created_at: string|null,
     *     updated_at: string|null
     * }> $chunks
     * @return list<array<string, mixed>>
     */
    private function getReturnedChunks(array $chunks): array
    {
        $returnedChunks = [];

        foreach ($chunks as $chunk) {
            $score = $this->searchScores->scoresByChunkId[$chunk['id']] ?? null;

            if ($score === null) {
                continue;
            }

            $chunk['score'] = $score;
            $returnedChunks[] = $chunk;
        }

        return $returnedChunks;
    }
}
