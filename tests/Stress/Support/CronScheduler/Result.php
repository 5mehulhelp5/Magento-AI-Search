<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Stress\Support\CronScheduler;

readonly class Result
{
    /**
     * @param list<array<string, float|int>> $samples
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
     */
    public function __construct(
        public float $observedFrom,
        public float $observedUntil,
        public array $samples,
        public array $invocations
    ) {
    }
}
