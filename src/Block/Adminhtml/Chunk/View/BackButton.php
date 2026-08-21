<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Block\Adminhtml\Chunk\View;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class BackButton implements ButtonProviderInterface
{
    public function __construct(
        private readonly UrlInterface $backendUrl
    ) {
    }

    /**
     * @return array<string, int|string|\Magento\Framework\Phrase>
     */
    public function getButtonData(): array
    {
        return [
            'label' => __('Back'),
            'on_click' => sprintf(
                "location.href = '%s';",
                $this->backendUrl->getUrl('davidbel_ai_search/chunk/index')
            ),
            'class' => 'back',
            'sort_order' => 10,
        ];
    }
}
