<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Block\Adminhtml\Test;

use Magento\Backend\Block\Widget\Button;

class EmbedderConnectionButton extends Button
{
    /**
     * @return $this
     */
    protected function _beforeToHtml()
    {
        $this->setLabel(__('Test Connection'));
        $this->setDataAttribute(
            [
                'mage-init' => [
                    'DavidBel_AiSearch/js/test-embedder-connection' => [
                        'url' => $this->getData('url')
                            ?? $this->getUrl('davidbel_ai_search/aiServer/testEmbedderConnection'),
                    ],
                ],
            ]
        );

        return parent::_beforeToHtml();
    }
}
