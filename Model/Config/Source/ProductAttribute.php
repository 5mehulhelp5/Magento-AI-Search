<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class ProductAttribute implements OptionSourceInterface
{
    /**
     * @var list<array{value: string, label: string}>|null
     */
    private ?array $options = null;

    public function __construct(
        private readonly CollectionFactory $attributeCollectionFactory
    ) {
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        if ($this->options === null) {
            $this->options = $this->loadOptions();
        }

        return $this->options;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function loadOptions(): array
    {
        $options = [
            [
                'value' => '',
                'label' => (string) __('-- Select Attribute --'),
            ],
        ];
        $attributes = $this->attributeCollectionFactory->create();
        $attributes->setOrder('frontend_label', 'ASC');
        $attributes->setOrder('attribute_code', 'ASC');

        foreach ($attributes as $attribute) {
            /** @var \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute */
            $attributeCode = $attribute->getAttributeCode();

            if ($attributeCode === '') {
                continue;
            }

            $frontendLabelValue = $attribute->getFrontendLabel();
            $frontendLabel = is_string($frontendLabelValue) ? trim($frontendLabelValue) : '';
            $options[] = [
                'value' => $attributeCode,
                'label' => $frontendLabel === ''
                    ? $attributeCode
                    : sprintf('%s (%s)', $frontendLabel, $attributeCode),
            ];
        }

        return $options;
    }
}
