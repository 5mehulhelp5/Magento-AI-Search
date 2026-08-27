<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Client\Embedding;

use DavidBel\AiSearch\Client\Embedding\Base\HttpResponseDecoder;
use DavidBel\AiSearch\Client\Embedding\Base\ResponseValidator;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use UnexpectedValueException;

class BaseTest extends TestCase
{
    public function testDecoderUsesFallbackWhenErrorResponseCannotBeDecoded(): void
    {
        $serializer = self::createStub(SerializerInterface::class);
        $serializer->method('unserialize')->willThrowException(new RuntimeException('invalid json'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Embedding request failed with HTTP status 500.');

        (new HttpResponseDecoder($serializer))->decode($this->response(500));
    }

    public function testDecoderUsesProviderErrorMessage(): void
    {
        $serializer = self::createStub(SerializerInterface::class);
        $serializer->method('unserialize')->willReturn([
            'error' => ['message' => 'Provider unavailable.'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Provider unavailable.');

        (new HttpResponseDecoder($serializer))->decode($this->response(503));
    }

    public function testValidatorRejectsUnorderedItems(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('unexpected item count');

        (new ResponseValidator())->validateOrderedItems(['item' => []], 1);
    }

    private function response(int $status): ResponseInterface
    {
        $body = self::createStub(StreamInterface::class);
        $body->method('__toString')->willReturn('response');
        $response = self::createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getBody')->willReturn($body);

        return $response;
    }
}
