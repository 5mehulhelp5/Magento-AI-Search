<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Controller\Adminhtml\Document;

use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Api\DocumentRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;

class View extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'DavidBel_AiSearch::documents';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly DocumentRepositoryInterface $documentRepository
    ) {
        parent::__construct($context);
    }

    public function execute(): Page|Redirect
    {
        /** @var int|string|null $requestedDocumentId */
        $requestedDocumentId = $this->getRequest()->getParam(DocumentInterface::DOCUMENT_ID);
        $documentId = (int) $requestedDocumentId;

        try {
            $this->documentRepository->get($documentId);
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('The document no longer exists.'));

            return $this->resultRedirectFactory->create()->setPath('davidbel_ai_search/document/index');
        }

        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu(self::ADMIN_RESOURCE);
        $resultPage->addBreadcrumb((string) __('AI Search'), (string) __('AI Search'));
        $resultPage->addBreadcrumb((string) __('Documents'), (string) __('Documents'));
        $resultPage->addBreadcrumb(
            (string) __('Document #%1', $documentId),
            (string) __('Document #%1', $documentId)
        );
        $resultPage->getConfig()->getTitle()->prepend((string) __('Document #%1', $documentId));

        return $resultPage;
    }
}
