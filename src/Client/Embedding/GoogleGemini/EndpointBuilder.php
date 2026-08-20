<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Client\Embedding\GoogleGemini;

class EndpointBuilder
{
    public function getBatchEmbeddingEndpoint(string $baseUrl, string $embeddingModel): string
    {
        return sprintf(
            '%s/models/%s:batchEmbedContents',
            rtrim($baseUrl, '/'),
            rawurlencode($this->getModelName($embeddingModel))
        );
    }

    private function getModelName(string $embeddingModel): string
    {
        if (str_starts_with($embeddingModel, 'models/')) {
            return substr($embeddingModel, strlen('models/'));
        }

        return $embeddingModel;
    }
}
