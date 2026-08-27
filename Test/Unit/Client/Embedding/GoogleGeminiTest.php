<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Client\Embedding;

use DavidBel\AiSearch\Client\Embedding\Base\EmbeddingInput;
use DavidBel\AiSearch\Client\Embedding\Base\HttpResponseDecoder;
use DavidBel\AiSearch\Client\Embedding\Base\RequestBodySerializer;
use DavidBel\AiSearch\Client\Embedding\Base\ResponseValidator;
use DavidBel\AiSearch\Client\Embedding\GoogleGemini;
use DavidBel\AiSearch\Client\Embedding\GoogleGemini\EndpointBuilder;
use DavidBel\AiSearch\Client\Embedding\GoogleGemini\RequestBuilder;
use DavidBel\AiSearch\Client\Embedding\GoogleGemini\ResponseDecoder;
use DavidBel\AiSearch\Client\Embedding\GoogleGemini\ResponseDecoderFactory;
use DavidBel\AiSearch\Config\EmbedderConfig;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex\QueryConfigurationSnapshot;
use DavidBel\AiSearch\Test\Unit\TestDouble\GeneratedFactoryStub;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class GoogleGeminiTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(ResponseDecoderFactory::class);
    }

    public function testBuildsBatchEndpoint(): void
    {
        $builder = new EndpointBuilder();

        self::assertSame(
            'https://example.test/v1/models/text-embedding-004:batchEmbedContents',
            $builder->getBatchEmbeddingEndpoint(
                'https://example.test/v1/',
                'models/text-embedding-004'
            )
        );
        self::assertSame(
            'https://example.test/v1/models/model%20name:batchEmbedContents',
            $builder->getBatchEmbeddingEndpoint(
                'https://example.test/v1',
                'model name'
            )
        );
    }

    public function testBuildsDocumentRequests(): void
    {
        $builder = new RequestBuilder();

        self::assertSame(
            [
                'requests' => [
                    $this->documentRequest('models/model', 'Title: Product Text: Description', 'Product'),
                    $this->documentRequest('models/model', 'Title: none Text: Other'),
                    $this->documentRequest('models/model', 'Title:  Text: Empty title'),
                ],
            ],
            $builder->buildDocumentRequestBody(
                [
                    new EmbeddingInput('Product', 'Description'),
                    new EmbeddingInput(null, 'Other'),
                    new EmbeddingInput('', 'Empty title'),
                ],
                'model',
                3,
                'Title: {title} Text: {text}'
            )
        );
    }

    public function testBuildsQueryRequest(): void
    {
        $builder = new RequestBuilder();

        self::assertSame(
            [
                'requests' => [
                    [
                        'model' => 'models/model',
                        'content' => ['parts' => [['text' => 'Query: shoes']]],
                        'taskType' => 'RETRIEVAL_QUERY',
                        'outputDimensionality' => 3,
                    ],
                ],
            ],
            $builder->buildQueryRequestBody('shoes', 'models/model', 3, 'Query: {text}')
        );
    }

    public function testDecodesOrderedVectors(): void
    {
        $serializer = self::createStub(SerializerInterface::class);
        $serializer->method('unserialize')->willReturn([
            'embeddings' => [
                ['values' => [1, 2.5]],
                ['values' => [3, 4]],
            ],
        ]);
        $decoder = new ResponseDecoder(
            new HttpResponseDecoder($serializer),
            new ResponseValidator(),
            2,
            2
        );

        self::assertSame(
            [[1.0, 2.5], [3.0, 4.0]],
            $decoder->execute(new Response(200, [], '{}'))
        );
    }

    public function testRejectsMissingEmbeddings(): void
    {
        $serializer = self::createStub(SerializerInterface::class);
        $serializer->method('unserialize')->willReturn([]);
        $decoder = new ResponseDecoder(
            new HttpResponseDecoder($serializer),
            new ResponseValidator(),
            2,
            1
        );

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('unexpected item count');

        $decoder->execute(new Response(200, [], '{}'));
    }

    public function testRejectsInvalidEmbeddingItem(): void
    {
        $serializer = self::createStub(SerializerInterface::class);
        $serializer->method('unserialize')->willReturn(['embeddings' => [null]]);
        $decoder = new ResponseDecoder(
            new HttpResponseDecoder($serializer),
            new ResponseValidator(),
            2,
            1
        );

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('item must be an object');

        $decoder->execute(new Response(200, [], '{}'));
    }

    public function testReturnsResolvedPromiseForEmptyDocuments(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::never())->method('requestAsync');

        self::assertSame([], $this->createClient($client)->embedDocumentsAsync([])->wait());
    }

    public function testSendsDocumentEmbeddingRequest(): void
    {
        $client = $this->createHttpClient(60);

        self::assertSame(
            [[1.0, 2.0, 3.0]],
            $this->createClient($client)
                ->embedDocumentsAsync([new EmbeddingInput('Product', 'Description')])
                ->wait()
        );
    }

    public function testSendsQueryEmbeddingRequestWithSnapshotConfiguration(): void
    {
        $client = $this->createHttpClient(12, 'query-model');
        $snapshot = new QueryConfigurationSnapshot('query-model', 3, 'Find {text}');

        self::assertSame(
            [[1.0, 2.0, 3.0]],
            $this->createClient($client)->embedQueryAsync('shoes', 12, $snapshot)->wait()
        );
    }

    public function testRequiresApiKey(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::never())->method('requestAsync');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('An API key is required');

        $this->createClient($client, null)
            ->embedDocumentsAsync([new EmbeddingInput(null, 'Description')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function documentRequest(string $model, string $text, ?string $title = null): array
    {
        $request = [
            'model' => $model,
            'content' => ['parts' => [['text' => $text]]],
            'taskType' => 'RETRIEVAL_DOCUMENT',
            'outputDimensionality' => 3,
        ];

        if ($title !== null) {
            $request['title'] = $title;
        }

        return $request;
    }

    private function createHttpClient(int $timeout, string $model = 'model'): Client&MockObject
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::once())
            ->method('requestAsync')
            ->with(
                'POST',
                'https://example.test/v1/models/' . $model . ':batchEmbedContents',
                [
                    RequestOptions::BODY => 'payload',
                    RequestOptions::HEADERS => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'x-goog-api-key' => 'secret',
                    ],
                    RequestOptions::HTTP_ERRORS => false,
                    RequestOptions::TIMEOUT => $timeout,
                ]
            )
            ->willReturn(Create::promiseFor(new Response()));

        return $client;
    }

    private function createClient(Client $client, ?string $apiKey = 'secret'): GoogleGemini
    {
        $config = self::createStub(EmbedderConfig::class);
        $config->method('getEmbeddingEndpoint')->willReturn('https://example.test/v1');
        $config->method('getApiKey')->willReturn($apiKey);
        $config->method('getEmbeddingModel')->willReturn('model');
        $config->method('getVectorDimensions')->willReturn(3);
        $config->method('getRequestTimeoutSeconds')->willReturn(60);
        $config->method('getEmbedderDocumentTemplate')
            ->willReturn('Title: {title} Text: {text}');

        $serializer = self::createStub(SerializerInterface::class);
        $serializer->method('serialize')->willReturn('payload');
        $decoder = self::createStub(ResponseDecoder::class);
        $decoder->method('execute')->willReturn([[1.0, 2.0, 3.0]]);
        $factory = self::createStub(ResponseDecoderFactory::class);
        $factory->method('create')->willReturn($decoder);

        return new GoogleGemini(
            $client,
            new RequestBodySerializer($serializer),
            $config,
            new EndpointBuilder(),
            new RequestBuilder(),
            $factory
        );
    }
}
