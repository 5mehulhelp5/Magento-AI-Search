<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Controller\Adminhtml\AiServer;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

class TestEmbedderConnection extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'DavidBel_AiSearch::config_search_source';

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
                'message' => (string) __(
                    'The test endpoint responded successfully. The AI server connection has not been tested yet.'
                ),
            ]
        );
    }
}
