<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Client\Embedding\GoogleGemini;

use DavidBel\AiSearch\Client\Embedding\Base\HttpResponseDecoder;
use DavidBel\AiSearch\Client\Embedding\Base\ResponseValidator;
use Psr\Http\Message\ResponseInterface;

class ResponseDecoder
{
    public function __construct(
        private readonly HttpResponseDecoder $httpResponseDecoder,
        private readonly ResponseValidator $responseValidator,
        private readonly int $vectorDimensions,
        private readonly int $inputCount
    ) {
    }

    /**
     * @return list<list<float>>
     */
    public function execute(ResponseInterface $response): array
    {
        $response = $this->httpResponseDecoder->decode($response);
        $embeddings = $this->responseValidator->validateOrderedItems(
            is_array($response) ? ($response['embeddings'] ?? null) : null,
            $this->inputCount
        );

        $vectors = [];

        foreach ($embeddings as $embedding) {
            $embedding = $this->responseValidator->validateItem($embedding);

            $vectors[] = $this->responseValidator->validateVector(
                $embedding['values'] ?? null,
                $this->vectorDimensions
            );
        }

        return $vectors;
    }
}
