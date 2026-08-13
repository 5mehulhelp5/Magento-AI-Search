<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Stress\Support\CronScheduler;

class RunState
{
    private float $nextLaunchAt;
    private float $nextSampleAt;
    private readonly float $deadline;

    /**
     * @var list<array<string, float|int>>
     */
    private array $samples = [];

    public function __construct(
        public readonly float $observedFrom,
        private readonly int $schedulerIntervalSeconds,
        private readonly int $sampleIntervalSeconds,
        int $maximumRuntimeSeconds
    ) {
        $this->nextLaunchAt = $observedFrom;
        $this->nextSampleAt = $observedFrom;
        $this->deadline = $observedFrom + $maximumRuntimeSeconds;
    }

    public function shouldLaunch(float $now, int $pendingCount): bool
    {
        return $pendingCount > 0 && $now >= $this->nextLaunchAt;
    }

    public function getNextLaunchAt(): float
    {
        return $this->nextLaunchAt;
    }

    public function recordLaunch(): void
    {
        $this->nextLaunchAt += $this->schedulerIntervalSeconds;
    }

    public function shouldSample(float $now): bool
    {
        return $now >= $this->nextSampleAt;
    }

    /**
     * @param array<string, float|int> $sample
     */
    public function recordSample(array $sample): void
    {
        $this->samples[] = $sample;
        $this->nextSampleAt += $this->sampleIntervalSeconds;
    }

    /**
     * @return list<array<string, float|int>>
     */
    public function getSamples(): array
    {
        return $this->samples;
    }

    public function isExpired(float $now): bool
    {
        return $now >= $this->deadline;
    }
}
