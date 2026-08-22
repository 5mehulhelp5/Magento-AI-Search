<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Block\Adminhtml\Test;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

class SemanticSearchButton extends Button
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return $this
     */
    protected function _beforeToHtml()
    {
        $this->setLabel(__('Test Semantic Search'));
        $this->setDataAttribute(
            [
                'mage-init' => [
                    'DavidBel_AiSearch/js/test-semantic-search' => [
                        'url' => $this->getUrl('davidbel_ai_search/search/testSemanticSearch'),
                        'stores' => $this->getStoreOptions(),
                        'selectedStoreId' => $this->getDefaultStoreId(),
                    ],
                ],
            ]
        );

        return parent::_beforeToHtml();
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    private function getStoreOptions(): array
    {
        $options = [];

        foreach ($this->storeRepository->getList() as $store) {
            if ($store->getCode() === Store::ADMIN_CODE) {
                continue;
            }

            $label = sprintf('%s (%s)', $store->getName(), $store->getCode());

            if (!(bool) $store->getIsActive()) {
                $label .= ' ' . (string) __('[Disabled]');
            }

            $options[] = [
                'id' => (string) $store->getId(),
                'label' => $label,
            ];
        }

        return $options;
    }

    private function getDefaultStoreId(): string
    {
        return (string) ($this->storeManager->getDefaultStoreView()?->getId() ?? '');
    }
}
