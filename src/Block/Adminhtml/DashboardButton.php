<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Block\Adminhtml;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\Math\Random;
use Magento\Framework\View\Helper\SecureHtmlRenderer;

class DashboardButton extends Button
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly UrlInterface $backendUrl,
        Random $random,
        SecureHtmlRenderer $secureHtmlRenderer,
        array $data = []
    ) {
        $data['label'] = __('AI Search Dashboard');
        $data['class'] = 'primary';
        $data['on_click'] = sprintf(
            "location.href = '%s';",
            $this->backendUrl->getUrl('davidbel_ai_search/dashboard/index')
        );

        parent::__construct($context, $data, $random, $secureHtmlRenderer);
    }
}
