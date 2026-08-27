<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Controller\Adminhtml\Dashboard;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'DavidBel_AiSearch::dashboard';
    private const string MENU_ID = 'DavidBel_AiSearch::ai_search_dashboard';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu(self::MENU_ID);
        $resultPage->addBreadcrumb((string) __('AI Search'), (string) __('AI Search'));
        $resultPage->addBreadcrumb((string) __('Dashboard'), (string) __('Dashboard'));
        $resultPage->getConfig()->getTitle()->prepend((string) __('AI Search Dashboard'));

        return $resultPage;
    }
}
