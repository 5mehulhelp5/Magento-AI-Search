<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Stress\Support\CatalogDataset;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Setup\CategorySetup;
use Magento\Catalog\Setup\CategorySetupFactory;
use Magento\Eav\Model\Config;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Table;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use RuntimeException;

class ConfigurableAttribute
{
    public const string CODE = 'ai_search_stress_variant';

    private const string LABEL = 'AI Search Stress Variant';
    private const int OPTION_COUNT = 9;

    public function __construct(
        private readonly CategorySetupFactory $categorySetupFactory,
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly Config $eavConfig
    ) {
    }

    public function create(): Attribute
    {
        if ($this->exists()) {
            throw new RuntimeException(
                sprintf('Stress attribute "%s" already exists. Run the cleanup stage first.', self::CODE)
            );
        }

        $categorySetup = $this->createCategorySetup();
        $this->moduleDataSetup->startSetup();

        try {
            $categorySetup->addAttribute(Product::ENTITY, self::CODE, $this->getAttributeData());
            $categorySetup->addAttributeToGroup(
                Product::ENTITY,
                $categorySetup->getDefaultAttributeSetId(Product::ENTITY),
                $categorySetup->getDefaultAttributeGroupId(
                    Product::ENTITY,
                    $categorySetup->getDefaultAttributeSetId(Product::ENTITY)
                ),
                self::CODE
            );
        } finally {
            $this->moduleDataSetup->endSetup();
        }

        $this->eavConfig->clear();

        return $this->getAttribute();
    }

    public function remove(): void
    {
        if (!$this->exists()) {
            return;
        }

        $this->moduleDataSetup->startSetup();

        try {
            $this->createCategorySetup()->removeAttribute(Product::ENTITY, self::CODE);
        } finally {
            $this->moduleDataSetup->endSetup();
        }

        $this->eavConfig->clear();
    }

    public function exists(): bool
    {
        $attributeId = filter_var($this->getAttribute()->getId(), FILTER_VALIDATE_INT);

        return $attributeId !== false && $attributeId > 0;
    }

    /**
     * @return list<int>
     */
    public function getOptionIds(Attribute $attribute): array
    {
        $optionIds = [];

        $options = $attribute->setStoreId(0)->getSource()->getAllOptions();

        foreach ($options as $option) {
            if (!is_array($option)) {
                continue;
            }

            $optionId = filter_var($option['value'] ?? null, FILTER_VALIDATE_INT);

            if ($optionId === false || $optionId < 1) {
                continue;
            }

            $optionIds[] = $optionId;
        }

        if (count($optionIds) !== self::OPTION_COUNT) {
            throw new RuntimeException('The stress configurable attribute does not have the expected options.');
        }

        return $optionIds;
    }

    private function createCategorySetup(): CategorySetup
    {
        return $this->categorySetupFactory->create(['setup' => $this->moduleDataSetup]);
    }

    private function getAttribute(): Attribute
    {
        $attribute = $this->eavConfig->getAttribute(Product::ENTITY, self::CODE);

        if (!$attribute instanceof Attribute) {
            throw new RuntimeException('The stress configurable attribute could not be loaded.');
        }

        return $attribute;
    }

    /**
     * @return array<string, mixed>
     */
    private function getAttributeData(): array
    {
        $optionLabels = [];

        for ($optionNumber = 1; $optionNumber <= self::OPTION_COUNT; $optionNumber++) {
            $optionLabels[] = sprintf('Stress Option %02d', $optionNumber);
        }

        return [
            'type' => 'int',
            'label' => self::LABEL,
            'input' => 'select',
            'source' => Table::class,
            'required' => false,
            'user_defined' => true,
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'is_configurable' => true,
            'option' => ['values' => $optionLabels],
        ];
    }
}
