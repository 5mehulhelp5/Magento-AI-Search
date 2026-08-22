<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Controller;

use DavidBel\AiSearch\Client\Embedding\Base\EmbedderClientInterface;
use DavidBel\AiSearch\Client\Embedding\Base\EmbedderClientPool;
use DavidBel\AiSearch\Config\EmbedderConfig;
use DavidBel\AiSearch\Controller\Adminhtml\AiServer\TestEmbedderConnection;
use DavidBel\AiSearch\Controller\Adminhtml\Search\TestSemanticSearch;
use DavidBel\AiSearch\Controller\Adminhtml\Search\TestSemanticSearch\ResultProvider;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use GuzzleHttp\Promise\Create;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AdminActionTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(JsonFactory::class);
    }

    public function testSemanticSearchActionReturnsResults(): void
    {
        $provider = $this->createMock(ResultProvider::class);
        $provider->expects(self::once())
            ->method('getSearchResults')
            ->with('shoes', 2)
            ->willReturn(['products' => []]);
        $json = $this->createMock(Json::class);
        $json->expects(self::once())
            ->method('setData')
            ->with(['success' => true, 'result' => ['products' => []]])
            ->willReturnSelf();
        $action = new TestSemanticSearch(
            $this->context(['q' => ' shoes ', 'store_id' => '2']),
            $this->jsonFactory($json),
            $provider
        );

        self::assertSame($json, $action->execute());
    }

    public function testSemanticSearchActionReturnsValidationFailure(): void
    {
        $provider = $this->createMock(ResultProvider::class);
        $provider->expects(self::never())->method('getSearchResults');
        $json = $this->createMock(Json::class);
        $json->expects(self::once())
            ->method('setData')
            ->with([
                'success' => false,
                'message' => 'The semantic search test failed.',
                'error_message' => 'Enter a search query.',
            ])
            ->willReturnSelf();
        $action = new TestSemanticSearch(
            $this->context(['q' => '  ', 'store_id' => 1]),
            $this->jsonFactory($json),
            $provider
        );

        self::assertSame($json, $action->execute());
    }

    public function testSemanticSearchActionReturnsProviderFailure(): void
    {
        $provider = self::createStub(ResultProvider::class);
        $provider->method('getSearchResults')
            ->willThrowException(new RuntimeException('search failed'));
        $json = $this->createMock(Json::class);
        $json->expects(self::once())
            ->method('setData')
            ->with([
                'success' => false,
                'message' => 'The semantic search test failed.',
                'error_message' => 'search failed',
            ])
            ->willReturnSelf();
        $action = new TestSemanticSearch(
            $this->context(['q' => 'shoes', 'store_id' => 1]),
            $this->jsonFactory($json),
            $provider
        );

        self::assertSame($json, $action->execute());
    }

    public function testEmbedderConnectionActionReturnsSuccess(): void
    {
        $config = $this->embedderConfig();
        $client = self::createStub(EmbedderClientInterface::class);
        $client->method('embedDocumentsAsync')
            ->willReturn(Create::promiseFor([[1.0, 2.0, 3.0], [4.0, 5.0, 6.0]]));
        $pool = self::createStub(EmbedderClientPool::class);
        $pool->method('getClient')->willReturn($client);
        $json = $this->createMock(Json::class);
        $json->expects(self::once())
            ->method('setData')
            ->with([
                'success' => true,
                'message' => 'Connection successful. The configured AI server returned '
                    . '2 embeddings with 3 dimensions.',
                'configuration' => $this->expectedEmbedderConfiguration(),
                'error_message' => null,
            ])
            ->willReturnSelf();

        self::assertSame(
            $json,
            (new TestEmbedderConnection(
                $this->context([]),
                $this->jsonFactory($json),
                $pool,
                $config
            ))->execute()
        );
    }

    #[DataProvider('invalidEmbeddingResponses')]
    public function testEmbedderConnectionActionRejectsInvalidResponse(
        mixed $vectors,
        string $message
    ): void {
        $client = self::createStub(EmbedderClientInterface::class);
        $client->method('embedDocumentsAsync')->willReturn(Create::promiseFor($vectors));
        $pool = self::createStub(EmbedderClientPool::class);
        $pool->method('getClient')->willReturn($client);
        $json = $this->createMock(Json::class);
        $json->expects(self::once())
            ->method('setData')
            ->with([
                'success' => false,
                'message' => 'The connection test failed.',
                'configuration' => $this->expectedEmbedderConfiguration(),
                'error_message' => $message,
            ])
            ->willReturnSelf();

        (new TestEmbedderConnection(
            $this->context([]),
            $this->jsonFactory($json),
            $pool,
            $this->embedderConfig()
        ))->execute();
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidEmbeddingResponses(): array
    {
        return [
            'not array' => ['invalid', 'The AI server returned an unexpected embedding count.'],
            'not list' => [
                ['first' => [1], 'second' => [2]],
                'The AI server returned an unexpected embedding count.',
            ],
            'wrong count' => [[[1]], 'The AI server returned an unexpected embedding count.'],
            'vector not array' => [
                [null, [1]],
                'The AI server returned an invalid embedding vector.',
            ],
            'vector not list' => [
                [['x' => 1], [1]],
                'The AI server returned an invalid embedding vector.',
            ],
            'empty vector' => [[[], []], 'The AI server returned an invalid embedding vector.'],
        ];
    }

    public function testEmbedderConnectionActionHandlesConfigurationFailure(): void
    {
        $config = self::createStub(EmbedderConfig::class);
        $config->method('getEmbeddingEndpoint')
            ->willThrowException(new RuntimeException('configuration failed'));
        $json = $this->createMock(Json::class);
        $json->expects(self::once())
            ->method('setData')
            ->with([
                'success' => false,
                'message' => 'The connection test failed.',
                'configuration' => null,
                'error_message' => 'configuration failed',
            ])
            ->willReturnSelf();

        (new TestEmbedderConnection(
            $this->context([]),
            $this->jsonFactory($json),
            self::createStub(EmbedderClientPool::class),
            $config
        ))->execute();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function context(array $parameters): Context
    {
        $request = self::createStub(RequestInterface::class);
        $map = [];

        foreach ($parameters as $key => $value) {
            $map[] = [$key, null, $value];
        }

        $request->method('getParam')->willReturnMap($map);
        $context = self::createStub(Context::class);
        $context->method('getRequest')->willReturn($request);

        return $context;
    }

    private function jsonFactory(Json $json): JsonFactory
    {
        $factory = self::createStub(JsonFactory::class);
        $factory->method('create')->willReturn($json);

        return $factory;
    }

    private function embedderConfig(): EmbedderConfig
    {
        $config = self::createStub(EmbedderConfig::class);
        $config->method('getEmbeddingEndpoint')->willReturn('https://example.test');
        $config->method('getEmbeddingApiProtocol')->willReturn('openai');
        $config->method('getApiKey')->willReturn('secret');
        $config->method('getEmbeddingModel')->willReturn('model');

        return $config;
    }

    /**
     * @return array{url: string, protocol: string, api_key_configured: bool, model: string}
     */
    private function expectedEmbedderConfiguration(): array
    {
        return [
            'url' => 'https://example.test',
            'protocol' => 'openai',
            'api_key_configured' => true,
            'model' => 'model',
        ];
    }
}
