<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Embedding\Client;

use DavidBel\AiSearch\Embedding\Client\OpenAi;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnexpectedValueException;

use function class_alias;
use function class_exists;

class OpenAiTest extends TestCase
{
    private const string MODEL = 'text-embedding-embeddinggemma-300m-qat';
    private const int VECTOR_DIMENSIONS = 768;

    public static function setUpBeforeClass(): void
    {
        if (class_exists(CurlFactory::class, false)) {
            return;
        }

        class_alias(CurlFactoryTestDouble::class, CurlFactory::class);
    }

    public function testReturnsImmediatelyForEmptyInputs(): void
    {
        $curlFactory = $this->createMock(CurlFactory::class);
        $curlFactory->expects(self::never())
            ->method('create');
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::never())
            ->method('serialize');

        self::assertSame([], (new OpenAi($curlFactory, $serializer))->embed([]));
    }

    public function testRequestsEmbeddingsAndMapsThemToInputOrder(): void
    {
        $curl = $this->createSuccessfulCurl();
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())
            ->method('serialize')
            ->with([
                'model' => self::MODEL,
                'input' => ['first input', 'second input'],
                'encoding_format' => 'float',
            ])
            ->willReturn('serialized request');
        $serializer->expects(self::once())
            ->method('unserialize')
            ->with('serialized response')
            ->willReturn([
                'model' => self::MODEL,
                'data' => [
                    [
                        'index' => 1,
                        'embedding' => self::vector(2.5),
                    ],
                    [
                        'index' => 0,
                        'embedding' => self::vector(1),
                    ],
                ],
            ]);

        $client = $this->createClient($curl, $serializer);

        self::assertSame(
            [
                self::vector(1.0),
                self::vector(2.5),
            ],
            $client->embed(['first input', 'second input'])
        );
    }

    public function testRejectsAnUnserializableRequest(): void
    {
        $curl = $this->createMock(Curl::class);
        $curl->expects(self::never())
            ->method('post');
        $serializer = self::createStub(SerializerInterface::class);
        $serializer->method('serialize')
            ->willReturn(false);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Embedding request could not be serialized.');

        $this->createClient($curl, $serializer)->embed(['input']);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function unsuccessfulHttpStatuses(): iterable
    {
        yield 'below success range' => [199];
        yield 'above success range' => [300];
    }

    #[DataProvider('unsuccessfulHttpStatuses')]
    public function testRejectsAnUnsuccessfulHttpStatus(int $status): void
    {
        $curl = $this->createMock(Curl::class);
        $curl->method('getStatus')
            ->willReturn($status);
        $curl->expects(self::never())
            ->method('getBody');
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('serialize')
            ->willReturn('serialized request');
        $serializer->expects(self::never())
            ->method('unserialize');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            sprintf('Embedding request failed with HTTP status %d.', $status)
        );

        $this->createClient($curl, $serializer)->embed(['input']);
    }

    /**
     * @return iterable<string, array{list<string>, mixed, string}>
     */
    public static function invalidResponses(): iterable
    {
        yield from self::invalidResponseStructures();
        yield from self::invalidItemIndexes();
        yield from self::invalidVectors();
    }

    /**
     * @return iterable<string, array{list<string>, mixed, string}>
     */
    private static function invalidResponseStructures(): iterable
    {
        yield 'non-object response' => [
            ['input'],
            null,
            'Embedding response contains an unexpected model.',
        ];
        yield 'unexpected model' => [
            ['input'],
            [
                'model' => 'another-model',
                'data' => [],
            ],
            'Embedding response contains an unexpected model.',
        ];
        yield 'missing data' => [
            ['input'],
            [
                'model' => self::MODEL,
            ],
            'Embedding response contains an unexpected item count.',
        ];
        yield 'unexpected item count' => [
            ['input'],
            [
                'model' => self::MODEL,
                'data' => [],
            ],
            'Embedding response contains an unexpected item count.',
        ];
        yield 'non-object item' => [
            ['input'],
            [
                'model' => self::MODEL,
                'data' => [null],
            ],
            'Embedding response item must be an object.',
        ];
    }

    /**
     * @return iterable<string, array{list<string>, mixed, string}>
     */
    private static function invalidItemIndexes(): iterable
    {
        yield 'non-integer index' => [
            ['input'],
            self::responseWithItem('0', self::vector()),
            'Embedding response contains an invalid item index.',
        ];
        yield 'negative index' => [
            ['input'],
            self::responseWithItem(-1, self::vector()),
            'Embedding response contains an invalid item index.',
        ];
        yield 'out-of-range index' => [
            ['input'],
            self::responseWithItem(1, self::vector()),
            'Embedding response contains an invalid item index.',
        ];
        yield 'duplicate index' => [
            ['first input', 'second input'],
            self::responseWithIndexes(0, 0),
            'Embedding response contains an invalid item index.',
        ];
    }

    /**
     * @return iterable<string, array{list<string>, mixed, string}>
     */
    private static function invalidVectors(): iterable
    {
        yield 'non-list vector' => [
            ['input'],
            self::responseWithItem(0, ['vector' => 0.5]),
            'Embedding response vector must be a list.',
        ];
        yield 'invalid vector dimension' => [
            ['input'],
            self::responseWithItem(0, [0.5]),
            'Embedding response contains an invalid vector dimension.',
        ];

        $nonNumericVector = self::vector();
        $nonNumericVector[0] = 'invalid';

        yield 'non-numeric vector value' => [
            ['input'],
            self::responseWithItem(0, $nonNumericVector),
            'Embedding vector must contain only numbers.',
        ];

        $nonFiniteVector = self::vector();
        $nonFiniteVector[0] = INF;

        yield 'non-finite vector value' => [
            ['input'],
            self::responseWithItem(0, $nonFiniteVector),
            'Embedding vector must contain only finite numbers.',
        ];
    }

    /**
     * @param list<string> $inputs
     */
    #[DataProvider('invalidResponses')]
    public function testRejectsAnInvalidResponse(
        array $inputs,
        mixed $response,
        string $exceptionMessage
    ): void {
        $curl = self::createStub(Curl::class);
        $curl->method('getStatus')
            ->willReturn(200);
        $curl->method('getBody')
            ->willReturn('serialized response');
        $serializer = self::createStub(SerializerInterface::class);
        $serializer->method('serialize')
            ->willReturn('serialized request');
        $serializer->method('unserialize')
            ->willReturn($response);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $this->createClient($curl, $serializer)->embed($inputs);
    }

    private function createSuccessfulCurl(): Curl
    {
        $curl = $this->createMock(Curl::class);
        $curl->expects(self::once())
            ->method('setHeaders')
            ->with([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);
        $curl->expects(self::once())
            ->method('setTimeout')
            ->with(60);
        $curl->expects(self::once())
            ->method('post')
            ->with('http://127.0.0.1:1234/v1/embeddings', 'serialized request');
        $curl->method('getStatus')
            ->willReturn(200);
        $curl->method('getBody')
            ->willReturn('serialized response');

        return $curl;
    }

    private function createClient(Curl $curl, SerializerInterface $serializer): OpenAi
    {
        $curlFactory = $this->createMock(CurlFactory::class);
        $curlFactory->expects(self::once())
            ->method('create')
            ->willReturn($curl);

        return new OpenAi($curlFactory, $serializer);
    }

    /**
     * @return list<int|float>
     */
    private static function vector(int|float $value = 0.5): array
    {
        return array_fill(0, self::VECTOR_DIMENSIONS, $value);
    }

    /**
     * @return array{model: string, data: list<array{index: mixed, embedding: mixed}>}
     */
    private static function responseWithItem(mixed $index, mixed $embedding): array
    {
        return [
            'model' => self::MODEL,
            'data' => [
                [
                    'index' => $index,
                    'embedding' => $embedding,
                ],
            ],
        ];
    }

    /**
     * @return array{model: string, data: list<array{index: mixed, embedding: list<int|float>}>}
     */
    private static function responseWithIndexes(mixed ...$indexes): array
    {
        $data = [];

        foreach ($indexes as $index) {
            $data[] = [
                'index' => $index,
                'embedding' => self::vector(),
            ];
        }

        return [
            'model' => self::MODEL,
            'data' => $data,
        ];
    }
}
