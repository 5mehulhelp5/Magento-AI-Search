<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ui\Component\Listing\Column\Backlog;

use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class ViewAction extends Column
{
    private const string VIEW_URL = 'davidbel_ai_search/backlog/view';

    /**
     * @param array<string, mixed> $components
     * @param array<string, mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $backendUrl,
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
         *             backlog_id: int|string,
         *             actions?: array<string, mixed>
         *         }>
         *     }
         * } $dataSource
         */
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as &$item) {
            $backlogId = (int) $item[EmbeddingBacklogInterface::BACKLOG_ID];
            $item['actions']['view'] = [
                'href' => $this->backendUrl->getUrl(
                    self::VIEW_URL,
                    [EmbeddingBacklogInterface::BACKLOG_ID => $backlogId]
                ),
                'label' => __('View'),
            ];
        }

        return $dataSource;
    }
}
