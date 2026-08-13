<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Stress;

use DavidBel\AiSearch\Config\DataProcessingConfig;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Operation;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Status;
use DavidBel\AiSearch\Tests\Stress\Support\CatalogDataset;
use DavidBel\AiSearch\Tests\Stress\Support\CronSchedule;
use DavidBel\AiSearch\Tests\Stress\Support\CronSchedule\Metrics;
use DavidBel\AiSearch\Tests\Stress\Support\CronScheduler;
use DavidBel\AiSearch\Tests\Stress\Support\CronScheduler\Result;
use DavidBel\AiSearch\Tests\Stress\Support\Measurement;
use DavidBel\AiSearch\Tests\Stress\Support\PipelineState;
use DavidBel\AiSearch\Tests\Stress\Support\StressConfiguration;

class TimelineTwoTest extends StressTestCase
{
    public function testMagentoSchedulerProcessesEveryPendingChunk(): void
    {
        $dataset = $this->create(CatalogDataset::class);
        $configuration = $this->create(StressConfiguration::class);
        $pipelineState = $this->create(PipelineState::class);
        $cronSchedule = $this->create(CronSchedule::class);
        $scheduler = $this->create(CronScheduler::class);
        $parentIds = $dataset->getConfigurableProductIds();
        $initialPendingCount = $pipelineState->getAllBacklogCount(Operation::Upsert, Status::Pending);

        self::assertCount($configuration->getConfigurableProductCount(), $parentIds);
        self::assertGreaterThan(0, $pipelineState->getChunkCount($parentIds));
        self::assertGreaterThan(0, $initialPendingCount);
        $cronSchedule->reset();

        $result = $scheduler->run($pipelineState, $parentIds);
        $scheduleRecords = $cronSchedule->getRecords();
        $metrics = $this->create(Metrics::class)->calculate(
            $scheduleRecords,
            $result->invocations,
            $result->observedFrom,
            $result->observedUntil,
            CronScheduler::INTERVAL_SECONDS
        );
        $this->recordMeasurements(
            $pipelineState,
            $parentIds,
            $initialPendingCount,
            $result->observedFrom,
            $result->observedUntil,
            $metrics,
            $result->samples,
            $result->invocations,
            $scheduleRecords
        );

        $this->assertSuccessfulResult($pipelineState, $parentIds, $result, $metrics);
    }

    /**
     * @param list<int> $parentIds
     * @param array<string, float|int> $metrics
     */
    private function assertSuccessfulResult(
        PipelineState $pipelineState,
        array $parentIds,
        Result $result,
        array $metrics
    ): void {
        self::assertNotEmpty($result->invocations);
        self::assertGreaterThan(0, $metrics['started_worker_executions']);
        self::assertSame(0, $metrics['failed_scheduler_processes']);
        self::assertSame(
            0,
            $pipelineState->getAllBacklogCount(Operation::Upsert, Status::Pending)
        );
        self::assertSame(
            0,
            $pipelineState->getAllBacklogCount(Operation::Upsert, Status::Failed)
        );
        self::assertSame(
            $pipelineState->getChunkCount($parentIds),
            $pipelineState->getBacklogCount($parentIds, Operation::Upsert, Status::Done)
        );
        self::assertTrue($pipelineState->hasWritableIndexForCurrentConfiguration());
        self::assertSame(
            $pipelineState->getChunkCount($parentIds),
            $pipelineState->getRemoteDocumentCount($parentIds)
        );
    }

    /**
     * @param list<int> $parentIds
     * @param array<string, float|int> $metrics
     * @param list<array<string, float|int>> $samples
     * @param list<array<string, float|int|string>> $invocations
     * @param list<array<string, int|string|null>> $scheduleRecords
     */
    private function recordMeasurements(
        PipelineState $pipelineState,
        array $parentIds,
        int $initialPendingCount,
        float $observedFrom,
        float $observedUntil,
        array $metrics,
        array $samples,
        array $invocations,
        array $scheduleRecords
    ): void {
        $dataProcessingConfig = $this->get(DataProcessingConfig::class);
        $measurement = $this->create(Measurement::class);
        $duration = $observedUntil - $observedFrom;
        $stage = array_merge($metrics, [
            'duration_seconds' => round($duration, 3),
            'initial_pending_upserts' => $initialPendingCount,
            'completed_upserts' => $pipelineState->getAllBacklogCount(Operation::Upsert, Status::Done),
            'failed_upserts' => $pipelineState->getAllBacklogCount(Operation::Upsert, Status::Failed),
            'upserts_per_second' => round($initialPendingCount / $duration, 3),
            'stress_remote_documents' => $pipelineState->getRemoteDocumentCount($parentIds),
            'embedding_batch_size' => $dataProcessingConfig->getVectorEmbeddingBatchSize(),
            'concurrent_embedding_requests' =>
                $dataProcessingConfig->getVectorEmbeddingConcurrentRequests(),
            'worker_runtime_boundary_seconds' =>
                $dataProcessingConfig->getVectorEmbeddingMaximumRuntimeSeconds(),
            'peak_memory_bytes' => memory_get_peak_usage(true),
        ]);
        $measurement->recordStage('timeline_two_scheduler', $stage);
        $measurement->recordDetails('timeline_two_samples', $samples);
        $measurement->recordDetails('timeline_two_scheduler_invocations', $invocations);
        $measurement->recordDetails('timeline_two_cron_schedule', $scheduleRecords);
    }
}
