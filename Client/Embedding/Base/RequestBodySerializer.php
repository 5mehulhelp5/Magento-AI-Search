<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Client\Embedding\Base;

use Magento\Framework\Serialize\SerializerInterface;
use UnexpectedValueException;

class RequestBodySerializer
{
    public function __construct(
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * @param array<array-key, mixed> $requestBody
     */
    public function serialize(array $requestBody): string
    {
        $payload = $this->serializer->serialize($requestBody);

        if (!is_string($payload)) {
            throw new UnexpectedValueException('Embedding request could not be serialized.');
        }

        return $payload;
    }
}
