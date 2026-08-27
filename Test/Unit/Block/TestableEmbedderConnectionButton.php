<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Block;

use DavidBel\AiSearch\Block\Adminhtml\Test\EmbedderConnectionButton;

class TestableEmbedderConnectionButton extends EmbedderConnectionButton
{
    public function prepare(): self
    {
        return $this->_beforeToHtml();
    }
}
