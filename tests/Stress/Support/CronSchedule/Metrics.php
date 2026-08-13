<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Stress\Support\CronSchedule;

use DateTimeImmutable;
use DateTimeZone;
use Magento\Cron\Model\Schedule;
use RuntimeException;

class Metrics
{
    /**
     * @param list<array{
     *     schedule_id: int,
     *     status: string,
     *     messages: string|null,
     *     created_at: string,
     *     scheduled_at: string,
     *     executed_at: string|null,
     *     finished_at: string|null
     * }> $scheduleRecords
     * @param list<array{
     *     sequence: int,
     *     scheduled_launch_at: float,
     *     launched_at: float,
     *     finished_at: float,
     *     launch_delay_seconds: float,
     *     duration_seconds: float,
     *     exit_code: int,
     *     output: string
     * }> $invocations
     * @return array<string, float|int>
     */
    public function calculate(
        array $scheduleRecords,
        array $invocations,
        float $observedFrom,
        float $observedUntil,
        int $intervalSeconds
    ): array {
        $windows = $this->getExecutionWindows($scheduleRecords);
        usort($windows, [$this, 'compareWindows']);
        $busySeconds = $this->getBusySeconds($windows);
        $observedSeconds = max(0.0, $observedUntil - $observedFrom);
        $idleGaps = $this->getIdleGaps($windows);
        $startedExecutions = count($windows);
        $invocationCount = count($invocations);

        return array_merge([
            'scheduler_interval_seconds' => $intervalSeconds,
            'observed_seconds' => round($observedSeconds, 3),
            'scheduler_invocations' => $invocationCount,
            'started_worker_executions' => $startedExecutions,
            'successful_worker_executions' =>
                $this->countScheduleStatus($scheduleRecords, Schedule::STATUS_SUCCESS),
            'error_worker_executions' =>
                $this->countScheduleStatus($scheduleRecords, Schedule::STATUS_ERROR),
            'invocations_without_started_job' => max(0, $invocationCount - $startedExecutions),
            'missed_schedules' => $this->countScheduleStatus($scheduleRecords, Schedule::STATUS_MISSED),
            'due_schedules_still_pending' => $this->countDuePendingSchedules(
                $scheduleRecords,
                $observedUntil
            ),
            'idle_gap_count_between_workers' => count($idleGaps),
            'total_idle_gap_seconds_between_workers' => round(array_sum($idleGaps), 3),
            'maximum_idle_gap_seconds_between_workers' => $idleGaps === []
                ? 0.0
                : round(max($idleGaps), 3),
            'unused_observed_seconds' => round(max(0.0, $observedSeconds - $busySeconds), 3),
            'worker_time_utilization_percent' => $observedSeconds > 0
                ? round(min(100.0, $busySeconds / $observedSeconds * 100), 3)
                : 0.0,
            'failed_scheduler_processes' => $this->countFailedInvocations($invocations),
        ], $this->getWorkerDurationMetrics($windows, $busySeconds, $intervalSeconds));
    }

    /**
     * @param list<array{executed_at: float, finished_at: float}> $windows
     * @return array<string, float|int>
     */
    private function getWorkerDurationMetrics(
        array $windows,
        float $busySeconds,
        int $intervalSeconds
    ): array {
        $longWorkerExecutions = 0;
        $maximumWorkerSeconds = 0.0;

        foreach ($windows as $window) {
            $duration = $window['finished_at'] - $window['executed_at'];
            $maximumWorkerSeconds = max($maximumWorkerSeconds, $duration);

            if ($duration <= $intervalSeconds) {
                continue;
            }

            $longWorkerExecutions++;
        }

        return [
            'worker_executions_longer_than_interval' => $longWorkerExecutions,
            'total_worker_seconds' => round($busySeconds, 3),
            'average_worker_seconds' => $windows === []
                ? 0.0
                : round($busySeconds / count($windows), 3),
            'maximum_worker_seconds' => round($maximumWorkerSeconds, 3),
        ];
    }

    /**
     * @param list<array{
     *     schedule_id: int,
     *     status: string,
     *     messages: string|null,
     *     created_at: string,
     *     scheduled_at: string,
     *     executed_at: string|null,
     *     finished_at: string|null
     * }> $scheduleRecords
     * @return list<array{executed_at: float, finished_at: float}>
     */
    private function getExecutionWindows(array $scheduleRecords): array
    {
        $windows = [];

        foreach ($scheduleRecords as $record) {
            if ($record['executed_at'] === null || $record['finished_at'] === null) {
                continue;
            }

            $executedAt = $this->getTimestamp($record['executed_at']);
            $finishedAt = $this->getTimestamp($record['finished_at']);

            if ($finishedAt < $executedAt) {
                throw new RuntimeException('A Magento cron schedule finished before it started.');
            }

            $windows[] = [
                'executed_at' => $executedAt,
                'finished_at' => $finishedAt,
            ];
        }

        return $windows;
    }

    /**
     * @param array{executed_at: float, finished_at: float} $first
     * @param array{executed_at: float, finished_at: float} $second
     */
    private function compareWindows(array $first, array $second): int
    {
        return $first['executed_at'] <=> $second['executed_at'];
    }

    /**
     * @param list<array{executed_at: float, finished_at: float}> $windows
     */
    private function getBusySeconds(array $windows): float
    {
        $busySeconds = 0.0;

        foreach ($this->mergeWindows($windows) as $window) {
            $busySeconds += $window['finished_at'] - $window['executed_at'];
        }

        return $busySeconds;
    }

    /**
     * @param list<array{executed_at: float, finished_at: float}> $windows
     * @return list<array{executed_at: float, finished_at: float}>
     */
    private function mergeWindows(array $windows): array
    {
        $merged = [];

        foreach ($windows as $window) {
            $lastIndex = count($merged) - 1;

            if ($lastIndex < 0 || $window['executed_at'] > $merged[$lastIndex]['finished_at']) {
                $merged[] = $window;
                continue;
            }

            $merged[$lastIndex]['finished_at'] = max(
                $merged[$lastIndex]['finished_at'],
                $window['finished_at']
            );
        }

        return $merged;
    }

    /**
     * @param list<array{executed_at: float, finished_at: float}> $windows
     * @return list<float>
     */
    private function getIdleGaps(array $windows): array
    {
        $gaps = [];
        $previousFinish = null;

        foreach ($windows as $window) {
            if ($previousFinish !== null && $window['executed_at'] > $previousFinish) {
                $gaps[] = $window['executed_at'] - $previousFinish;
            }

            $previousFinish = max($previousFinish ?? 0.0, $window['finished_at']);
        }

        return $gaps;
    }

    /**
     * @param list<array{status: string}> $scheduleRecords
     */
    private function countScheduleStatus(array $scheduleRecords, string $status): int
    {
        $count = 0;

        foreach ($scheduleRecords as $record) {
            if ($record['status'] !== $status) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    /**
     * @param list<array{status: string, scheduled_at: string}> $scheduleRecords
     */
    private function countDuePendingSchedules(array $scheduleRecords, float $observedUntil): int
    {
        $count = 0;

        foreach ($scheduleRecords as $record) {
            if ($record['status'] !== Schedule::STATUS_PENDING
                || $this->getTimestamp($record['scheduled_at']) > $observedUntil
            ) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    /**
     * @param list<array{exit_code: int}> $invocations
     */
    private function countFailedInvocations(array $invocations): int
    {
        $count = 0;

        foreach ($invocations as $invocation) {
            if ($invocation['exit_code'] === 0) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    private function getTimestamp(string $value): float
    {
        $date = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $value,
            new DateTimeZone('UTC')
        );

        if (!$date instanceof DateTimeImmutable) {
            throw new RuntimeException(sprintf('Cron timestamp "%s" is invalid.', $value));
        }

        return (float) $date->getTimestamp();
    }
}
