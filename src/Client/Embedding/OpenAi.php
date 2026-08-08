<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Client\Embedding;

use DavidBel\AiSearch\Api\EmbedderClientInterface;
use DavidBel\AiSearch\Config\EmbedderConfig;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\RequestOptions;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use UnexpectedValueException;

use function is_finite;

class OpenAi implements EmbedderClientInterface
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly SerializerInterface $serializer,
        private readonly EmbedderConfig $embedderConfig
    ) {
    }

    /**
     * @param list<EmbeddingInput> $inputs
     */
    public function embedDocumentsAsync(array $inputs): PromiseInterface
    {
        $requestInputs = [];

        foreach ($inputs as $input) {
            $requestInputs[] = strtr(
                $this->embedderConfig->getDocumentTemplate(),
                [
                    '{title}' => $input->title ?? 'none',
                    '{text}' => $input->text,
                ]
            );
        }

        return $this->sendAsync($requestInputs);
    }

    public function embedQueryAsync(string $queryText): PromiseInterface
    {
        return $this->sendAsync([
            strtr(
                $this->embedderConfig->getQueryTemplate(),
                ['{text}' => $queryText]
            ),
        ]);
    }

    /**
     * @param list<string> $inputs
     */
    private function sendAsync(array $inputs): PromiseInterface
    {
        if ($inputs === []) {
            return Create::promiseFor([]);
        }

        $payload = $this->serializer->serialize([
            'model' => $this->embedderConfig->getModel(),
            'input' => $inputs,
            'encoding_format' => 'float',
        ]);

        if (!is_string($payload)) {
            throw new UnexpectedValueException('Embedding request could not be serialized.');
        }

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
                RequestOptions::TIMEOUT => $this->embedderConfig->getRequestTimeoutSeconds(),
            ]
        )->then(
            fn (ResponseInterface $response): array => $this->decodeHttpResponse(
                $response,
                count($inputs)
            )
        );
    }

    /**
     * @return list<list<float>>
     */
    private function decodeHttpResponse(ResponseInterface $response, int $inputCount): array
    {
        $status = $response->getStatusCode();

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf('Embedding request failed with HTTP status %d.', $status));
        }

        return $this->decodeResponse((string) $response->getBody(), $inputCount);
    }

    /**
     * @return list<list<float>>
     */
    private function decodeResponse(string $body, int $inputCount): array
    {
        $response = $this->serializer->unserialize($body);

        if (!is_array($response) || ($response['model'] ?? null) !== $this->embedderConfig->getModel()) {
            throw new UnexpectedValueException('Embedding response contains an unexpected model.');
        }

        $data = $response['data'] ?? null;

        if (!is_array($data) || count($data) !== $inputCount) {
            throw new UnexpectedValueException('Embedding response contains an unexpected item count.');
        }

        return $this->mapVectorsToInputOrder($data, $inputCount);
    }

    /**
     * @param array<array-key, mixed> $data
     * @return list<list<float>>
     */
    private function mapVectorsToInputOrder(array $data, int $inputCount): array
    {
        $vectorsByIndex = [];

        foreach ($data as $item) {
            if (!is_array($item)) {
                throw new UnexpectedValueException('Embedding response item must be an object.');
            }

            $index = $item['index'] ?? null;

            if (!is_int($index) || $index < 0 || $index >= $inputCount || isset($vectorsByIndex[$index])) {
                throw new UnexpectedValueException('Embedding response contains an invalid item index.');
            }

            $vectorsByIndex[$index] = $this->validateVector($item['embedding'] ?? null);
        }

        ksort($vectorsByIndex);

        return array_values($vectorsByIndex);
    }

    /**
     * @return list<float>
     */
    private function validateVector(mixed $embedding): array
    {
        if (!is_array($embedding) || !array_is_list($embedding)) {
            throw new UnexpectedValueException('Embedding response vector must be a list.');
        }

        if (count($embedding) !== $this->embedderConfig->getVectorDimensions()) {
            throw new UnexpectedValueException('Embedding response contains an invalid vector dimension.');
        }

        $vector = [];

        foreach ($embedding as $value) {
            $vector[] = $this->validateVectorValue($value);
        }

        return $vector;
    }

    private function validateVectorValue(mixed $value): float
    {
        if (!is_int($value) && !is_float($value)) {
            throw new UnexpectedValueException('Embedding vector must contain only numbers.');
        }

        $floatValue = (float) $value;

        if (!is_finite($floatValue)) {
            throw new UnexpectedValueException('Embedding vector must contain only finite numbers.');
        }

        return $floatValue;
    }
}
