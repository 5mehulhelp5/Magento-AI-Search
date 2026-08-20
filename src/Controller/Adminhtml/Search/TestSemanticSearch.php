<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Controller\Adminhtml\Search;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

class TestSemanticSearch extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'DavidBel_AiSearch::config_search_result';

    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        return $result->setData(
            [
                'success' => true,
                'result' => '',
            ]
        );
    }
}
