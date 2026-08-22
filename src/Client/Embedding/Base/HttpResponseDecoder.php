<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Client\Embedding\Base;

use Magento\Framework\Serialize\SerializerInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

class HttpResponseDecoder
{
    public function __construct(
        private readonly SerializerInterface $serializer
    ) {
    }

    public function decode(ResponseInterface $response): mixed
    {
        $status = $response->getStatusCode();

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(
                $this->getErrorMessage($response, $status),
                $status
            );
        }

        return $this->serializer->unserialize((string) $response->getBody());
    }

    private function getErrorMessage(ResponseInterface $response, int $status): string
    {
        try {
            $responseData = $this->serializer->unserialize((string) $response->getBody());
        } catch (Throwable) {
            return sprintf('Embedding request failed with HTTP status %d.', $status);
        }

        $error = is_array($responseData) ? ($responseData['error'] ?? null) : null;
        $message = is_array($error) ? ($error['message'] ?? null) : null;

        return is_string($message) && trim($message) !== ''
            ? $message
            : sprintf('Embedding request failed with HTTP status %d.', $status);
    }
}
