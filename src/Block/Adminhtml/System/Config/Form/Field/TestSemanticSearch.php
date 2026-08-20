<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Block\Adminhtml\System\Config\Form\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

class TestSemanticSearch extends Field
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

    public function render(AbstractElement $element): string
    {
        $element->unsScope()
            ->unsCanUseWebsiteValue()
            ->unsCanUseDefaultValue();

        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        $button = $this->getLayout()->createBlock(
            Button::class,
            '',
            [
                'data' => [
                    'id' => $element->getHtmlId(),
                    'label' => __('Test Semantic Search'),
                    'data_attribute' => [
                        'mage-init' => [
                            'DavidBel_AiSearch/js/test-semantic-search' => [
                                'url' => $this->getUrl('davidbel_ai_search/search/testSemanticSearch'),
                                'stores' => $this->getStoreOptions(),
                                'selectedStoreId' => $this->getDefaultStoreId(),
                            ],
                        ],
                    ],
                ],
            ]
        );

        return $button->toHtml();
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
