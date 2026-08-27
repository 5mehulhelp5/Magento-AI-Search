<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Controller;

use DavidBel\AiSearch\Api\ChunkRepositoryInterface;
use DavidBel\AiSearch\Api\DocumentRepositoryInterface;
use DavidBel\AiSearch\Api\EmbeddingBacklogRepositoryInterface;
use DavidBel\AiSearch\Block\Adminhtml\Backlog\View\BackButton as BacklogBackButton;
use DavidBel\AiSearch\Block\Adminhtml\Chunk\View\BackButton as ChunkBackButton;
use DavidBel\AiSearch\Block\Adminhtml\Document\View\BackButton as DocumentBackButton;
use DavidBel\AiSearch\Controller\Adminhtml\Backlog\Index as BacklogIndex;
use DavidBel\AiSearch\Controller\Adminhtml\Backlog\View as BacklogView;
use DavidBel\AiSearch\Controller\Adminhtml\Chunk\Index as ChunkIndex;
use DavidBel\AiSearch\Controller\Adminhtml\Chunk\View as ChunkView;
use DavidBel\AiSearch\Controller\Adminhtml\Document\Index as DocumentIndex;
use DavidBel\AiSearch\Controller\Adminhtml\Document\View as DocumentView;
use DavidBel\AiSearch\Test\Unit\TestDouble\GeneratedFactoryStub;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\UrlInterface;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Page\Config;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\PageFactory;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\Stub;

class AdminNavigationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(PageFactory::class);
    }

    public function testBackButtonsReturnTheirListingUrls(): void
    {
        $url = self::createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnMap([
            ['davidbel_ai_search/backlog/index', null, 'backlog-url'],
            ['davidbel_ai_search/chunk/index', null, 'chunk-url'],
            ['davidbel_ai_search/document/index', null, 'document-url'],
        ]);

        self::assertSame(
            "location.href = 'backlog-url';",
            (new BacklogBackButton($url))->getButtonData()['on_click']
        );
        self::assertSame(
            "location.href = 'chunk-url';",
            (new ChunkBackButton($url))->getButtonData()['on_click']
        );
        self::assertSame(
            "location.href = 'document-url';",
            (new DocumentBackButton($url))->getButtonData()['on_click']
        );
    }

    public function testBacklogIndexBuildsListingPage(): void
    {
        $page = $this->page('Backlog');
        $action = new BacklogIndex($this->context(), $this->pageFactory($page));

        self::assertSame($page, $action->execute());
    }

    public function testChunkIndexBuildsListingPage(): void
    {
        $page = $this->page('Chunks');
        $action = new ChunkIndex($this->context(), $this->pageFactory($page));

        self::assertSame($page, $action->execute());
    }

    public function testDocumentIndexBuildsListingPage(): void
    {
        $page = $this->page('Documents');
        $action = new DocumentIndex($this->context(), $this->pageFactory($page));

        self::assertSame($page, $action->execute());
    }

    public function testBacklogViewBuildsItemPage(): void
    {
        $repository = $this->createMock(EmbeddingBacklogRepositoryInterface::class);
        $repository->expects(self::once())->method('get')->with(12);
        $page = $this->page('Backlog Item #12');
        $action = new BacklogView(
            $this->context('backlog_id', 12),
            $this->pageFactory($page),
            $repository
        );

        self::assertSame($page, $action->execute());
    }

    public function testChunkViewBuildsItemPage(): void
    {
        $repository = $this->createMock(ChunkRepositoryInterface::class);
        $repository->expects(self::once())->method('get')->with(13);
        $page = $this->page('Chunk #13');
        $action = new ChunkView(
            $this->context('chunk_id', 13),
            $this->pageFactory($page),
            $repository
        );

        self::assertSame($page, $action->execute());
    }

    public function testDocumentViewBuildsItemPage(): void
    {
        $repository = $this->createMock(DocumentRepositoryInterface::class);
        $repository->expects(self::once())->method('get')->with(14);
        $page = $this->page('Document #14');
        $action = new DocumentView(
            $this->context('document_id', 14),
            $this->pageFactory($page),
            $repository
        );

        self::assertSame($page, $action->execute());
    }

    public function testBacklogViewRedirectsWhenItemDoesNotExist(): void
    {
        $repository = self::createStub(EmbeddingBacklogRepositoryInterface::class);
        $repository->method('get')->willThrowException(new NoSuchEntityException());
        [$context, $redirect] = $this->missingEntityContext('backlog_id', 12, 'backlog');
        $action = new BacklogView($context, $this->pageFactory(self::createStub(Page::class)), $repository);

        self::assertSame($redirect, $action->execute());
    }

    public function testChunkViewRedirectsWhenItemDoesNotExist(): void
    {
        $repository = self::createStub(ChunkRepositoryInterface::class);
        $repository->method('get')->willThrowException(new NoSuchEntityException());
        [$context, $redirect] = $this->missingEntityContext('chunk_id', 13, 'chunk');
        $action = new ChunkView($context, $this->pageFactory(self::createStub(Page::class)), $repository);

        self::assertSame($redirect, $action->execute());
    }

    public function testDocumentViewRedirectsWhenItemDoesNotExist(): void
    {
        $repository = self::createStub(DocumentRepositoryInterface::class);
        $repository->method('get')->willThrowException(new NoSuchEntityException());
        [$context, $redirect] = $this->missingEntityContext('document_id', 14, 'document');
        $action = new DocumentView($context, $this->pageFactory(self::createStub(Page::class)), $repository);

        self::assertSame($redirect, $action->execute());
    }

    private function page(string $expectedTitle): Page
    {
        $title = $this->createMock(Title::class);
        $title->expects(self::once())->method('prepend')->with($expectedTitle);
        $config = self::createStub(Config::class);
        $config->method('getTitle')->willReturn($title);
        $page = self::createStub(Page::class);
        $page->method('getConfig')->willReturn($config);

        return $page;
    }

    private function pageFactory(Page $page): PageFactory
    {
        $factory = self::createStub(PageFactory::class);
        $factory->method('create')->willReturn($page);

        return $factory;
    }

    private function context(?string $parameter = null, int $value = 0): Context&Stub
    {
        $request = self::createStub(RequestInterface::class);

        if ($parameter !== null) {
            $request = $this->createMock(RequestInterface::class);
            $request->expects(self::once())->method('getParam')->with($parameter)->willReturn($value);
        }
        $context = self::createStub(Context::class);
        $context->method('getRequest')->willReturn($request);

        return $context;
    }

    /**
     * @return array{Context, Redirect}
     */
    private function missingEntityContext(string $parameter, int $value, string $route): array
    {
        $redirect = $this->createMock(Redirect::class);
        $redirect->expects(self::once())
            ->method('setPath')
            ->with('davidbel_ai_search/' . $route . '/index')
            ->willReturnSelf();
        $redirectFactory = self::createStub(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($redirect);
        $messages = $this->createMock(ManagerInterface::class);
        $messages->expects(self::once())->method('addErrorMessage');
        $context = $this->context($parameter, $value);
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);
        $context->method('getMessageManager')->willReturn($messages);

        return [$context, $redirect];
    }
}
