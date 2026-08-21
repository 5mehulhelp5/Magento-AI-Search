<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ui\Component\Listing\Column\Document;

use DavidBel\AiSearch\Api\Data\DocumentInterface;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class ProductEditLink extends Column
{
    private const string PRODUCT_ENTITY_TYPE = 'product';

    /**
     * @param array<string, mixed> $components
     * @param array<string, mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $backendUrl,
        private readonly Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array<mixed> $dataSource
     * @return array<mixed>
     */
    public function prepareDataSource(array $dataSource): array
    {
        /**
         * @var array{
         *     data?: array{
         *         items?: list<array{
         *             source_entity_type: string,
         *             source_entity_id: int|string,
         *             store_id: int|string
         *         }>
         *     }
         * } $dataSource
         */
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as &$item) {
            if ($item[DocumentInterface::SOURCE_ENTITY_TYPE] !== self::PRODUCT_ENTITY_TYPE) {
                continue;
            }

            $productId = (int) $item[DocumentInterface::SOURCE_ENTITY_ID];
            $storeId = (int) $item[DocumentInterface::STORE_ID];
            $productEditUrl = $this->backendUrl->getUrl(
                'catalog/product/edit',
                [
                    'id' => $productId,
                    'store' => $storeId,
                ]
            );

            $item[DocumentInterface::SOURCE_ENTITY_ID] = sprintf(
                '<a href="%s">%d</a>',
                $this->escaper->escapeUrl($productEditUrl),
                $productId
            );
        }

        return $dataSource;
    }
}
