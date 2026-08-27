<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Ingestion\ChunkProcessing;

use DavidBel\AiSearch\Client\OpenSearch;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingBatch;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingItem;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Delete;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Delete\Batch as DeleteBatch;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Delete\RequestBuilder;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Delete\ResponseMapper;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Item;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Result;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Upsert;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class VectorSyncTest extends TestCase
{
    public function testDeleteBuildsBulkRequestAndMapsResponse(): void
    {
        $item = new Item(1, 2, '2026-08-22 10:00:00', 42, 'product', 10, 3);
        $batch = new DeleteBatch([$item]);
        $response = ['errors' => false, 'items' => []];
        $result = self::createStub(Result::class);
        $openSearch = $this->createMock(OpenSearch::class);
        $openSearch->expects(self::once())
            ->method('bulkQuery')
            ->with(3, [['delete' => ['_id' => '42']]])
            ->willReturn($response);
        $mapper = $this->createMock(ResponseMapper::class);
        $mapper->expects(self::once())->method('map')->with($response, [$item])->willReturn($result);

        self::assertSame(
            $result,
            (new Delete($openSearch, new RequestBuilder(), $mapper))->execute($batch)
        );
    }

    public function testFacadeDelegatesUpsertAndDelete(): void
    {
        $processingBatch = new ProcessingBatch([$this->processingItem()]);
        $deleteBatch = new DeleteBatch([
            new Item(1, 2, '2026-08-22 10:00:00', 42, 'product', 10, 3),
        ]);
        $upsertResult = self::createStub(Result::class);
        $deleteResult = self::createStub(Result::class);
        $upsert = $this->createMock(Upsert::class);
        $upsert->expects(self::once())
            ->method('execute')
            ->with($processingBatch, [[0.1, 0.2]])
            ->willReturn($upsertResult);
        $delete = $this->createMock(Delete::class);
        $delete->expects(self::once())->method('execute')->with($deleteBatch)->willReturn($deleteResult);
        $sync = new VectorSync($upsert, $delete);

        self::assertSame($upsertResult, $sync->upsert($processingBatch, [[0.1, 0.2]]));
        self::assertSame($deleteResult, $sync->delete($deleteBatch));
    }

    public function testDeleteBatchExposesVersionedItemsAndUniqueSourceIds(): void
    {
        $first = new Item(1, 2, 'updated', 41, 'product', 10, 3);
        $second = new Item(2, 4, 'updated', 42, 'product', 10, 3);
        $batch = new DeleteBatch([$first, $second]);

        self::assertSame([1 => 2, 2 => 4], $batch->getBacklogVersions());
        self::assertSame($second, $batch->getLastItem());
        self::assertSame(3, $batch->getIndexVersion());
        self::assertSame([10], $batch->getSourceEntityIds());
        self::assertSame([$first, $second], $batch->getItems());
    }

    public function testDeleteBatchRejectsEmptyAndMixedIndexVersions(): void
    {
        try {
            new DeleteBatch([]);
            self::fail('An empty delete batch must be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('at least one item', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('one OpenSearch index version');
        new DeleteBatch([
            new Item(1, 2, 'updated', 41, 'product', 10, 3),
            new Item(2, 4, 'updated', 42, 'product', 20, 4),
        ]);
    }

    private function processingItem(): ProcessingItem
    {
        return new ProcessingItem(
            1,
            2,
            '2026-08-22 10:00:00',
            42,
            'product',
            10,
            3,
            'catalog_product_10',
            0,
            'text',
            'hash'
        );
    }
}
