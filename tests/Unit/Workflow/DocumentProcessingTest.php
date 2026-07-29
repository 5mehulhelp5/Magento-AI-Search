<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Workflow;

use DavidBel\AiSearch\Workflow\DocumentProcessing;
use DavidBel\AiSearch\Workflow\DocumentProcessing\DocumentUpdater;
use DavidBel\AiSearch\Workflow\DocumentProcessing\Product\ScopedSource;
use DavidBel\AiSearch\Workflow\DocumentProcessing\Product\SourceProvider;
use PHPUnit\Framework\TestCase;

class DocumentProcessingTest extends TestCase
{
    public function testPerformsAFullUpdateUsingKeysetBatches(): void
    {
        $sources = [new ScopedSource(2, 'Description')];
        $sourceProvider = $this->createMock(SourceProvider::class);
        $batch = 0;
        $sourceProvider->expects(self::exactly(2))
            ->method('getProductIdsAfter')
            ->willReturnCallback(
                static function (int $lastProductId, int $limit) use (&$batch): array {
                    self::assertSame(DocumentProcessing::BATCH_SIZE, $limit);

                    if ($batch === 0) {
                        self::assertSame(0, $lastProductId);
                        ++$batch;

                        return [30];
                    }

                    self::assertSame(30, $lastProductId);

                    return [];
                }
            );
        $sourceProvider->expects(self::once())
            ->method('getByProductIds')
            ->with([30])
            ->willReturn([30 => $sources]);
        $documentUpdater = $this->createMock(DocumentUpdater::class);
        $documentUpdater->expects(self::once())
            ->method('fullUpdate')
            ->with('product', 30, 'description', $sources);

        (new DocumentProcessing($sourceProvider, $documentUpdater))->fullUpdate();
    }

    public function testDeltaUpdatesSourcesAndMissingProducts(): void
    {
        $firstProductSources = [new ScopedSource(1, 'First description')];
        $sourceProvider = $this->createMock(SourceProvider::class);
        $sourceProvider->expects(self::once())
            ->method('getByProductIds')
            ->with([10, 20])
            ->willReturn([10 => $firstProductSources]);
        $documentUpdater = $this->createMock(DocumentUpdater::class);
        $updatedProducts = [];
        $documentUpdater->expects(self::exactly(2))
            ->method('deltaUpdate')
            ->willReturnCallback(
                static function (
                    string $sourceEntityType,
                    int $sourceEntityId,
                    string $sourceCode,
                    array $sources
                ) use (&$updatedProducts): void {
                    self::assertSame('product', $sourceEntityType);
                    self::assertSame('description', $sourceCode);
                    $updatedProducts[$sourceEntityId] = $sources;
                }
            );

        $documentProcessing = new DocumentProcessing($sourceProvider, $documentUpdater);
        $documentProcessing->deltaUpdate([10, 20]);

        self::assertSame($firstProductSources, $updatedProducts[10]);
        self::assertSame([], $updatedProducts[20]);
    }

    public function testDoesNotLoadAnEmptyDeltaUpdate(): void
    {
        $sourceProvider = $this->createMock(SourceProvider::class);
        $sourceProvider->expects(self::never())
            ->method('getByProductIds');
        $documentUpdater = self::createStub(DocumentUpdater::class);

        (new DocumentProcessing($sourceProvider, $documentUpdater))->deltaUpdate([]);
    }

    public function testLoadsDeltaUpdatesInBoundedBatches(): void
    {
        $productIds = range(1, DocumentProcessing::BATCH_SIZE + 1);
        $loadedBatches = [];
        $sourceProvider = $this->createMock(SourceProvider::class);
        $sourceProvider->expects(self::exactly(2))
            ->method('getByProductIds')
            ->willReturnCallback(
                static function (array $productIdBatch) use (&$loadedBatches): array {
                    $loadedBatches[] = $productIdBatch;

                    return [];
                }
            );
        $documentUpdater = self::createStub(DocumentUpdater::class);

        (new DocumentProcessing($sourceProvider, $documentUpdater))->deltaUpdate($productIds);

        self::assertSame(
            [
                range(1, DocumentProcessing::BATCH_SIZE),
                [DocumentProcessing::BATCH_SIZE + 1],
            ],
            $loadedBatches
        );
    }
}
