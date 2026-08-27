<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Controller\Adminhtml\Chunk;

use DavidBel\AiSearch\Api\ChunkRepositoryInterface;
use DavidBel\AiSearch\Api\Data\ChunkInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;

class View extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'DavidBel_AiSearch::chunks';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly ChunkRepositoryInterface $chunkRepository
    ) {
        parent::__construct($context);
    }

    public function execute(): Page|Redirect
    {
        /** @var int|string|null $requestedChunkId */
        $requestedChunkId = $this->getRequest()->getParam(ChunkInterface::CHUNK_ID);
        $chunkId = (int) $requestedChunkId;

        try {
            $this->chunkRepository->get($chunkId);
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('The chunk no longer exists.'));

            return $this->resultRedirectFactory->create()->setPath('davidbel_ai_search/chunk/index');
        }

        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu(self::ADMIN_RESOURCE);
        $resultPage->addBreadcrumb((string) __('AI Search'), (string) __('AI Search'));
        $resultPage->addBreadcrumb((string) __('Chunks'), (string) __('Chunks'));
        $resultPage->addBreadcrumb((string) __('Chunk #%1', $chunkId), (string) __('Chunk #%1', $chunkId));
        $resultPage->getConfig()->getTitle()->prepend((string) __('Chunk #%1', $chunkId));

        return $resultPage;
    }
}
