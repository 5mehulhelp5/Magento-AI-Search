<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Block;

use DavidBel\AiSearch\Block\Adminhtml\System\Config\Form\Field\AttributeConfiguration\ParsingStrategy;

class TestableParsingStrategy extends ParsingStrategy
{
    public function renderSelect(): string
    {
        return $this->_toHtml();
    }
}
