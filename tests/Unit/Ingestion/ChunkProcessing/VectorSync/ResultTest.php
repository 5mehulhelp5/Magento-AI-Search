<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Ingestion\ChunkProcessing\VectorSync;

use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Item;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Result;
use PHPUnit\Framework\TestCase;

class ResultTest extends TestCase
{
    public function testExposesVersionedOutcomesAndDeduplicatedSourceEntities(): void
    {
        $result = new Result(
            [
                $this->createItem(10, 2, 'product', 90),
                $this->createItem(20, 3, 'product', 90),
                $this->createItem(30, 1, 'category', 8),
            ],
            [$this->createItem(40, 4, 'product', 91)]
        );

        self::assertSame([10 => 2, 20 => 3, 30 => 1], $result->getSuccessfulBacklogVersions());
        self::assertSame([40 => 4], $result->getFailedBacklogVersions());
        self::assertSame(
            ['product' => [90], 'category' => [8]],
            $result->getSuccessfulSourceEntities()
        );
        self::assertSame(3, $result->getSuccessfulCount());
    }

    private function createItem(
        int $backlogId,
        int $version,
        string $entityType,
        int $entityId
    ): Item {
        return new Item(
            $backlogId,
            $version,
            '2026-08-04 10:00:00',
            $backlogId + 100,
            $entityType,
            $entityId
        );
    }
}
