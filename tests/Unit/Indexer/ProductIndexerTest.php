<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Indexer;

use DavidBel\AiSearch\Indexer\ProductIndexer;
use DavidBel\AiSearch\Indexer\Versioning;
use DavidBel\AiSearch\Ingestion\DocumentProcessing;
use DavidBel\AiSearch\Log\Logger;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ProductIndexerTest extends TestCase
{
    public function testDelegatesAFullUpdate(): void
    {
        $documentProcessing = $this->createMock(DocumentProcessing::class);
        $documentProcessing->expects(self::once())
            ->method('fullUpdate');
        $versioning = $this->createMock(Versioning::class);
        $versioning->expects(self::once())
            ->method('prepareTargetForFullReindex');
        $versioning->expects(self::once())
            ->method('markTargetDocumentProcessingComplete');

        (new ProductIndexer(
            $documentProcessing,
            $versioning,
            self::createStub(Logger::class)
        ))->executeFull();
    }

    public function testNormalizesAndDelegatesADeltaUpdate(): void
    {
        $documentProcessing = $this->createMock(DocumentProcessing::class);
        $documentProcessing->expects(self::once())
            ->method('deltaUpdate')
            ->with([2, 1]);

        (new ProductIndexer(
            $documentProcessing,
            $this->createAvailableVersioning(),
            self::createStub(Logger::class)
        ))->execute([2, 1, 2]);
    }

    public function testDelegatesOneProductAsADeltaUpdate(): void
    {
        $documentProcessing = $this->createMock(DocumentProcessing::class);
        $documentProcessing->expects(self::once())
            ->method('deltaUpdate')
            ->with([7]);

        (new ProductIndexer(
            $documentProcessing,
            $this->createAvailableVersioning(),
            self::createStub(Logger::class)
        ))->executeRow(7);
    }

    public function testRejectsAnInvalidProductId(): void
    {
        $documentProcessing = self::createStub(DocumentProcessing::class);
        $indexer = new ProductIndexer(
            $documentProcessing,
            $this->createAvailableVersioning(),
            self::createStub(Logger::class)
        );

        $this->expectException(InvalidArgumentException::class);

        $indexer->executeList([0]);
    }

    public function testInvalidatesWhenNoIndexMatchesCurrentConfiguration(): void
    {
        $processing = $this->createMock(DocumentProcessing::class);
        $processing->expects(self::never())->method('deltaUpdate');
        $versioning = $this->createMock(Versioning::class);
        $versioning->method('hasTargetOrActiveForCurrentConfiguration')->willReturn(false);
        $versioning->expects(self::once())->method('invalidateProductIndexerWhenNeeded');

        (new ProductIndexer($processing, $versioning, self::createStub(Logger::class)))
            ->executeList([1]);
    }

    public function testLogsAndRethrowsFullIndexFailure(): void
    {
        $failure = new RuntimeException('full update failed');
        $versioning = self::createStub(Versioning::class);
        $versioning->method('prepareTargetForFullReindex')->willThrowException($failure);
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())
            ->method('indexerFailed')
            ->with(ProductIndexer::ID, 'full', $failure);
        $logger->expects(self::never())->method('indexerCompleted');

        $this->expectExceptionObject($failure);
        (new ProductIndexer(self::createStub(DocumentProcessing::class), $versioning, $logger))
            ->executeFull();
    }

    private function createAvailableVersioning(): Versioning
    {
        $versioning = self::createStub(Versioning::class);
        $versioning->method('hasTargetOrActiveForCurrentConfiguration')
            ->willReturn(true);

        return $versioning;
    }
}
