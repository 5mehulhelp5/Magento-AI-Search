<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Controller\Adminhtml\Search;

use DavidBel\AiSearch\Controller\Adminhtml\Search\TestSemanticSearch\ResultProvider;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Throwable;
use UnexpectedValueException;

class TestSemanticSearch extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'DavidBel_AiSearch::config_search_result';
    private const string DASHBOARD_RESOURCE = 'DavidBel_AiSearch::dashboard';

    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly ResultProvider $resultProvider
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        try {
            return $result->setData(
                [
                    'success' => true,
                    'result' => $this->resultProvider->getSearchResults(
                        $this->getSearchQuery(),
                        $this->getRequestedStoreId()
                    ),
                ]
            );
        } catch (Throwable $exception) {
            return $result->setData(
                [
                    'success' => false,
                    'message' => (string) __('The semantic search test failed.'),
                    'error_message' => $exception->getMessage(),
                ]
            );
        }
    }

    private function getSearchQuery(): string
    {
        $query = $this->getRequest()->getParam('q');

        if (!is_string($query) || trim($query) === '') {
            throw new UnexpectedValueException('Enter a search query.');
        }

        return trim($query);
    }

    private function getRequestedStoreId(): int
    {
        /** @var int|string $storeId */
        $storeId = $this->getRequest()->getParam('store_id');

        return (int) $storeId;
    }

    protected function _isAllowed(): bool
    {
        return parent::_isAllowed() || $this->_authorization->isAllowed(self::DASHBOARD_RESOURCE);
    }
}
