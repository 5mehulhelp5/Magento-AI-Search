<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Block\Adminhtml\System\Config\Form\Field\AttributeConfiguration;

use DavidBel\AiSearch\Model\Config\Source\ProductAttribute as ProductAttributeSource;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;

class ProductAttribute extends Select
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly ProductAttributeSource $productAttributeSource,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function setInputName(string $inputName): self
    {
        $this->setData('name', $this->isMultiple() ? $inputName . '[]' : $inputName);

        return $this;
    }

    public function setInputId(string $inputId): self
    {
        $this->setId($inputId);

        return $this;
    }

    protected function _toHtml(): string
    {
        $this->prepareElement();

        if ($this->getOptions() === []) {
            $this->prepareOptions();
        }

        return parent::_toHtml();
    }

    private function prepareOptions(): void
    {
        foreach ($this->productAttributeSource->toOptionArray() as $option) {
            if ($this->isMultiple() && $option['value'] === '') {
                continue;
            }

            $this->addOption($option['value'], $option['label']);
        }
    }

    private function prepareElement(): void
    {
        if ($this->isMultiple()) {
            $this->setClass('required-entry admin__control-multiselect');
            $this->setData('extra_params', 'multiple="multiple" size="6"');

            return;
        }

        $this->setClass('required-entry admin__control-select');
    }

    private function isMultiple(): bool
    {
        return (bool) $this->getData('is_multiple');
    }
}
