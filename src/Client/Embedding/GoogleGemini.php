<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Client\Embedding;

use DavidBel\AiSearch\Client\Embedding\Base\EmbedderClientInterface;
use DavidBel\AiSearch\Client\Embedding\GoogleGemini\EndpointBuilder;
use DavidBel\AiSearch\Client\Embedding\GoogleGemini\RequestBuilder;
use DavidBel\AiSearch\Client\Embedding\GoogleGemini\ResponseDecoderFactory;
use DavidBel\AiSearch\Config\EmbedderConfig;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex\QueryConfigurationSnapshot;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\RequestOptions;
use Magento\Framework\Serialize\SerializerInterface;
use RuntimeException;
use UnexpectedValueException;

class GoogleGemini implements EmbedderClientInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly SerializerInterface $serializer,
        private readonly EmbedderConfig $embedderConfig,
        private readonly EndpointBuilder $endpointBuilder,
        private readonly RequestBuilder $requestBuilder,
        private readonly ResponseDecoderFactory $responseDecoderFactory
    ) {
    }

    /**
     * @param list<\DavidBel\AiSearch\Client\Embedding\Base\EmbeddingInput> $inputs
     */
    public function embedDocumentsAsync(array $inputs): PromiseInterface
    {
        if ($inputs === []) {
            return Create::promiseFor([]);
        }

        $apiKey = $this->embedderConfig->getApiKey();

        if ($apiKey === null) {
            throw new UnexpectedValueException(
                'An API key is required for the Google Gemini native embedding protocol.'
            );
        }

        $vectorDimensions = $this->embedderConfig->getVectorDimensions();
        $payload = $this->serializer->serialize(
            $this->requestBuilder->execute(
                $inputs,
                $this->embedderConfig->getEmbeddingModel(),
                $vectorDimensions,
                $this->embedderConfig->getEmbedderDocumentTemplate()
            )
        );

        if (!is_string($payload)) {
            throw new UnexpectedValueException('Embedding request could not be serialized.');
        }

        $responseDecoder = $this->responseDecoderFactory->create([
            'vectorDimensions' => $vectorDimensions,
            'inputCount' => count($inputs),
        ]);

        return $this->client->requestAsync(
            'POST',
            $this->endpointBuilder->getBatchEmbeddingEndpoint(
                $this->embedderConfig->getEmbeddingEndpoint(),
                $this->embedderConfig->getEmbeddingModel()
            ),
            [
                RequestOptions::BODY => $payload,
                RequestOptions::HEADERS => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $apiKey,
                ],
                RequestOptions::HTTP_ERRORS => false,
                RequestOptions::TIMEOUT => $this->embedderConfig->getRequestTimeoutSeconds(),
            ]
        )->then([$responseDecoder, 'execute']);
    }

    public function embedQueryAsync(
        string $queryText,
        int $requestTimeoutSeconds,
        QueryConfigurationSnapshot $configurationSnapshot
    ): PromiseInterface {
        throw new RuntimeException('Google Gemini native query embedding is not implemented.');
    }
}
