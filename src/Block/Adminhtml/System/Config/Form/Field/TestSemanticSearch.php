<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Block\Adminhtml\System\Config\Form\Field;

use DavidBel\AiSearch\Block\Adminhtml\Test\SemanticSearchButton;
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
            SemanticSearchButton::class,
            '',
            [
                'data' => [
                    'id' => $element->getHtmlId(),
                ],
            ]
        );

        return $button->toHtml();
    }
}
