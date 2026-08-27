<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Stress;

use DavidBel\AiSearch\Config\SemanticDataProcessingConfig;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Operation;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Status;
use DavidBel\AiSearch\Test\Stress\Support\CatalogDataset;
use DavidBel\AiSearch\Test\Stress\Support\CronSchedule;
use DavidBel\AiSearch\Test\Stress\Support\CronSchedule\Metrics;
use DavidBel\AiSearch\Test\Stress\Support\CronScheduler;
use DavidBel\AiSearch\Test\Stress\Support\CronScheduler\Result;
use DavidBel\AiSearch\Test\Stress\Support\Measurement;
use DavidBel\AiSearch\Test\Stress\Support\PipelineState;
use DavidBel\AiSearch\Test\Stress\Support\StressConfiguration;

class TimelineTwoTest extends StressTestCase
{
    public function testMagentoSchedulerProcessesEveryPendingChunk(): void
    {
        $dataset = $this->create(CatalogDataset::class);
        $configuration = $this->create(StressConfiguration::class);
        $pipelineState = $this->create(PipelineState::class);
        $cronSchedule = $this->create(CronSchedule::class);
        $scheduler = $this->create(CronScheduler::class);
        $productIds = $dataset->getSearchableProductIds();
        $initialPendingCount = $pipelineState->getAllBacklogCount(Operation::Upsert, Status::Pending);

        self::assertCount(
            $configuration->usesStandaloneSimpleProducts()
                ? $configuration->getSimpleProductCount()
                : $configuration->getConfigurableProductCount(),
            $productIds
        );
        self::assertGreaterThan(0, $pipelineState->getChunkCount($productIds));
        self::assertGreaterThan(0, $initialPendingCount);
        $cronSchedule->reset();

        $result = $scheduler->run($pipelineState, $productIds);
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
            $productIds,
            $initialPendingCount,
            $result->observedFrom,
            $result->observedUntil,
            $metrics,
            $result->samples,
            $result->invocations,
            $scheduleRecords
        );

        $this->assertSuccessfulResult($pipelineState, $productIds, $result, $metrics);
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
        $semanticDataProcessingConfig = $this->get(SemanticDataProcessingConfig::class);
        $measurement = $this->create(Measurement::class);
        $duration = $observedUntil - $observedFrom;
        $stage = array_merge($metrics, [
            'duration_seconds' => round($duration, 3),
            'initial_pending_upserts' => $initialPendingCount,
            'completed_upserts' => $pipelineState->getAllBacklogCount(Operation::Upsert, Status::Done),
            'failed_upserts' => $pipelineState->getAllBacklogCount(Operation::Upsert, Status::Failed),
            'upserts_per_second' => round($initialPendingCount / $duration, 3),
            'stress_remote_documents' => $pipelineState->getRemoteDocumentCount($parentIds),
            'embedding_batch_size' =>
                $semanticDataProcessingConfig->getVectorEmbeddingBatchSize(),
            'concurrent_embedding_requests' =>
                $semanticDataProcessingConfig->getVectorEmbeddingConcurrentRequests(),
            'worker_runtime_boundary_seconds' =>
                $semanticDataProcessingConfig->getVectorEmbeddingMaximumRuntimeSeconds(),
            'peak_memory_bytes' => memory_get_peak_usage(true),
        ]);
        $measurement->recordStage('timeline_two_scheduler', $stage);
        $measurement->recordDetails('timeline_two_samples', $samples);
        $measurement->recordDetails('timeline_two_scheduler_invocations', $invocations);
        $measurement->recordDetails('timeline_two_cron_schedule', $scheduleRecords);
    }
}
