<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Indexer;

use DavidBel\AiSearch\Indexer\ProductIndexer;
use DavidBel\AiSearch\Workflow\DocumentProcessing;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ProductIndexerTest extends TestCase
{
    public function testDelegatesAFullUpdate(): void
    {
        $documentProcessing = $this->createMock(DocumentProcessing::class);
        $documentProcessing->expects(self::once())
            ->method('fullUpdate');

        (new ProductIndexer($documentProcessing))->executeFull();
    }

    public function testNormalizesAndDelegatesADeltaUpdate(): void
    {
        $documentProcessing = $this->createMock(DocumentProcessing::class);
        $documentProcessing->expects(self::once())
            ->method('deltaUpdate')
            ->with([2, 1]);

        (new ProductIndexer($documentProcessing))->execute([2, 1, 2]);
    }

    public function testDelegatesOneProductAsADeltaUpdate(): void
    {
        $documentProcessing = $this->createMock(DocumentProcessing::class);
        $documentProcessing->expects(self::once())
            ->method('deltaUpdate')
            ->with([7]);

        (new ProductIndexer($documentProcessing))->executeRow(7);
    }

    public function testRejectsAnInvalidProductId(): void
    {
        $documentProcessing = self::createStub(DocumentProcessing::class);
        $indexer = new ProductIndexer($documentProcessing);

        $this->expectException(InvalidArgumentException::class);

        $indexer->executeList([0]);
    }
}
