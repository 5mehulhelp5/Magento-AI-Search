<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Block\Adminhtml\System\Config;

use DavidBel\AiSearch\Block\Adminhtml\DashboardButton;
use Magento\Backend\Block\Template;
use Magento\Config\Block\System\Config\Form;
use Magento\Framework\View\Element\AbstractBlock;

class DashboardNavigation extends Form
{
    protected function _prepareLayout(): AbstractBlock
    {
        $navigation = $this->addChild(
            'dashboard_navigation',
            Template::class,
            ['template' => 'DavidBel_AiSearch::system/config/dashboard-navigation.phtml']
        );
        $navigation->addChild(
            'dashboard_button',
            DashboardButton::class,
            ['class' => '']
        );

        return parent::_prepareLayout();
    }

    protected function _afterToHtml(mixed $html): string
    {
        return $this->getChildHtml('dashboard_navigation')
            . parent::_afterToHtml($html);
    }
}
