<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Stress\Support;

use Magento\Cron\Model\ResourceModel\Schedule\CollectionFactory;
use Magento\Cron\Model\ResourceModel\Schedule as ScheduleResource;
use Magento\Cron\Model\Schedule;
use Magento\Cron\Observer\ProcessCronQueueObserver;
use Magento\Framework\App\CacheInterface;
use RuntimeException;

class CronSchedule
{
    public const string GROUP_ID = 'davidbel_ai_search';
    public const string JOB_CODE = 'davidbel_ai_search_chunk_processing';

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly CacheInterface $cache
    ) {
    }

    public function reset(): void
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('job_code', self::JOB_CODE);
        $collection->addFieldToFilter('status', Schedule::STATUS_RUNNING);

        if ($collection->getSize() > 0) {
            throw new RuntimeException('The AI Search chunk-processing cron job is already running.');
        }

        $resource = $this->getResource();
        /** @var \Magento\Framework\DB\Adapter\AdapterInterface $connection */
        $connection = $resource->getConnection();
        $connection->delete(
            $resource->getMainTable(),
            ['job_code = ?' => self::JOB_CODE]
        );
        $this->cache->remove(
            ProcessCronQueueObserver::CACHE_KEY_LAST_SCHEDULE_GENERATE_AT . self::GROUP_ID
        );
        $this->cache->remove(
            ProcessCronQueueObserver::CACHE_KEY_LAST_HISTORY_CLEANUP_AT . self::GROUP_ID
        );
    }

    public function hasRunningJob(): bool
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('job_code', self::JOB_CODE);
        $collection->addFieldToFilter('status', Schedule::STATUS_RUNNING);

        return $collection->getSize() > 0;
    }

    /**
     * @return list<array{
     *     schedule_id: int,
     *     status: string,
     *     messages: string|null,
     *     created_at: string,
     *     scheduled_at: string,
     *     executed_at: string|null,
     *     finished_at: string|null
     * }>
     */
    public function getRecords(): array
    {
        $resource = $this->getResource();
        /** @var \Magento\Framework\DB\Adapter\AdapterInterface $connection */
        $connection = $resource->getConnection();
        $select = $connection
            ->select()
            ->from(
                $resource->getMainTable(),
                [
                    'schedule_id',
                    'status',
                    'messages',
                    'created_at',
                    'scheduled_at',
                    'executed_at',
                    'finished_at',
                ]
            )
            ->where('job_code = ?', self::JOB_CODE)
            ->order('schedule_id ASC');
        /** @var list<array<string, mixed>> $rows */
        $rows = $connection->fetchAll($select);
        $records = [];

        foreach ($rows as $row) {
            $records[] = $this->normalizeRecord($row);
        }

        return $records;
    }

    private function getResource(): ScheduleResource
    {
        $resource = $this->collectionFactory->create()->getResource();

        if (!$resource instanceof ScheduleResource) {
            throw new RuntimeException('The Magento cron schedule resource is unavailable.');
        }

        return $resource;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{
     *     schedule_id: int,
     *     status: string,
     *     messages: string|null,
     *     created_at: string,
     *     scheduled_at: string,
     *     executed_at: string|null,
     *     finished_at: string|null
     * }
     */
    private function normalizeRecord(array $row): array
    {
        $scheduleId = filter_var($row['schedule_id'] ?? null, FILTER_VALIDATE_INT);
        $status = $row['status'] ?? null;
        $createdAt = $row['created_at'] ?? null;
        $scheduledAt = $row['scheduled_at'] ?? null;

        if (!is_int($scheduleId) || $scheduleId < 1
            || !is_string($status)
            || !is_string($createdAt)
            || !is_string($scheduledAt)
        ) {
            throw new RuntimeException('Magento returned an invalid AI Search cron schedule record.');
        }

        return [
            'schedule_id' => $scheduleId,
            'status' => $status,
            'messages' => $this->getNullableString($row['messages'] ?? null),
            'created_at' => $createdAt,
            'scheduled_at' => $scheduledAt,
            'executed_at' => $this->getNullableString($row['executed_at'] ?? null),
            'finished_at' => $this->getNullableString($row['finished_at'] ?? null),
        ];
    }

    private function getNullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw new RuntimeException('Magento returned an invalid cron schedule value.');
        }

        return $value;
    }
}
