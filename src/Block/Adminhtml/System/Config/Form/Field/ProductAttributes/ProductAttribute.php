<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Block\Adminhtml\System\Config\Form\Field\ProductAttributes;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;

class ProductAttribute extends Select
{
    public function __construct(
        Context $context,
        private readonly CollectionFactory $attributeCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function setInputName(string $inputName): self
    {
        $this->setData('name', $inputName);

        return $this;
    }

    public function setInputId(string $inputId): self
    {
        $this->setId($inputId);

        return $this;
    }

    protected function _toHtml(): string
    {
        $this->setClass('required-entry admin__control-select');

        if ($this->getOptions() !== []) {
            return parent::_toHtml();
        }

        $this->addOption('', __('-- Select Attribute --'));
        $attributes = $this->attributeCollectionFactory->create();
        $attributes->setOrder('frontend_label', 'ASC');
        $attributes->setOrder('attribute_code', 'ASC');

        foreach ($attributes as $attribute) {
            $attributeCode = (string) $attribute->getAttributeCode();

            if ($attributeCode === '') {
                continue;
            }

            $frontendLabel = trim((string) $attribute->getFrontendLabel());
            $this->addOption(
                $attributeCode,
                $frontendLabel === ''
                    ? $attributeCode
                    : sprintf('%s (%s)', $frontendLabel, $attributeCode)
            );
        }

        return parent::_toHtml();
    }
}
