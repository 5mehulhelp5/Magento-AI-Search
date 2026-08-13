<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Stress\Support;

use DavidBel\AiSearch\Model\EmbeddingBacklog\Operation;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Status;
use DavidBel\AiSearch\Tests\Stress\MagentoEnvironment;
use DavidBel\AiSearch\Tests\Stress\Support\CronScheduler\Invocation;
use DavidBel\AiSearch\Tests\Stress\Support\CronScheduler\Result;
use DavidBel\AiSearch\Tests\Stress\Support\CronScheduler\RunState;
use RuntimeException;
use Symfony\Component\Process\Process;

class CronScheduler
{
    public const int INTERVAL_SECONDS = 60;

    private const int SAMPLE_INTERVAL_SECONDS = 5;
    private const int MAXIMUM_TEST_RUNTIME_SECONDS = 3_600;
    private const int POLL_INTERVAL_MICROSECONDS = 250_000;

    /**
     * @var list<Invocation>
     */
    private array $invocations = [];

    public function __construct(
        private readonly CronSchedule $cronSchedule
    ) {
    }

    public function launch(float $scheduledLaunchAt): void
    {
        $sequence = count($this->invocations) + 1;
        $command = [
            PHP_BINARY,
            MagentoEnvironment::getMagentoRoot() . '/bin/magento',
            'cron:run',
            '--group=' . CronSchedule::GROUP_ID,
        ];
        $launchedAt = microtime(true);
        $process = new Process($command, MagentoEnvironment::getMagentoRoot(), timeout: null);
        $process->start();

        $this->invocations[] = new Invocation(
            $process,
            $sequence,
            $scheduledLaunchAt,
            $launchedAt
        );
    }

    /**
     * @param list<int> $parentIds
     */
    public function run(PipelineState $pipelineState, array $parentIds): Result
    {
        $state = new RunState(
            microtime(true),
            self::INTERVAL_SECONDS,
            self::SAMPLE_INTERVAL_SECONDS,
            self::MAXIMUM_TEST_RUNTIME_SECONDS
        );

        try {
            while (!$this->tick($state, $pipelineState, $parentIds)) {
                if ($state->isExpired(microtime(true))) {
                    throw new RuntimeException(
                        'The scheduler-driven timeline two test did not finish within one hour.'
                    );
                }

                usleep(self::POLL_INTERVAL_MICROSECONDS);
            }
        } finally {
            $this->terminateRunningInvocations();
        }

        return new Result(
            $state->observedFrom,
            microtime(true),
            $state->getSamples(),
            $this->getInvocationRecords()
        );
    }

    public function refresh(): void
    {
        foreach ($this->invocations as $invocation) {
            $invocation->refresh();
        }
    }

    public function hasRunningInvocations(): bool
    {
        foreach ($this->invocations as $invocation) {
            if ($invocation->isRunning()) {
                return true;
            }
        }

        return false;
    }

    public function terminateRunningInvocations(): void
    {
        foreach ($this->invocations as $invocation) {
            $invocation->terminate();
        }
    }

    /**
     * @return list<array{
     *     sequence: int,
     *     scheduled_launch_at: float,
     *     launched_at: float,
     *     finished_at: float,
     *     launch_delay_seconds: float,
     *     duration_seconds: float,
     *     exit_code: int,
     *     output: string
     * }>
     */
    public function getInvocationRecords(): array
    {
        $records = [];

        foreach ($this->invocations as $invocation) {
            $records[] = $invocation->toArray();
        }

        return $records;
    }

    /**
     * @param list<int> $parentIds
     */
    private function tick(RunState $state, PipelineState $pipelineState, array $parentIds): bool
    {
        $now = microtime(true);
        $this->refresh();
        $pendingCount = $pipelineState->getAllBacklogCount(Operation::Upsert, Status::Pending);
        $failedCount = $pipelineState->getAllBacklogCount(Operation::Upsert, Status::Failed);
        $this->launchWhenDue($state, $now, $pendingCount);
        $this->sampleWhenDue(
            $state,
            $pipelineState,
            $parentIds,
            $now,
            $pendingCount,
            $failedCount
        );

        return $pendingCount === 0
            && !$this->hasRunningInvocations()
            && !$this->cronSchedule->hasRunningJob();
    }

    private function launchWhenDue(RunState $state, float $now, int $pendingCount): void
    {
        if (!$state->shouldLaunch($now, $pendingCount)) {
            return;
        }

        $this->launch($state->getNextLaunchAt());
        $state->recordLaunch();
    }

    /**
     * @param list<int> $parentIds
     */
    private function sampleWhenDue(
        RunState $state,
        PipelineState $pipelineState,
        array $parentIds,
        float $now,
        int $pendingCount,
        int $failedCount
    ): void {
        if (!$state->shouldSample($now)) {
            return;
        }

        $state->recordSample([
            'elapsed_seconds' => round($now - $state->observedFrom, 3),
            'pending_upserts' => $pendingCount,
            'done_upserts' => $pipelineState->getAllBacklogCount(Operation::Upsert, Status::Done),
            'failed_upserts' => $failedCount,
            'stress_remote_documents' => $pipelineState->getRemoteDocumentCount($parentIds),
        ]);
    }
}
