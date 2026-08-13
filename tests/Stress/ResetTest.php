<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Stress;

use DavidBel\AiSearch\Tests\Stress\Support\CatalogDataset;
use DavidBel\AiSearch\Tests\Stress\Support\EnvironmentReset;
use DavidBel\AiSearch\Tests\Stress\Support\Measurement;
use DavidBel\AiSearch\Tests\Stress\Support\PipelineState;

class ResetTest extends StressTestCase
{
    public function testResetsOnlyTheLocalAiSearchStressEnvironment(): void
    {
        $startedAt = microtime(true);
        $dataset = $this->create(CatalogDataset::class);
        $dataset->removeCatalogData();

        $removed = $this->create(EnvironmentReset::class)->execute();
        $pipelineState = $this->create(PipelineState::class);
        self::assertSame(0, $pipelineState->getAllDocumentCount());
        self::assertSame(0, $pipelineState->getAllChunkCount());
        self::assertSame(0, $pipelineState->getAllBacklogCount());
        self::assertFalse($pipelineState->hasWritableIndexForCurrentConfiguration());

        $measurement = $this->create(Measurement::class);
        $measurement->resetRun();
        $measurement->recordStage('reset', [
            'duration_seconds' => round(microtime(true) - $startedAt, 3),
            'removed_indexes' => $removed['removed_indexes'],
            'removed_documents' => $removed['removed_documents'],
            'removed_chunks' => $removed['removed_chunks'],
            'removed_backlog' => $removed['removed_backlog'],
            'peak_memory_bytes' => memory_get_peak_usage(true),
        ]);
    }
}
