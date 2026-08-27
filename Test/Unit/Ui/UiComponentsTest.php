<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Ui;

use DavidBel\AiSearch\Model\Chunk;
use DavidBel\AiSearch\Model\Document;
use DavidBel\AiSearch\Model\EmbeddingBacklog;
use DavidBel\AiSearch\Model\ResourceModel\Chunk\Collection as ChunkCollection;
use DavidBel\AiSearch\Model\ResourceModel\Chunk\CollectionFactory as ChunkCollectionFactory;
use DavidBel\AiSearch\Model\ResourceModel\Document\Collection as DocumentCollection;
use DavidBel\AiSearch\Model\ResourceModel\Document\CollectionFactory as DocumentCollectionFactory;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Collection as BacklogCollection;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory as BacklogCollectionFactory;
use DavidBel\AiSearch\Test\Unit\TestDouble\GeneratedFactoryStub;
use DavidBel\AiSearch\Ui\Component\Listing\Column\Backlog\ChunkViewLink;
use DavidBel\AiSearch\Ui\Component\Listing\Column\Backlog\FullReindexStatusOptions;
use DavidBel\AiSearch\Ui\Component\Listing\Column\Backlog\OperationOptions;
use DavidBel\AiSearch\Ui\Component\Listing\Column\Backlog\StatusOptions;
use DavidBel\AiSearch\Ui\Component\Listing\Column\Backlog\ViewAction as BacklogViewAction;
use DavidBel\AiSearch\Ui\Component\Listing\Column\Chunk\DocumentViewLink;
use DavidBel\AiSearch\Ui\Component\Listing\Column\Chunk\ViewAction as ChunkViewAction;
use DavidBel\AiSearch\Ui\Component\Listing\Column\Document\ProductEditLink;
use DavidBel\AiSearch\Ui\Component\Listing\Column\Document\ViewAction as DocumentViewAction;
use DavidBel\AiSearch\Ui\DataProvider\Backlog\ViewDataProvider as BacklogViewDataProvider;
use DavidBel\AiSearch\Ui\DataProvider\Chunk\ViewDataProvider as ChunkViewDataProvider;
use DavidBel\AiSearch\Ui\DataProvider\Document\ViewDataProvider as DocumentViewDataProvider;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use PHPUnit\Framework\TestCase;

class UiComponentsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(
            BacklogCollectionFactory::class,
            ChunkCollectionFactory::class,
            DocumentCollectionFactory::class
        );
    }

    public function testBacklogChunkLinkEscapesAdminUrl(): void
    {
        $url = self::createStub(UrlInterface::class);
        $url->method('getUrl')->willReturn('url&value');
        $escaper = self::createStub(Escaper::class);
        $escaper->method('escapeUrl')->willReturn('url&amp;value');
        $column = new ChunkViewLink($this->uiContext(), $this->uiFactory(), $url, $escaper);

        self::assertSame(
            ['data' => ['items' => [['chunk_id' => '<a href="url&amp;value">10</a>']]]],
            $column->prepareDataSource(['data' => ['items' => [['chunk_id' => '10']]]])
        );
        self::assertSame([], $column->prepareDataSource([]));
    }

    public function testChunkDocumentLinkEscapesAdminUrl(): void
    {
        $url = self::createStub(UrlInterface::class);
        $url->method('getUrl')->willReturn('document-url');
        $escaper = self::createStub(Escaper::class);
        $escaper->method('escapeUrl')->willReturn('escaped-url');
        $column = new DocumentViewLink($this->uiContext(), $this->uiFactory(), $url, $escaper);

        self::assertSame(
            ['data' => ['items' => [['document_id' => '<a href="escaped-url">20</a>']]]],
            $column->prepareDataSource(['data' => ['items' => [['document_id' => 20]]]])
        );
        self::assertSame([], $column->prepareDataSource([]));
    }

    public function testProductEditLinkChangesOnlyProductEntities(): void
    {
        $url = self::createStub(UrlInterface::class);
        $url->method('getUrl')->willReturn('product-url');
        $escaper = self::createStub(Escaper::class);
        $escaper->method('escapeUrl')->willReturn('escaped-product-url');
        $column = new ProductEditLink($this->uiContext(), $this->uiFactory(), $url, $escaper);

        self::assertSame(
            [
                'data' => [
                    'items' => [
                        [
                            'source_entity_type' => 'product',
                            'source_entity_id' => '<a href="escaped-product-url">10</a>',
                            'store_id' => 2,
                        ],
                        [
                            'source_entity_type' => 'other',
                            'source_entity_id' => 11,
                            'store_id' => 2,
                        ],
                    ],
                ],
            ],
            $column->prepareDataSource([
                'data' => [
                    'items' => [
                        ['source_entity_type' => 'product', 'source_entity_id' => 10, 'store_id' => 2],
                        ['source_entity_type' => 'other', 'source_entity_id' => 11, 'store_id' => 2],
                    ],
                ],
            ])
        );
        self::assertSame([], $column->prepareDataSource([]));
    }

    public function testBacklogViewActionAddsViewUrl(): void
    {
        $column = new BacklogViewAction(
            $this->uiContext(),
            $this->uiFactory(),
            $this->backendUrl('backlog-url')
        );
        $result = $column->prepareDataSource(['data' => ['items' => [['backlog_id' => 10]]]]);
        /** @var array{data: array{items: list<array{actions: array{view: array{href: string, label: \Magento\Framework\Phrase}}}>}} $result */

        self::assertSame('backlog-url', $result['data']['items'][0]['actions']['view']['href']);
        self::assertSame('View', (string) $result['data']['items'][0]['actions']['view']['label']);
        self::assertSame([], $column->prepareDataSource([]));
    }

    public function testChunkViewActionAddsViewUrl(): void
    {
        $column = new ChunkViewAction(
            $this->uiContext(),
            $this->uiFactory(),
            $this->backendUrl('chunk-url')
        );
        $result = $column->prepareDataSource(['data' => ['items' => [['chunk_id' => 10]]]]);
        /** @var array{data: array{items: list<array{actions: array{view: array{href: string}}}>}} $result */

        self::assertSame('chunk-url', $result['data']['items'][0]['actions']['view']['href']);
        self::assertSame([], $column->prepareDataSource([]));
    }

    public function testDocumentViewActionAddsViewUrl(): void
    {
        $column = new DocumentViewAction(
            $this->uiContext(),
            $this->uiFactory(),
            $this->backendUrl('document-url')
        );
        $result = $column->prepareDataSource(['data' => ['items' => [['document_id' => 10]]]]);
        /** @var array{data: array{items: list<array{actions: array{view: array{href: string}}}>}} $result */

        self::assertSame('document-url', $result['data']['items'][0]['actions']['view']['href']);
        self::assertSame([], $column->prepareDataSource([]));
    }

    public function testBacklogOptionsExposeEveryEnumCase(): void
    {
        $statuses = (new StatusOptions())->toOptionArray();
        $operations = (new OperationOptions())->toOptionArray();
        $fullReindexStatuses = (new FullReindexStatusOptions())->toOptionArray();

        self::assertSame('Pending', (string) $statuses[0]['label']);
        self::assertSame('Upsert', (string) $operations[0]['label']);
        self::assertSame('Delta', (string) $fullReindexStatuses[0]['label']);
        self::assertNotEmpty($statuses);
        self::assertNotEmpty($operations);
        self::assertNotEmpty($fullReindexStatuses);
    }

    public function testBacklogViewDataProviderFiltersLoadsAndCachesData(): void
    {
        $item = self::createStub(EmbeddingBacklog::class);
        $item->method('getBacklogId')->willReturn(10);
        $item->method('getData')->willReturn(['backlog_id' => 10]);
        $collection = $this->createMock(BacklogCollection::class);
        $collection->expects(self::once())
            ->method('addFieldToFilter')
            ->with('backlog_id', '10')
            ->willReturnSelf();
        $collection->expects(self::once())->method('getItems')->willReturn([$item]);
        $factory = self::createStub(BacklogCollectionFactory::class);
        $factory->method('create')->willReturn($collection);
        $provider = new BacklogViewDataProvider(
            'backlog',
            'backlog_id',
            'backlog_id',
            $factory,
            $this->request(10)
        );

        self::assertSame([10 => ['backlog_id' => 10]], $provider->getData());
        self::assertSame([10 => ['backlog_id' => 10]], $provider->getData());
    }

    public function testChunkViewDataProviderFiltersLoadsAndCachesData(): void
    {
        $item = self::createStub(Chunk::class);
        $item->method('getChunkId')->willReturn(20);
        $item->method('getData')->willReturn(['chunk_id' => 20]);
        $collection = $this->createMock(ChunkCollection::class);
        $collection->expects(self::once())
            ->method('addFieldToFilter')
            ->with('chunk_id', '20')
            ->willReturnSelf();
        $collection->expects(self::once())->method('getItems')->willReturn([$item]);
        $factory = self::createStub(ChunkCollectionFactory::class);
        $factory->method('create')->willReturn($collection);
        $provider = new ChunkViewDataProvider(
            'chunk',
            'chunk_id',
            'chunk_id',
            $factory,
            $this->request(20)
        );

        self::assertSame([20 => ['chunk_id' => 20]], $provider->getData());
        self::assertSame([20 => ['chunk_id' => 20]], $provider->getData());
    }

    public function testDocumentViewDataProviderFiltersLoadsAndCachesData(): void
    {
        $item = self::createStub(Document::class);
        $item->method('getDocumentId')->willReturn(30);
        $item->method('getData')->willReturn(['document_id' => 30]);
        $collection = $this->createMock(DocumentCollection::class);
        $collection->expects(self::once())
            ->method('addFieldToFilter')
            ->with('document_id', '30')
            ->willReturnSelf();
        $collection->expects(self::once())->method('getItems')->willReturn([$item]);
        $factory = self::createStub(DocumentCollectionFactory::class);
        $factory->method('create')->willReturn($collection);
        $provider = new DocumentViewDataProvider(
            'document',
            'document_id',
            'document_id',
            $factory,
            $this->request(30)
        );

        self::assertSame([30 => ['document_id' => 30]], $provider->getData());
        self::assertSame([30 => ['document_id' => 30]], $provider->getData());
    }

    private function uiContext(): ContextInterface
    {
        return self::createStub(ContextInterface::class);
    }

    private function uiFactory(): UiComponentFactory
    {
        return self::createStub(UiComponentFactory::class);
    }

    private function backendUrl(string $url): UrlInterface
    {
        $backendUrl = self::createStub(UrlInterface::class);
        $backendUrl->method('getUrl')->willReturn($url);

        return $backendUrl;
    }

    private function request(int $id): RequestInterface
    {
        $request = self::createStub(RequestInterface::class);
        $request->method('getParam')->willReturn($id);

        return $request;
    }
}
