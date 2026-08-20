<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Client\Embedding\GoogleGemini;

use Magento\Framework\Serialize\SerializerInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use UnexpectedValueException;

use function is_finite;

class ResponseDecoder
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly int $vectorDimensions,
        private readonly int $inputCount
    ) {
    }

    /**
     * @return list<list<float>>
     */
    public function execute(ResponseInterface $response): array
    {
        $status = $response->getStatusCode();

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf('Embedding request failed with HTTP status %d.', $status));
        }

        return $this->decodeResponse((string) $response->getBody());
    }

    /**
     * @return list<list<float>>
     */
    private function decodeResponse(string $body): array
    {
        $response = $this->serializer->unserialize($body);
        $embeddings = is_array($response) ? ($response['embeddings'] ?? null) : null;

        if (!is_array($embeddings)
            || !array_is_list($embeddings)
            || count($embeddings) !== $this->inputCount
        ) {
            throw new UnexpectedValueException('Embedding response contains an unexpected item count.');
        }

        $vectors = [];

        foreach ($embeddings as $embedding) {
            if (!is_array($embedding)) {
                throw new UnexpectedValueException('Embedding response item must be an object.');
            }

            $vectors[] = $this->validateVector($embedding['values'] ?? null);
        }

        return $vectors;
    }

    /**
     * @return list<float>
     */
    private function validateVector(mixed $embedding): array
    {
        if (!is_array($embedding) || !array_is_list($embedding)) {
            throw new UnexpectedValueException('Embedding response vector must be a list.');
        }

        if (count($embedding) !== $this->vectorDimensions) {
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
