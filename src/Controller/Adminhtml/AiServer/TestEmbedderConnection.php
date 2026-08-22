<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Controller\Adminhtml\AiServer;

use DavidBel\AiSearch\Client\Embedding\Base\EmbedderClientPool;
use DavidBel\AiSearch\Client\Embedding\Base\EmbeddingInput;
use DavidBel\AiSearch\Config\EmbedderConfig;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Throwable;
use UnexpectedValueException;

class TestEmbedderConnection extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'DavidBel_AiSearch::config_search_source';
    private const string DASHBOARD_RESOURCE = 'DavidBel_AiSearch::dashboard';
    private const int EXPECTED_EMBEDDING_COUNT = 2;

    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly EmbedderClientPool $embedderClientPool,
        private readonly EmbedderConfig $embedderConfig
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();
        $configuration = null;

        try {
            $configuration = $this->getConfiguration();
            $vectors = $this->embedderClientPool
                ->getClient()
                ->embedDocumentsAsync(
                    [
                        new EmbeddingInput(null, 'First test.'),
                        new EmbeddingInput(null, 'Second test.'),
                    ]
                )
                ->wait();

            $vectorDimensions = $this->getVectorDimensions($vectors);

            return $result->setData(
                [
                    'success' => true,
                    'message' => (string) __(
                        'Connection successful. The configured AI server returned %1 embeddings with %2 dimensions.',
                        self::EXPECTED_EMBEDDING_COUNT,
                        $vectorDimensions
                    ),
                    'configuration' => $configuration,
                    'error_message' => null,
                ]
            );
        } catch (Throwable $exception) {
            return $result->setData(
                [
                    'success' => false,
                    'message' => (string) __('The connection test failed.'),
                    'configuration' => $configuration,
                    'error_message' => $exception->getMessage(),
                ]
            );
        }
    }

    /**
     * @return array{url: string, protocol: string, api_key_configured: bool, model: string}
     */
    private function getConfiguration(): array
    {
        return [
            'url' => $this->embedderConfig->getEmbeddingEndpoint(),
            'protocol' => $this->embedderConfig->getEmbeddingApiProtocol(),
            'api_key_configured' => $this->embedderConfig->getApiKey() !== null,
            'model' => $this->embedderConfig->getEmbeddingModel(),
        ];
    }

    private function getVectorDimensions(mixed $vectors): int
    {
        if (!is_array($vectors)
            || !array_is_list($vectors)
            || count($vectors) !== self::EXPECTED_EMBEDDING_COUNT
        ) {
            throw new UnexpectedValueException('The AI server returned an unexpected embedding count.');
        }

        $vector = reset($vectors);

        if (!is_array($vector) || !array_is_list($vector) || $vector === []) {
            throw new UnexpectedValueException('The AI server returned an invalid embedding vector.');
        }

        return count($vector);
    }

    protected function _isAllowed(): bool
    {
        return parent::_isAllowed() || $this->_authorization->isAllowed(self::DASHBOARD_RESOURCE);
    }
}
