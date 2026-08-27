<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Block\Adminhtml\Dashboard;

use DavidBel\AiSearch\Indexer\Versioning;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Operation;
use DavidBel\AiSearch\Model\EmbeddingBacklog\FullReindexStatus;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Status;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\Progress as BacklogProgress;
use Magento\Backend\Block\Template;

class Progress extends Template
{
    private const array STATUSES = [
        Status::Pending->value,
        Status::Failed->value,
        Status::Outdated->value,
        Status::Done->value,
    ];

    private const array OPERATIONS = [Operation::Upsert->value, Operation::Delete->value];

    private const array MODES = ['delta', 'full'];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Template\Context $context,
        private readonly BacklogProgress $backlogProgress,
        private readonly Versioning $versioning,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return list<array{
     *     index_version: int,
     *     roles: list<string>,
     *     bars: list<array{
     *         operation: string,
     *         mode: string,
     *         total: int,
     *         segments: list<array{status: string, count: int, percentage: float}>
     *     }>
     * }>
     */
    public function getIndexVersionGroups(): array
    {
        $counts = [];

        foreach ($this->backlogProgress->getItemCounts() as $itemCount) {
            $mode = $itemCount['full_reindex_status'] === FullReindexStatus::Delta->value
                ? 'delta'
                : 'full';
            $currentCount = $counts[$itemCount['index_version']][$itemCount['operation']][$mode]
                [$itemCount['status']] ?? 0;
            $counts[$itemCount['index_version']][$itemCount['operation']][$mode]
                [$itemCount['status']] = $currentCount + $itemCount['item_count'];
        }

        $indexVersions = array_keys($counts);
        rsort($indexVersions, SORT_NUMERIC);
        $indexVersionGroups = [];
        $ingestionIndexVersion = $this->versioning->hasIngestionIndexVersion()
            ? $this->versioning->getIngestionIndexVersion()
            : null;
        $searchIndexVersion = $this->versioning->getSearchIndex(true)?->number;

        foreach ($indexVersions as $indexVersion) {
            $indexVersionGroups[] = [
                'index_version' => $indexVersion,
                'roles' => $this->getIndexVersionRoles(
                    $indexVersion,
                    $ingestionIndexVersion,
                    $searchIndexVersion
                ),
                'bars' => $this->createBars($counts[$indexVersion]),
            ];
        }

        return $indexVersionGroups;
    }

    /**
     * @return list<string>
     */
    private function getIndexVersionRoles(
        int $indexVersion,
        ?int $ingestionIndexVersion,
        ?int $searchIndexVersion
    ): array {
        $roles = [];

        if ($indexVersion === $ingestionIndexVersion) {
            $roles[] = (string) __('Active for ingestion');
        }

        if ($indexVersion === $searchIndexVersion) {
            $roles[] = (string) __('Active for search');
        }

        if ($roles === []) {
            $roles[] = (string) __('Inactive, will be deleted');
        }

        return $roles;
    }

    public function getOperationLabel(string $operation): string
    {
        return match ($operation) {
            Operation::Upsert->value => (string) __('Upsert'),
            Operation::Delete->value => (string) __('Delete'),
            default => $operation,
        };
    }

    public function getModeLabel(string $mode): string
    {
        return match ($mode) {
            'delta' => (string) __('Delta'),
            'full' => (string) __('Full Reindex'),
            default => $mode,
        };
    }

    /**
     * @return list<array{status: string, label: string}>
     */
    public function getLegendItems(): array
    {
        $legendItems = [];

        foreach (self::STATUSES as $status) {
            $legendItems[] = [
                'status' => $status,
                'label' => $this->getStatusLabel($status),
            ];
        }

        return $legendItems;
    }

    public function getStatusLabel(string $status): string
    {
        return match ($status) {
            Status::Pending->value => (string) __('Pending'),
            Status::Failed->value => (string) __('Failed'),
            Status::Outdated->value => (string) __('Outdated'),
            Status::Done->value => (string) __('Done'),
            default => $status,
        };
    }

    /**
     * @param array<string, array<string, array<string, int>>> $indexVersionCounts
     * @return list<array{
     *     operation: string,
     *     mode: string,
     *     total: int,
     *     segments: list<array{status: string, count: int, percentage: float}>
     * }>
     */
    private function createBars(array $indexVersionCounts): array
    {
        $bars = [];

        foreach (self::OPERATIONS as $operation) {
            foreach (self::MODES as $mode) {
                $statusCounts = array_fill_keys(self::STATUSES, 0);

                foreach ($indexVersionCounts[$operation][$mode] ?? [] as $status => $count) {
                    $statusCounts[$status] = $count;
                }

                $bars[] = $this->createBar($operation, $mode, $statusCounts);
            }
        }

        return $bars;
    }

    /**
     * @param array<string, int> $statusCounts
     * @return array{
     *     operation: string,
     *     mode: string,
     *     total: int,
     *     segments: list<array{status: string, count: int, percentage: float}>
     * }
     */
    private function createBar(string $operation, string $mode, array $statusCounts): array
    {
        $total = array_sum($statusCounts);
        $segments = [];

        foreach (self::STATUSES as $status) {
            $count = $statusCounts[$status];
            $segments[] = [
                'status' => $status,
                'count' => $count,
                'percentage' => $total > 0 ? ($count / $total) * 100 : 0.0,
            ];
        }

        return [
            'operation' => $operation,
            'mode' => $mode,
            'total' => $total,
            'segments' => $segments,
        ];
    }
}
