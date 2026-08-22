<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Client\Embedding\OpenAi;

use DavidBel\AiSearch\Client\Embedding\Base\HttpResponseDecoder;
use DavidBel\AiSearch\Client\Embedding\Base\ResponseValidator;
use Psr\Http\Message\ResponseInterface;
use UnexpectedValueException;

class ResponseDecoder
{
    public function __construct(
        private readonly HttpResponseDecoder $httpResponseDecoder,
        private readonly ResponseValidator $responseValidator,
        private readonly string $embeddingModel,
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

        if (!is_array($response)
            || ($response['model'] ?? null) !== $this->embeddingModel
        ) {
            throw new UnexpectedValueException('Embedding response contains an unexpected model.');
        }

        $data = $this->responseValidator->validateItems(
            $response['data'] ?? null,
            $this->inputCount
        );

        return $this->mapVectorsToInputOrder($data);
    }

    /**
     * @param array<array-key, mixed> $data
     * @return list<list<float>>
     */
    private function mapVectorsToInputOrder(array $data): array
    {
        $vectorsByIndex = [];

        foreach ($data as $item) {
            $item = $this->responseValidator->validateItem($item);

            $index = $item['index'] ?? null;

            if (!is_int($index)
                || $index < 0
                || $index >= $this->inputCount
                || isset($vectorsByIndex[$index])
            ) {
                throw new UnexpectedValueException('Embedding response contains an invalid item index.');
            }

            $vectorsByIndex[$index] = $this->responseValidator->validateVector(
                $item['embedding'] ?? null,
                $this->vectorDimensions
            );
        }

        ksort($vectorsByIndex);

        return array_values($vectorsByIndex);
    }
}
