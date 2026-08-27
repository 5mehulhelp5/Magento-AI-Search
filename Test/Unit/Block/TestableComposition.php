<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Block;

use DavidBel\AiSearch\Block\Adminhtml\System\Config\Form\Field\AttributeConfiguration\Composition;

class TestableComposition extends Composition
{
    public function renderSelect(): string
    {
        return $this->_toHtml();
    }
}
