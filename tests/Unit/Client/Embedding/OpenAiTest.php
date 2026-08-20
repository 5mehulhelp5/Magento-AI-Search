<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Client\Embedding;

use DavidBel\AiSearch\Client\Embedding\OpenAi;
use DavidBel\AiSearch\Client\Embedding\EmbeddingInput;
use DavidBel\AiSearch\Client\Embedding\OpenAi\ResponseDecoder;
use DavidBel\AiSearch\Client\Embedding\OpenAi\ResponseDecoderFactory;
use DavidBel\AiSearch\Config\EmbedderConfig;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Psr7\Response;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use UnexpectedValueException;

class OpenAiTest extends TestCase
{
    private const string MODEL = 'text-embedding-embeddinggemma-300m-qat';
    private const int VECTOR_DIMENSIONS = 768;

    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(ResponseDecoderFactory::class);
    }

    public function testReturnsAResolvedPromiseForEmptyInputs(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::never())
            ->method('requestAsync');
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::never())
            ->method('serialize');

        $promise = $this->createOpenAi($httpClient, $serializer)
            ->embedDocumentsAsync([]);

        self::assertSame([], $promise->wait());
    }

    public function testRequestsEmbeddingsAsynchronouslyAndMapsThemToInputOrder(): void
    {
        $promise = (
            $this->createOpenAi(
                $this->createSuccessfulHttpClient(),
                $this->createSuccessfulSerializer()
            )
        )
            ->embedDocumentsAsync(
                $this->createInputs(['first input', 'second input'])
            );

        self::assertSame(
            [
                self::vector(1.0),
                self::vector(2.5),
            ],
            $promise->wait()
        );
    }

    public function testAddsConfiguredBearerTokenToRequest(): void
    {
        $this->createOpenAi(
            $this->createSuccessfulHttpClient('secret'),
            $this->createSuccessfulSerializer(),
            'secret'
        )
            ->embedDocumentsAsync(
                $this->createInputs(['first input', 'second input'])
            )
            ->wait();
    }

    private function createSuccessfulSerializer(): SerializerInterface&MockObject
    {
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

        return $serializer;
    }

    private function createSuccessfulHttpClient(
        ?string $bearerToken = null
    ): ClientInterface&MockObject {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($bearerToken !== null) {
            $headers['Authorization'] = 'Bearer ' . $bearerToken;
        }

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::once())
            ->method('requestAsync')
            ->with(
                'POST',
                'http://host.docker.internal:1234/v1/embeddings',
                [
                    RequestOptions::BODY => 'serialized request',
                    RequestOptions::HEADERS => $headers,
                    RequestOptions::HTTP_ERRORS => false,
                    RequestOptions::TIMEOUT => 60,
                ]
            )
            ->willReturn(
                Create::promiseFor(
                    new Response(200, [], 'serialized response')
                )
            );

        return $httpClient;
    }

    public function testRejectsAnUnserializableRequestBeforeSendingIt(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::never())
            ->method('requestAsync');
        $serializer = self::createStub(SerializerInterface::class);
        $serializer->method('serialize')
            ->willReturn(false);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Embedding request could not be serialized.');

        $this->createOpenAi($httpClient, $serializer)
            ->embedDocumentsAsync($this->createInputs(['input']));
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
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('serialize')
            ->willReturn('serialized request');
        $serializer->expects(self::never())
            ->method('unserialize');
        $httpClient = $this->createHttpClientForResponse(
            new Response($status)
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            sprintf('Embedding request failed with HTTP status %d.', $status)
        );

        $this->createOpenAi($httpClient, $serializer)
            ->embedDocumentsAsync($this->createInputs(['input']))
            ->wait();
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
        $serializer = self::createStub(SerializerInterface::class);
        $serializer->method('serialize')
            ->willReturn('serialized request');
        $serializer->method('unserialize')
            ->willReturn($response);
        $httpClient = $this->createHttpClientForResponse(
            new Response(200, [], 'serialized response')
        );

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $this->createOpenAi($httpClient, $serializer)
            ->embedDocumentsAsync($this->createInputs($inputs))
            ->wait();
    }

    private function createOpenAi(
        ClientInterface $httpClient,
        SerializerInterface $serializer,
        ?string $bearerToken = null
    ): OpenAi {
        $config = self::createStub(EmbedderConfig::class);
        $config->method('getEmbeddingEndpoint')
            ->willReturn('http://host.docker.internal:1234/v1/embeddings');
        $config->method('getBearerToken')->willReturn($bearerToken);
        $config->method('getEmbeddingModel')->willReturn(self::MODEL);
        $config->method('getVectorDimensions')->willReturn(self::VECTOR_DIMENSIONS);
        $config->method('getRequestTimeoutSeconds')->willReturn(60);
        $config->method('getEmbedderDocumentTemplate')->willReturn('{text}');
        $responseDecoderFactory = self::createStub(ResponseDecoderFactory::class);
        $responseDecoderFactory->method('create')
            ->willReturnCallback(
                static function (array $arguments) use ($serializer): ResponseDecoder {
                    $embeddingModel = $arguments['embeddingModel'] ?? null;
                    $vectorDimensions = $arguments['vectorDimensions'] ?? null;
                    $inputCount = $arguments['inputCount'] ?? null;
                    self::assertIsString($embeddingModel);
                    self::assertIsInt($vectorDimensions);
                    self::assertIsInt($inputCount);

                    return new ResponseDecoder(
                        $serializer,
                        $embeddingModel,
                        $vectorDimensions,
                        $inputCount
                    );
                }
            );

        return new OpenAi(
            $httpClient,
            $serializer,
            $config,
            $responseDecoderFactory
        );
    }

    /**
     * @param list<string> $texts
     * @return list<EmbeddingInput>
     */
    private function createInputs(array $texts): array
    {
        return array_map(
            static fn (string $text): EmbeddingInput => new EmbeddingInput(null, $text),
            $texts
        );
    }

    private function createHttpClientForResponse(
        ResponseInterface $response
    ): ClientInterface&MockObject {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::once())
            ->method('requestAsync')
            ->willReturn(Create::promiseFor($response));

        return $httpClient;
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
