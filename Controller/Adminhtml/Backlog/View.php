<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Controller\Adminhtml\Backlog;

use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Api\EmbeddingBacklogRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;

class View extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'DavidBel_AiSearch::backlog';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly EmbeddingBacklogRepositoryInterface $embeddingBacklogRepository
    ) {
        parent::__construct($context);
    }

    public function execute(): Page|Redirect
    {
        /** @var int|string|null $requestedBacklogId */
        $requestedBacklogId = $this->getRequest()->getParam(EmbeddingBacklogInterface::BACKLOG_ID);
        $backlogId = (int) $requestedBacklogId;

        try {
            $this->embeddingBacklogRepository->get($backlogId);
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('The backlog item no longer exists.'));

            return $this->resultRedirectFactory->create()->setPath('davidbel_ai_search/backlog/index');
        }

        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu(self::ADMIN_RESOURCE);
        $resultPage->addBreadcrumb((string) __('AI Search'), (string) __('AI Search'));
        $resultPage->addBreadcrumb((string) __('Backlog'), (string) __('Backlog'));
        $resultPage->addBreadcrumb(
            (string) __('Backlog Item #%1', $backlogId),
            (string) __('Backlog Item #%1', $backlogId)
        );
        $resultPage->getConfig()->getTitle()->prepend(
            (string) __('Backlog Item #%1', $backlogId)
        );

        return $resultPage;
    }
}
