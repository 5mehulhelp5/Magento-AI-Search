<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Block\Adminhtml\System\Config;

class SemanticSearchSource extends DashboardNavigation
{
    public function getFormHtml(): string
    {
        $title = $this->escapeHtml((string) __('Important:'));
        $message = $this->escapeHtml(
            (string) __(
                'Changing any setting on this page, except the AI server endpoint, API key, '
                . 'and request timeout, requires '
                . 'a full AI Search rebuild, which starts automatically. '
                . 'The rebuild may reprocess and re-embed the entire catalog, send many requests to the '
                . 'configured AI server, and take a significant amount of time.'
            )
        );

        return sprintf(
            '<div class="message message-warning"><strong>%s</strong> %s</div>%s',
            $title,
            $message,
            parent::getFormHtml()
        );
    }
}
