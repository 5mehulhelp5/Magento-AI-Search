<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ui\Component\Listing\Column\Chunk;

use DavidBel\AiSearch\Api\Data\ChunkInterface;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class DocumentViewLink extends Column
{
    private const string DOCUMENT_VIEW_URL = 'davidbel_ai_search/document/view';

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
         *         items?: list<array{document_id: int|string}>
         *     }
         * } $dataSource
         */
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as &$item) {
            $documentId = (int) $item[ChunkInterface::DOCUMENT_ID];
            $documentViewUrl = $this->backendUrl->getUrl(
                self::DOCUMENT_VIEW_URL,
                [ChunkInterface::DOCUMENT_ID => $documentId]
            );

            $item[ChunkInterface::DOCUMENT_ID] = sprintf(
                '<a href="%s">%d</a>',
                $this->escaper->escapeUrl($documentViewUrl),
                $documentId
            );
        }

        return $dataSource;
    }
}
