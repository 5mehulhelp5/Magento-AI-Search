<?php
/**
 * davidbel/ai-search by David Belicza
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

class Documents extends AbstractFieldArray
{
    private ?ProductAttribute $productAttributeRenderer = null;
    private ?Composition $compositionRenderer = null;
    private ?ParsingStrategy $parsingStrategyRenderer = null;

    protected function _prepareToRender(): void
    {
        $this->addColumn(
            'attribute_code',
            [
                'label' => __('Attribute'),
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
        $this->_addAfter = false;
        $this->_addButtonLabel = (string) __('Add Document');
    }

    protected function _prepareArrayRow(DataObject $row): void
    {
        $row->setData(
            'option_extra_attrs',
            [
                $this->getOptionKey(
                    $this->getProductAttributeRenderer(),
                    $this->getStringData($row, 'attribute_code')
                ) => 'selected="selected"',
                $this->getOptionKey(
                    $this->getCompositionRenderer(),
                    $this->getStringData($row, 'composite')
                ) => 'selected="selected"',
                $this->getOptionKey(
                    $this->getParsingStrategyRenderer(),
                    $this->getStringData($row, 'parsing_strategy')
                ) => 'selected="selected"',
            ]
        );
    }

    private function getProductAttributeRenderer(): ProductAttribute
    {
        $this->productAttributeRenderer ??= $this->getLayout()->createBlock(
            ProductAttribute::class,
            '',
            ['data' => ['is_render_to_js_template' => true]]
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

    private function getStringData(DataObject $row, string $key): string
    {
        $value = $row->getData($key);

        return is_scalar($value) ? (string) $value : '';
    }
}
