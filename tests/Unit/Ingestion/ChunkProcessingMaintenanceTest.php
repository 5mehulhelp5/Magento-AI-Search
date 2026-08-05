<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Ingestion;

use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog as EmbeddingBacklogResource;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Collection;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use DavidBel\AiSearch\Tests\Unit\TestDouble\GeneratedFactoryStub;
use DavidBel\AiSearch\Ingestion\ChunkProcessingCleanup;
use DavidBel\AiSearch\Ingestion\ChunkProcessingRetry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\TestCase;

class ChunkProcessingMaintenanceTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        GeneratedFactoryStub::register(CollectionFactory::class);
    }

    public function testRetriesFailedRowsBelowTheAttemptThreshold(): void
    {
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->expects(self::once())
            ->method('markFailedAsPending')
            ->with(3)
            ->willReturn(7);

        self::assertSame(
            7,
            (new ChunkProcessingRetry(
                $this->createCollectionFactory($resource)
            ))->execute()
        );
    }

    public function testCleansExhaustedAndExpiredRowsUsingUtcCutoff(): void
    {
        $dateTime = $this->createMock(DateTime::class);
        $dateTime->expects(self::once())
            ->method('gmtDate')
            ->with(null, '-24 hours')
            ->willReturn('2026-08-03 10:00:00');
        $resource = $this->createMock(EmbeddingBacklogResource::class);
        $resource->expects(self::once())
            ->method('deleteExhaustedUpsertsOrExpiredResults')
            ->with(3, '2026-08-03 10:00:00')
            ->willReturn(5);

        self::assertSame(
            5,
            (new ChunkProcessingCleanup(
                $this->createCollectionFactory($resource),
                $dateTime
            ))->execute()
        );
    }

    private function createCollectionFactory(
        EmbeddingBacklogResource $resource
    ): CollectionFactory {
        $collection = self::createStub(Collection::class);
        $collection->method('getResourceModel')->willReturn($resource);
        $factory = self::createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return $factory;
    }
}
