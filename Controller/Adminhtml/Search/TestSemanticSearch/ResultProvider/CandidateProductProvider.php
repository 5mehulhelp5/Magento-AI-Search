<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Controller\Adminhtml\Search\TestSemanticSearch\ResultProvider;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;

class CandidateProductProvider
{
    public function __construct(
        private readonly CollectionFactory $productCollectionFactory
    ) {
    }

    /**
     * @param list<int> $productIdsInScoreOrder
     * @return list<\Magento\Catalog\Model\Product>
     */
    public function getProductsInScoreOrder(array $productIdsInScoreOrder, int $storeId): array
    {
        if ($productIdsInScoreOrder === []) {
            return [];
        }

        $candidateProducts = $this->loadCandidateProducts($productIdsInScoreOrder, $storeId);
        $candidateProductsById = $this->mapProductsById($candidateProducts);

        return $this->mapProductsToScoreOrder($productIdsInScoreOrder, $candidateProductsById);
    }

    /**
     * @param list<int> $productIdsInScoreOrder
     * @return array<array-key, \Magento\Catalog\Model\Product>
     */
    private function loadCandidateProducts(array $productIdsInScoreOrder, int $storeId): array
    {
        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect('name');
        $collection->addIdFilter($productIdsInScoreOrder);
        $collection->load();

        /** @var array<array-key, \Magento\Catalog\Model\Product> $candidateProducts */
        $candidateProducts = $collection->getItems();

        return $candidateProducts;
    }

    /**
     * @param array<array-key, \Magento\Catalog\Model\Product> $candidateProducts
     * @return array<int, \Magento\Catalog\Model\Product>
     */
    private function mapProductsById(array $candidateProducts): array
    {
        $candidateProductsById = [];

        foreach ($candidateProducts as $product) {
            /** @var int $productId */
            $productId = $product->getId();
            $candidateProductsById[$productId] = $product;
        }

        return $candidateProductsById;
    }

    /**
     * @param list<int> $productIdsInScoreOrder
     * @param array<int, \Magento\Catalog\Model\Product> $candidateProductsById
     * @return list<\Magento\Catalog\Model\Product>
     */
    private function mapProductsToScoreOrder(
        array $productIdsInScoreOrder,
        array $candidateProductsById
    ): array {
        $products = [];

        foreach ($productIdsInScoreOrder as $productId) {
            if (!isset($candidateProductsById[$productId])) {
                continue;
            }

            $products[] = $candidateProductsById[$productId];
        }

        return $products;
    }
}
