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
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class ChunkViewLink extends Column
{
    private const string CHUNK_VIEW_URL = 'davidbel_ai_search/chunk/view';

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
         *         items?: list<array{chunk_id: int|string}>
         *     }
         * } $dataSource
         */
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as &$item) {
            $chunkId = (int) $item[EmbeddingBacklogInterface::CHUNK_ID];
            $chunkViewUrl = $this->backendUrl->getUrl(
                self::CHUNK_VIEW_URL,
                [EmbeddingBacklogInterface::CHUNK_ID => $chunkId]
            );

            $item[EmbeddingBacklogInterface::CHUNK_ID] = sprintf(
                '<a href="%s">%d</a>',
                $this->escaper->escapeUrl($chunkViewUrl),
                $chunkId
            );
        }

        return $dataSource;
    }
}
