<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Block;

use DavidBel\AiSearch\Block\Adminhtml\System\Config\DashboardNavigation;
use Magento\Framework\View\Element\AbstractBlock;

class TestableDashboardNavigation extends DashboardNavigation
{
    public function prepareLayout(): AbstractBlock
    {
        return $this->_prepareLayout();
    }

    public function appendNavigation(string $html): string
    {
        return $this->_afterToHtml($html);
    }
}
