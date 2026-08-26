<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Ingestion;

use DavidBel\AiSearch\Config\SemanticDataProcessingConfig;
use DavidBel\AiSearch\Ingestion\ChunkProcessingCleanup;
use DavidBel\AiSearch\Ingestion\ChunkProcessingRetry;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\IndexVersion as BacklogIndexVersion;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Maintenance as BacklogMaintenance;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\TestCase;

class ChunkProcessingMaintenanceTest extends TestCase
{
    public function testRetriesFailedRowsBelowTheAttemptThreshold(): void
    {
        $maintenance = $this->createMock(BacklogMaintenance::class);
        $maintenance->expects(self::once())
            ->method('markFailedAsPending')
            ->with(7, 3)
            ->willReturn(7);

        self::assertSame(
            7,
            (new ChunkProcessingRetry(
                $maintenance,
                $this->createSemanticDataProcessingConfig()
            ))->execute(7)
        );
    }

    public function testCleansExhaustedAndExpiredRowsUsingUtcCutoff(): void
    {
        $dateTime = $this->createMock(DateTime::class);
        $dateTime->expects(self::once())
            ->method('gmtDate')
            ->with(null, '-24 hours')
            ->willReturn('2026-08-03 10:00:00');
        $backlogIndexVersion = $this->createMock(BacklogIndexVersion::class);
        $backlogIndexVersion->expects(self::once())
            ->method('deleteItemsOutsideIndexVersion')
            ->with(7)
            ->willReturn(2);
        $maintenance = $this->createMock(BacklogMaintenance::class);
        $maintenance->expects(self::once())
            ->method('deleteExhaustedUpsertsOrExpiredResults')
            ->with(3, '2026-08-03 10:00:00', 8)
            ->willReturn(5);

        self::assertSame(
            7,
            (new ChunkProcessingCleanup(
                $backlogIndexVersion,
                $maintenance,
                $dateTime,
                $this->createSemanticDataProcessingConfig()
            ))->execute(7, 8)
        );
    }

    private function createSemanticDataProcessingConfig(): SemanticDataProcessingConfig
    {
        $config = self::createStub(SemanticDataProcessingConfig::class);
        $config->method('getRetryAttemptThreshold')->willReturn(3);
        $config->method('getCleanupResultRetentionHours')->willReturn(24);

        return $config;
    }
}
