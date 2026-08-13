<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Stress\Support\CronScheduler;

use RuntimeException;
use Symfony\Component\Process\Process;

class Invocation
{
    private ?float $finishedAt = null;
    private ?int $exitCode = null;

    public function __construct(
        private readonly Process $process,
        public readonly int $sequence,
        public readonly float $scheduledLaunchAt,
        public readonly float $launchedAt
    ) {
    }

    public function refresh(): void
    {
        if ($this->finishedAt !== null) {
            return;
        }

        if ($this->process->isRunning()) {
            return;
        }

        $this->finishedAt = microtime(true);
        $this->exitCode = $this->process->getExitCode();

        if ($this->exitCode !== null) {
            return;
        }

        throw new RuntimeException('The Magento cron invocation returned no exit code.');
    }

    public function isRunning(): bool
    {
        $this->refresh();

        return $this->finishedAt === null;
    }

    public function terminate(): void
    {
        if (!$this->isRunning()) {
            return;
        }

        $this->process->stop(10.0);
        $this->refresh();
    }

    /**
     * @return array{
     *     sequence: int,
     *     scheduled_launch_at: float,
     *     launched_at: float,
     *     finished_at: float,
     *     launch_delay_seconds: float,
     *     duration_seconds: float,
     *     exit_code: int,
     *     output: string
     * }
     */
    public function toArray(): array
    {
        $this->refresh();

        if ($this->finishedAt === null || $this->exitCode === null) {
            throw new RuntimeException('The Magento cron invocation is still running.');
        }

        $output = trim($this->process->getOutput() . PHP_EOL . $this->process->getErrorOutput());

        return [
            'sequence' => $this->sequence,
            'scheduled_launch_at' => $this->scheduledLaunchAt,
            'launched_at' => $this->launchedAt,
            'finished_at' => $this->finishedAt,
            'launch_delay_seconds' => round($this->launchedAt - $this->scheduledLaunchAt, 3),
            'duration_seconds' => round($this->finishedAt - $this->launchedAt, 3),
            'exit_code' => $this->exitCode,
            'output' => $output,
        ];
    }
}
