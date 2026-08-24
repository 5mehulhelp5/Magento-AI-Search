<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Config;

use DavidBel\AiSearch\Config\EmbedderConfig;
use DavidBel\AiSearch\Config\IndexingScopeConfig;
use DavidBel\AiSearch\Config\SearchConfig;
use DavidBel\AiSearch\Config\SemanticDataProcessingConfig;
use DavidBel\AiSearch\Config\SemanticSearchResultConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\Stub;
use UnexpectedValueException;

class ConfigTest extends TestCase
{
    public function testReadsSemanticDataProcessingValues(): void
    {
        $config = new SemanticDataProcessingConfig($this->scopeConfigWithValue('5'));

        self::assertSame(5, $config->getDocumentProcessingBatchSize());
        self::assertSame(5, $config->getVectorEmbeddingBatchSize());
        self::assertSame(5, $config->getVectorEmbeddingConcurrentRequests());
        self::assertSame(5, $config->getVectorEmbeddingMaximumRuntimeSeconds());
        self::assertSame(5, $config->getVectorDeleteBatchSize());
        self::assertSame(5, $config->getVectorDeleteUpsertAttemptThreshold());
        self::assertSame(5, $config->getVectorDeleteMaximumRuntimeSeconds());
        self::assertSame(5, $config->getRetryAttemptThreshold());
        self::assertSame(5, $config->getCleanupAttemptThreshold());
        self::assertSame(5, $config->getCleanupResultRetentionHours());
        self::assertSame(5, $config->getIndexerLockTimeoutSeconds());
        self::assertSame(5, $config->getIndexerMinimumSuccessPercentage());
    }

    public function testRejectsInvalidSemanticDataProcessingValues(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('must contain a positive integer');

        (new SemanticDataProcessingConfig($this->scopeConfigWithValue('0')))
            ->getDocumentProcessingBatchSize();
    }

    public function testRejectsAnIndexerPercentageAboveOneHundred(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('must not exceed 100');

        (new SemanticDataProcessingConfig($this->scopeConfigWithValue('101')))
            ->getIndexerMinimumSuccessPercentage();
    }

    public function testReadsEmbedderValuesAndDecryptsTheApiKey(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects(self::once())->method('decrypt')->with('5')->willReturn('secret');
        $config = new EmbedderConfig($this->scopeConfigWithValue('5'), $encryptor);

        self::assertSame('5', $config->getEmbeddingApiProtocol());
        self::assertSame('5', $config->getEmbeddingEndpoint());
        self::assertSame('secret', $config->getApiKey());
        self::assertSame('5', $config->getEmbeddingModel());
        self::assertSame(5, $config->getVectorDimensions());
        self::assertSame(5, $config->getRequestTimeoutSeconds());
        self::assertSame('5', $config->getEmbedderDocumentTemplate());
        self::assertSame(5, $config->getMaximumChunkTokens());
        self::assertSame(5, $config->getChunkOverlapTokens());
        self::assertSame(5, $config->getEstimatedCharactersPerToken());
    }

    public function testReturnsNullForAnUnconfiguredApiKey(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects(self::never())->method('decrypt');

        self::assertNull(
            (new EmbedderConfig($this->scopeConfigWithValue(null), $encryptor))->getApiKey()
        );
    }

    public function testFallsBackToTheStoredApiKeyWhenDecryptionIsEmpty(): void
    {
        $encryptor = self::createStub(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturn('');

        self::assertSame(
            'stored',
            (new EmbedderConfig($this->scopeConfigWithValue('stored'), $encryptor))->getApiKey()
        );
    }

    public function testRejectsANonStringEmbedderValue(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('must contain a string');

        $this->createEmbedderConfig([])->getEmbeddingEndpoint();
    }

    public function testRejectsANonStringOptionalApiKey(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('must contain a string');

        $this->createEmbedderConfig([])->getApiKey();
    }

    public function testRejectsAnInvalidEmbedderInteger(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('must contain an integer');

        $this->createEmbedderConfig('invalid')->getVectorDimensions();
    }

    public function testRejectsANonPositiveEmbedderInteger(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('must contain a positive integer');

        $this->createEmbedderConfig('0')->getVectorDimensions();
    }

    public function testRejectsANegativeChunkOverlap(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('must contain a non-negative integer');

        $this->createEmbedderConfig('-1')->getChunkOverlapTokens();
    }

    public function testReadsAndNormalizesTheIndexingScope(): void
    {
        $config = new IndexingScopeConfig($this->scopeConfigWithValue('3, 1, 3, 2'));

        self::assertSame([1, 2, 3], $config->getStoreIdsForIndexing());
    }

    public function testReturnsAnEmptyIndexingScope(): void
    {
        self::assertSame(
            [],
            (new IndexingScopeConfig($this->scopeConfigWithValue(null)))
                ->getStoreIdsForIndexing()
        );
    }

    public function testRejectsANonStringIndexingScope(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('must contain a comma-separated list');

        (new IndexingScopeConfig($this->scopeConfigWithValue([])))
            ->getStoreIdsForIndexing();
    }

    public function testRejectsAnInvalidStoreId(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('contains an invalid store ID');

        (new IndexingScopeConfig($this->scopeConfigWithValue('1, 0')))
            ->getStoreIdsForIndexing();
    }

    public function testReadsSearchConfiguration(): void
    {
        $config = new SearchConfig($this->scopeConfigWithValue('configured'));

        self::assertSame('davidbel_ai_search_chunks', $config->getIndexName());
        self::assertSame(1, $config->getIndexSchemaVersion());
        self::assertSame('hnsw', $config->getVectorMethod());
        self::assertSame('configured', $config->getVectorEngine());
        self::assertSame('configured', $config->getVectorSpace());
    }

    public function testRejectsANonStringSearchConfiguration(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('must contain a string');

        (new SearchConfig($this->scopeConfigWithValue(null)))->getVectorEngine();
    }

    public function testReadsStoreScopedSemanticSearchResultConfiguration(): void
    {
        $scopeConfig = $this->scopeConfigWithValue('5');
        $scopeConfig->method('isSetFlag')->willReturn(true);
        $config = new SemanticSearchResultConfig($scopeConfig);

        self::assertTrue($config->isEnabled(2));
        self::assertSame(5, $config->getRequestTimeoutSeconds(2));
        self::assertTrue($config->usePreviousSemanticIndexDuringRebuild(2));
        self::assertTrue($config->shouldCollapseResultsByProduct(2));
        self::assertSame(5, $config->getProductResultLimit(2));
        self::assertSame(5, $config->getChunkCandidateLimit(2));
        self::assertSame(5.0, $config->getMinimumScore(2));
        self::assertSame('5', $config->getEmbedderQueryTemplate());
        self::assertSame('5', $config->getEmbedderQueryTemplate(2));
    }

    public function testRejectsAnInvalidSemanticSearchResultLimit(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('must contain a positive integer');

        (new SemanticSearchResultConfig($this->scopeConfigWithValue('0')))
            ->getProductResultLimit(2);
    }

    public function testRejectsANegativeMinimumScore(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('must contain a non-negative number');

        (new SemanticSearchResultConfig($this->scopeConfigWithValue('-0.1')))
            ->getMinimumScore(2);
    }

    public function testRejectsANonStringQueryTemplate(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('must contain a string');

        (new SemanticSearchResultConfig($this->scopeConfigWithValue(null)))
            ->getEmbedderQueryTemplate();
    }

    private function createEmbedderConfig(mixed $value): EmbedderConfig
    {
        return new EmbedderConfig(
            $this->scopeConfigWithValue($value),
            self::createStub(EncryptorInterface::class)
        );
    }

    private function scopeConfigWithValue(mixed $value): ScopeConfigInterface&Stub
    {
        $scopeConfig = self::createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn($value);

        return $scopeConfig;
    }
}
