<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Block;

use DavidBel\AiSearch\Block\Adminhtml\Dashboard\Indexing;
use DavidBel\AiSearch\Block\Adminhtml\Dashboard\Progress;
use DavidBel\AiSearch\Indexer\Versioning;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex\QueryConfigurationSnapshot;
use DavidBel\AiSearch\Model\EmbeddingBacklog\FullReindexStatus;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Operation;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Status;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Progress as BacklogProgress;
use DavidBel\AiSearch\Model\ResourceModel\ProductIndexer\Progress as IndexerProgress;
use DavidBel\AiSearch\Tests\Unit\TestDouble\ObjectManagerStub;
use Magento\Backend\Block\Template\Context;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use PHPUnit\Framework\TestCase;

class DashboardTest extends TestCase
{
    protected function setUp(): void
    {
        ObjectManager::setInstance(new ObjectManagerStub([
            JsonHelper::class => self::createStub(JsonHelper::class),
            DirectoryHelper::class => self::createStub(DirectoryHelper::class),
        ]));
    }

    public function testProgressGroupsCountsAndAssignsIndexRoles(): void
    {
        $backlogProgress = self::createStub(BacklogProgress::class);
        $backlogProgress->method('getItemCounts')->willReturn([
            $this->itemCount(3, Operation::Upsert->value, FullReindexStatus::Delta->value, 'pending', 2),
            $this->itemCount(3, Operation::Upsert->value, FullReindexStatus::Delta->value, 'pending', 3),
            $this->itemCount(3, Operation::Delete->value, FullReindexStatus::Pending->value, 'done', 4),
            $this->itemCount(2, Operation::Upsert->value, FullReindexStatus::Indexed->value, 'failed', 1),
            $this->itemCount(1, Operation::Delete->value, FullReindexStatus::Delta->value, 'outdated', 2),
        ]);
        $versioning = self::createStub(Versioning::class);
        $versioning->method('hasIngestionIndexVersion')->willReturn(true);
        $versioning->method('getIngestionIndexVersion')->willReturn(3);
        $versioning->method('getSearchIndex')->with(true)->willReturn($this->physicalIndex(2));
        $block = new Progress(self::createStub(Context::class), $backlogProgress, $versioning);

        $groups = $block->getIndexVersionGroups();

        self::assertSame([3, 2, 1], array_column($groups, 'index_version'));
        self::assertSame(['Active for ingestion'], $groups[0]['roles']);
        self::assertSame(['Active for search'], $groups[1]['roles']);
        self::assertSame(['Inactive, will be deleted'], $groups[2]['roles']);
        self::assertSame(5, $groups[0]['bars'][0]['total']);
        self::assertSame(5, $groups[0]['bars'][0]['segments'][0]['count']);
        self::assertEquals(100.0, $groups[0]['bars'][0]['segments'][0]['percentage']);
        self::assertSame(0, $groups[0]['bars'][1]['total']);
        self::assertSame(0.0, $groups[0]['bars'][1]['segments'][0]['percentage']);
        self::assertSame(4, $groups[0]['bars'][3]['total']);
    }

    public function testProgressHandlesMissingIndexVersionsAndEmptyCounts(): void
    {
        $backlogProgress = self::createStub(BacklogProgress::class);
        $backlogProgress->method('getItemCounts')->willReturn([]);
        $versioning = self::createStub(Versioning::class);
        $versioning->method('hasIngestionIndexVersion')->willReturn(false);
        $versioning->method('getSearchIndex')->with(true)->willReturn(null);
        $block = new Progress(self::createStub(Context::class), $backlogProgress, $versioning);

        self::assertSame([], $block->getIndexVersionGroups());
    }

    public function testProgressProvidesAllLabelsAndLegendItems(): void
    {
        $block = new Progress(
            self::createStub(Context::class),
            self::createStub(BacklogProgress::class),
            self::createStub(Versioning::class)
        );

        self::assertSame('Upsert', $block->getOperationLabel(Operation::Upsert->value));
        self::assertSame('Delete', $block->getOperationLabel(Operation::Delete->value));
        self::assertSame('custom', $block->getOperationLabel('custom'));
        self::assertSame('Delta', $block->getModeLabel('delta'));
        self::assertSame('Full Reindex', $block->getModeLabel('full'));
        self::assertSame('custom', $block->getModeLabel('custom'));
        self::assertSame('Pending', $block->getStatusLabel(Status::Pending->value));
        self::assertSame('Failed', $block->getStatusLabel(Status::Failed->value));
        self::assertSame('Outdated', $block->getStatusLabel(Status::Outdated->value));
        self::assertSame('Done', $block->getStatusLabel(Status::Done->value));
        self::assertSame('custom', $block->getStatusLabel('custom'));
        self::assertSame(
            [
                ['status' => 'pending', 'label' => 'Pending'],
                ['status' => 'failed', 'label' => 'Failed'],
                ['status' => 'outdated', 'label' => 'Outdated'],
                ['status' => 'done', 'label' => 'Done'],
            ],
            $block->getLegendItems()
        );
    }

    public function testIndexingDelegatesQueuedProductCount(): void
    {
        $progress = self::createStub(IndexerProgress::class);
        $progress->method('getQueuedProductCount')->willReturn(12);
        $block = new Indexing(self::createStub(Context::class), $progress);

        self::assertSame(12, $block->getQueuedProductCount());
    }

    /**
     * @return array{operation: string, index_version: int, full_reindex_status: int, status: string, item_count: int}
     */
    private function itemCount(
        int $version,
        string $operation,
        int $fullReindexStatus,
        string $status,
        int $count
    ): array {
        return [
            'operation' => $operation,
            'index_version' => $version,
            'full_reindex_status' => $fullReindexStatus,
            'status' => $status,
            'item_count' => $count,
        ];
    }

    private function physicalIndex(int $number): PhysicalIndex
    {
        return new PhysicalIndex(
            $number,
            'index_' . $number,
            'fingerprint',
            new QueryConfigurationSnapshot('model', 3, '{text}')
        );
    }
}
