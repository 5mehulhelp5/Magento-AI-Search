<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Block\Adminhtml\System\Config\Form\Field;

use DavidBel\AiSearch\Block\Adminhtml\System\Config\Form\Field\AttributeConfiguration\Composition;
use DavidBel\AiSearch\Block\Adminhtml\System\Config\Form\Field\AttributeConfiguration\ParsingStrategy;
use DavidBel\AiSearch\Block\Adminhtml\System\Config\Form\Field\AttributeConfiguration\ProductAttribute;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Html\Select;

class DynamicDocument extends AbstractFieldArray
{
    private ?ProductAttribute $productAttributeRenderer = null;
    private ?Composition $compositionRenderer = null;
    private ?ParsingStrategy $parsingStrategyRenderer = null;

    protected function _prepareToRender(): void
    {
        $this->addColumn(
            'attribute_code',
            [
                'label' => __('Attributes'),
                'renderer' => $this->getProductAttributeRenderer(),
            ]
        );
        $this->addColumn(
            'composite',
            [
                'label' => __('Composition'),
                'renderer' => $this->getCompositionRenderer(),
            ]
        );
        $this->addColumn(
            'parsing_strategy',
            [
                'label' => __('Parsing Strategy'),
                'renderer' => $this->getParsingStrategyRenderer(),
            ]
        );
        $this->addColumn(
            'template',
            [
                'label' => __('Template'),
                'class' => 'input-text required-entry',
                'style' => 'width: 320px;',
            ]
        );
        $this->_addAfter = false;
        $this->_addButtonLabel = (string) __('Add Document Part');
    }

    protected function _prepareArrayRow(DataObject $row): void
    {
        $optionAttributes = [];

        foreach ($this->getAttributeCodes($row) as $attributeCode) {
            $optionAttributes[$this->getOptionKey($this->getProductAttributeRenderer(), $attributeCode)] =
                'selected="selected"';
        }

        $optionAttributes[
            $this->getOptionKey(
                $this->getCompositionRenderer(),
                $this->getStringData($row, 'composite')
            )
        ] = 'selected="selected"';
        $optionAttributes[
            $this->getOptionKey(
                $this->getParsingStrategyRenderer(),
                $this->getStringData($row, 'parsing_strategy')
            )
        ] = 'selected="selected"';

        $row->setData('option_extra_attrs', $optionAttributes);
    }

    private function getProductAttributeRenderer(): ProductAttribute
    {
        $this->productAttributeRenderer ??= $this->getLayout()->createBlock(
            ProductAttribute::class,
            '',
            [
                'data' => [
                    'is_render_to_js_template' => true,
                    'is_multiple' => true,
                ],
            ]
        );

        return $this->productAttributeRenderer;
    }

    private function getCompositionRenderer(): Composition
    {
        $this->compositionRenderer ??= $this->getLayout()->createBlock(
            Composition::class,
            '',
            ['data' => ['is_render_to_js_template' => true]]
        );

        return $this->compositionRenderer;
    }

    private function getParsingStrategyRenderer(): ParsingStrategy
    {
        $this->parsingStrategyRenderer ??= $this->getLayout()->createBlock(
            ParsingStrategy::class,
            '',
            ['data' => ['is_render_to_js_template' => true]]
        );

        return $this->parsingStrategyRenderer;
    }

    private function getOptionKey(Select $renderer, string $value): string
    {
        return 'option_' . $renderer->calcOptionHash($value);
    }

    /**
     * @return list<string>
     */
    private function getAttributeCodes(DataObject $row): array
    {
        $value = $row->getData('attribute_code');
        $attributeCodes = [];

        if (is_array($value)) {
            $attributeCodes = $value;
        }

        if (is_string($value)) {
            $attributeCodes = explode(',', $value);
        }

        $result = [];

        foreach ($attributeCodes as $attributeCode) {
            if (!is_string($attributeCode)) {
                continue;
            }

            $attributeCode = trim($attributeCode);

            if ($attributeCode === '') {
                continue;
            }

            $result[] = $attributeCode;
        }

        return $result;
    }

    private function getStringData(DataObject $row, string $key): string
    {
        $value = $row->getData($key);

        return is_scalar($value) ? (string) $value : '';
    }
}
