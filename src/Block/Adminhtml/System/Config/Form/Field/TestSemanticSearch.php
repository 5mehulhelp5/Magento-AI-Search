<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Block\Adminhtml\System\Config\Form\Field;

use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class TestSemanticSearch extends Field
{
    public function render(AbstractElement $element): string
    {
        $element->unsScope()
            ->unsCanUseWebsiteValue()
            ->unsCanUseDefaultValue();

        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        $button = $this->getLayout()->createBlock(
            Button::class,
            '',
            [
                'data' => [
                    'id' => $element->getHtmlId(),
                    'label' => __('Test Semantic Search'),
                    'data_attribute' => [
                        'mage-init' => [
                            'DavidBel_AiSearch/js/test-semantic-search' => [
                                'url' => $this->getUrl('davidbel_ai_search/search/testSemanticSearch'),
                            ],
                        ],
                    ],
                ],
            ]
        );

        return $button->toHtml();
    }
}
