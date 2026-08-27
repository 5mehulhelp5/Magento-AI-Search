<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Stress\Support;

use DavidBel\AiSearch\Test\Stress\Support\CatalogDataset\ConfigurableAttribute;
use DavidBel\AiSearch\Test\Stress\Support\CatalogDataset\ProductCreator;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use RuntimeException;

class CatalogDataset
{
    public const string SKU_PREFIX = 'ai-search-stress-';

    public function __construct(
        private readonly ConfigurableAttribute $configurableAttribute,
        private readonly ProductCreator $productCreator,
        private readonly CollectionFactory $productCollectionFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly Registry $registry,
        private readonly StressConfiguration $configuration
    ) {
    }

    public function create(): void
    {
        if ($this->getAllProductIds() !== [] || $this->configurableAttribute->exists()) {
            throw new RuntimeException('Stress-test catalog data already exists. Run the cleanup stage first.');
        }

        if ($this->configuration->usesStandaloneSimpleProducts()) {
            $this->createStandaloneSimpleProducts();

            return;
        }

        $attribute = $this->configurableAttribute->create();
        $optionIds = $this->configurableAttribute->getOptionIds($attribute);
        $configurableProductCount = $this->configuration->getConfigurableProductCount();

        for ($parentNumber = 1; $parentNumber <= $configurableProductCount; $parentNumber++) {
            $childIds = [];

            foreach ($optionIds as $childPosition => $optionId) {
                $childNumber = $childPosition + 1;
                $childSku = $this->getSimpleSku($parentNumber, $childNumber);
                $childIds[] = $this->productCreator->createSimple(
                    $childSku,
                    sprintf('AI Search Stress Simple %02d %02d', $parentNumber, $childNumber),
                    $optionId,
                    $childNumber
                );
            }

            $this->productCreator->createConfigurable(
                $this->getConfigurableSku($parentNumber),
                sprintf('AI Search Stress Configurable %02d', $parentNumber),
                $childIds,
                $attribute,
                $optionIds
            );
        }
    }

    public function removeCatalogData(): void
    {
        $secureArea = $this->registry->registry('isSecureArea');
        $this->registry->unregister('isSecureArea');
        $this->registry->register('isSecureArea', true);

        try {
            $this->deleteProductsByType(Configurable::TYPE_CODE);
            $this->deleteProductsByType(Product\Type::TYPE_SIMPLE);
        } finally {
            $this->registry->unregister('isSecureArea');

            if ($secureArea !== null) {
                $this->registry->register('isSecureArea', $secureArea);
            }
        }

        $this->configurableAttribute->remove();
    }

    /**
     * @return list<int>
     */
    public function getAllProductIds(): array
    {
        return $this->getProductIdsByType(null);
    }

    /**
     * @return list<int>
     */
    public function getConfigurableProductIds(): array
    {
        return $this->getProductIdsByType(Configurable::TYPE_CODE);
    }

    /**
     * @return list<int>
     */
    public function getSimpleProductIds(): array
    {
        return $this->getProductIdsByType(Product\Type::TYPE_SIMPLE);
    }

    /**
     * @return list<int>
     */
    public function getSearchableProductIds(): array
    {
        if ($this->configuration->usesStandaloneSimpleProducts()) {
            return $this->getSimpleProductIds();
        }

        return $this->getConfigurableProductIds();
    }

    /**
     * @return list<string>
     */
    public function getConfigurableProductSkus(): array
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('sku');
        $collection->addFieldToFilter('sku', ['like' => self::SKU_PREFIX . '%']);
        $collection->addFieldToFilter('type_id', Configurable::TYPE_CODE);
        $collection->setOrder('entity_id');
        $skus = [];

        foreach ($collection->getItems() as $product) {
            $sku = $product->getSku();

            if (!is_string($sku)) {
                throw new RuntimeException('A stress configurable product has an invalid SKU.');
            }

            $skus[] = $sku;
        }

        return $skus;
    }

    /**
     * @return array<string, string>
     */
    public function getDescriptionsBySku(): array
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('description');
        $collection->addFieldToFilter('sku', ['like' => self::SKU_PREFIX . '%']);
        $descriptions = [];

        foreach ($collection->getItems() as $product) {
            $sku = $product->getSku();
            $description = $product->getDescription();

            if (!is_string($sku) || !is_string($description)) {
                throw new RuntimeException('A stress product has invalid source data.');
            }

            $descriptions[$sku] = $description;
        }

        ksort($descriptions);

        return $descriptions;
    }

    private function deleteProductsByType(string $typeId): void
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('sku');
        $collection->addFieldToFilter('sku', ['like' => self::SKU_PREFIX . '%']);
        $collection->addFieldToFilter('type_id', $typeId);

        foreach ($collection->getItems() as $product) {
            $sku = $product->getSku();

            if (!is_string($sku)) {
                throw new RuntimeException('A stress product has an invalid SKU.');
            }

            try {
                $this->productRepository->deleteById($sku);
            } catch (NoSuchEntityException) {
                continue;
            }
        }
    }

    private function createStandaloneSimpleProducts(): void
    {
        $productCount = $this->configuration->getTotalProductCount();

        for ($productNumber = 1; $productNumber <= $productCount; $productNumber++) {
            $sku = $this->getStandaloneSimpleSku($productNumber);
            $this->productCreator->createStandaloneSimple(
                $sku,
                sprintf('AI Search Stress Simple %04d', $productNumber),
                $productNumber
            );
        }
    }

    /**
     * @return list<int>
     */
    private function getProductIdsByType(?string $typeId): array
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addFieldToFilter('sku', ['like' => self::SKU_PREFIX . '%']);

        if ($typeId !== null) {
            $collection->addFieldToFilter('type_id', $typeId);
        }

        $collection->setOrder('entity_id');
        $productIds = [];

        foreach ($collection->getAllIds() as $productId) {
            $normalizedId = filter_var($productId, FILTER_VALIDATE_INT);

            if ($normalizedId === false || $normalizedId < 1) {
                throw new RuntimeException('A stress product has an invalid ID.');
            }

            $productIds[] = $normalizedId;
        }

        return $productIds;
    }

    private function getConfigurableSku(int $parentNumber): string
    {
        return sprintf('%sconfigurable-%04d', self::SKU_PREFIX, $parentNumber);
    }

    private function getSimpleSku(int $parentNumber, int $childNumber): string
    {
        return sprintf('%ssimple-%04d-%02d', self::SKU_PREFIX, $parentNumber, $childNumber);
    }

    private function getStandaloneSimpleSku(int $productNumber): string
    {
        return sprintf('%ssimple-%04d', self::SKU_PREFIX, $productNumber);
    }
}
