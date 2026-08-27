<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Block;

use DavidBel\AiSearch\Block\Adminhtml\System\Config\Form\Field\TestSemanticSearch;
use Magento\Framework\Data\Form\Element\AbstractElement;

class TestableSemanticSearchField extends TestSemanticSearch
{
    public function elementHtml(AbstractElement $element): string
    {
        return $this->_getElementHtml($element);
    }
}
