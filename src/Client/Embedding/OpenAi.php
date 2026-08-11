<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Client\Embedding;

use DavidBel\AiSearch\Client\Embedding\OpenAi\ResponseDecoderFactory;
use DavidBel\AiSearch\Config\EmbedderConfig;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex\QueryConfigurationSnapshot;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\RequestOptions;
use Magento\Framework\Serialize\SerializerInterface;
use UnexpectedValueException;

class OpenAi implements EmbedderClientInterface
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly SerializerInterface $serializer,
        private readonly EmbedderConfig $embedderConfig,
        private readonly ResponseDecoderFactory $responseDecoderFactory
    ) {
    }

    /**
     * @param list<EmbeddingInput> $inputs
     */
    public function embedDocumentsAsync(array $inputs): PromiseInterface
    {
        $requestInputs = [];
        $documentTemplate = $this->embedderConfig->getEmbedderDocumentTemplate();

        foreach ($inputs as $input) {
            $requestInputs[] = strtr(
                $documentTemplate,
                [
                    '{title}' => $input->title ?? 'none',
                    '{text}' => $input->text,
                ]
            );
        }

        return $this->sendAsync(
            $requestInputs,
            $this->embedderConfig->getEmbeddingModel(),
            $this->embedderConfig->getVectorDimensions(),
            $this->embedderConfig->getRequestTimeoutSeconds()
        );
    }

    public function embedQueryAsync(
        string $queryText,
        int $requestTimeoutSeconds,
        QueryConfigurationSnapshot $configurationSnapshot
    ): PromiseInterface {
        return $this->sendAsync(
            [
                strtr(
                    $configurationSnapshot->queryTemplate,
                    ['{text}' => $queryText]
                ),
            ],
            $configurationSnapshot->embeddingModel,
            $configurationSnapshot->vectorDimensions,
            $requestTimeoutSeconds
        );
    }

    /**
     * @param list<string> $inputs
     */
    private function sendAsync(
        array $inputs,
        string $embeddingModel,
        int $vectorDimensions,
        int $requestTimeoutSeconds
    ): PromiseInterface {
        if ($inputs === []) {
            return Create::promiseFor([]);
        }

        $payload = $this->serializer->serialize([
            'model' => $embeddingModel,
            'input' => $inputs,
            'encoding_format' => 'float',
        ]);

        if (!is_string($payload)) {
            throw new UnexpectedValueException('Embedding request could not be serialized.');
        }

        $responseDecoder = $this->responseDecoderFactory->create([
            'embeddingModel' => $embeddingModel,
            'vectorDimensions' => $vectorDimensions,
            'inputCount' => count($inputs),
        ]);

        return $this->client->requestAsync(
            'POST',
            $this->embedderConfig->getBaseUrl() . '/v1/embeddings',
            [
                RequestOptions::BODY => $payload,
                RequestOptions::HEADERS => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                RequestOptions::HTTP_ERRORS => false,
                RequestOptions::TIMEOUT => $requestTimeoutSeconds,
            ]
        )->then([$responseDecoder, 'execute']);
    }
}
