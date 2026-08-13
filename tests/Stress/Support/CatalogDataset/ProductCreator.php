<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Stress\Support\CatalogDataset;

use Magento\Catalog\Api\Data\ProductExtensionFactory;
use Magento\Catalog\Api\Data\ProductExtensionInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\ConfigurableProduct\Helper\Product\Options\Factory as ConfigurableOptionsFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Store\Model\StoreManagerInterface;
use RuntimeException;

class ProductCreator
{
    public function __construct(
        private readonly ProductFactory $productFactory,
        private readonly ProductExtensionFactory $productExtensionFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ConfigurableOptionsFactory $configurableOptionsFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly DescriptionGenerator $descriptionGenerator
    ) {
    }

    public function createSimple(
        string $sku,
        string $name,
        int $optionId,
        int $sequence
    ): int {
        $product = $this->createProduct($sku, $name, Type::TYPE_SIMPLE, Visibility::VISIBILITY_NOT_VISIBLE);
        $product->setPrice(100 + $sequence);
        $product->setData(ConfigurableAttribute::CODE, $optionId);

        return $this->saveAndGetId($product);
    }

    /**
     * @param list<int> $childIds
     * @param list<int> $optionIds
     */
    public function createConfigurable(
        string $sku,
        string $name,
        array $childIds,
        Attribute $attribute,
        array $optionIds
    ): int {
        $product = $this->createProduct(
            $sku,
            $name,
            Configurable::TYPE_CODE,
            Visibility::VISIBILITY_BOTH
        );
        $extensionAttributes = $product->getExtensionAttributes();

        if (!$extensionAttributes instanceof ProductExtensionInterface) {
            $extensionAttributes = $this->productExtensionFactory->create();
        }

        $attributeId = filter_var($attribute->getId(), FILTER_VALIDATE_INT);

        if ($attributeId === false || $attributeId < 1) {
            throw new RuntimeException('The stress configurable attribute has no persisted ID.');
        }

        $extensionAttributes->setConfigurableProductOptions(
            $this->configurableOptionsFactory->create([
                [
                    'attribute_id' => $attributeId,
                    'code' => $attribute->getAttributeCode(),
                    'label' => $attribute->getStoreLabel(),
                    'position' => 0,
                    'values' => $this->getConfigurableValues($attribute, $optionIds),
                ],
            ])
        );
        $extensionAttributes->setConfigurableProductLinks($childIds);
        $product->setExtensionAttributes($extensionAttributes);

        return $this->saveAndGetId($product);
    }

    private function createProduct(
        string $sku,
        string $name,
        string $typeId,
        int $visibility
    ): Product {
        $defaultStore = $this->storeManager->getDefaultStoreView();

        if ($defaultStore === null) {
            throw new RuntimeException('A default storefront is required for the stress dataset.');
        }

        $product = $this->productFactory->create();
        $product->setStoreId(0);
        $product->setSku($sku);
        $product->setName($name);
        $product->setTypeId($typeId);
        $product->setAttributeSetId($product->getDefaultAttributeSetId());
        $product->setWebsiteIds([$defaultStore->getWebsiteId()]);
        $product->setStatus(Status::STATUS_ENABLED);
        $product->setVisibility($visibility);
        $product->setDescription($this->descriptionGenerator->generate($sku));
        $product->setShortDescription(sprintf('Stress data for %s.', $sku));

        return $product;
    }

    private function saveAndGetId(Product $product): int
    {
        $savedProduct = $this->productRepository->save($product);
        $productId = filter_var($savedProduct->getId(), FILTER_VALIDATE_INT);

        if ($productId === false || $productId < 1) {
            throw new RuntimeException(sprintf('Stress product "%s" has no persisted ID.', $product->getSku()));
        }

        return $productId;
    }

    /**
     * @param list<int> $optionIds
     * @return list<array{label: string, attribute_id: int, value_index: int}>
     */
    private function getConfigurableValues(Attribute $attribute, array $optionIds): array
    {
        $attributeId = filter_var($attribute->getId(), FILTER_VALIDATE_INT);

        if ($attributeId === false || $attributeId < 1) {
            throw new RuntimeException('The stress configurable attribute has no persisted ID.');
        }

        $values = [];

        foreach ($optionIds as $position => $optionId) {
            $values[] = [
                'label' => sprintf('Stress Option %02d', $position + 1),
                'attribute_id' => $attributeId,
                'value_index' => $optionId,
            ];
        }

        return $values;
    }
}
