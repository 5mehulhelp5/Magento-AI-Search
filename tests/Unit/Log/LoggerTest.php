<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Log;

use DavidBel\AiSearch\Log\Logger;
use DavidBel\AiSearch\Model\EmbeddingBacklog\ErrorDetails;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Operation;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class LoggerTest extends TestCase
{
    public function testLogsCronStart(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('Cron started.', ['cron' => 'cron_name']);

        (new Logger($logger))->cronStarted('cron_name');
    }

    public function testLogsCronFinish(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('Cron finished.', ['cron' => 'cron_name']);

        (new Logger($logger))->cronFinished('cron_name');
    }

    public function testLogsACronFailure(): void
    {
        $failure = new RuntimeException('failed', 503);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with('Cron failed.', [
                'cron' => 'cron_name',
                'error_code' => '503',
                'error_message' => 'failed',
                'exception' => $failure,
            ]);

        (new Logger($logger))->cronFailed('cron_name', $failure);
    }

    public function testLogsIndexerStart(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('Indexer started.', ['indexer' => 'products', 'update_mode' => 'full']);

        (new Logger($logger))->indexerStarted('products', 'full');
    }

    public function testLogsIndexerCompletion(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('Indexer completed.', ['indexer' => 'products', 'update_mode' => 'full']);

        (new Logger($logger))->indexerCompleted('products', 'full');
    }

    public function testLogsAnIndexerFailure(): void
    {
        $failure = new RuntimeException('failed');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with('Indexer failed.', [
                'indexer' => 'products',
                'update_mode' => 'delta',
                'error_code' => null,
                'error_message' => 'failed',
                'exception' => $failure,
            ]);

        (new Logger($logger))->indexerFailed('products', 'delta', $failure);
    }

    public function testLogsDocumentBatchStart(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('Document batch started.', [
                'update_mode' => 'delta',
                'index_version' => 7,
                'product_count' => 2,
                'product_ids' => [10, 20],
            ]);

        (new Logger($logger))->documentBatchStarted('delta', 7, [10, 20]);
    }

    public function testLogsWorkerStart(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('Worker started.', ['operation' => 'upsert', 'index_version' => 7]);

        (new Logger($logger))->workerStarted(Operation::Upsert, 7);
    }

    public function testLogsBatchStart(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('Batch started.', [
                'operation' => 'delete',
                'index_version' => 7,
                'batch_id' => 2,
                'item_count' => 2,
                'product_ids' => [10],
                'backlog_ids' => [30, 40],
            ]);

        (new Logger($logger))->batchStarted(Operation::Delete, 7, 2, [10], [30, 40]);
    }

    public function testLogsSemanticSearchFailure(): void
    {
        $failure = new RuntimeException('failed');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'Semantic catalog search failed. Magento full-text search will be used.',
                ['exception' => $failure]
            );

        (new Logger($logger))->semanticSearchFailed($failure);
    }

    public function testLogsPhysicalIndexListingFailure(): void
    {
        $failure = new RuntimeException('failed');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'Obsolete OpenSearch index versions could not be listed.',
                ['exception' => $failure]
            );

        (new Logger($logger))->physicalIndexListingFailed($failure);
    }

    public function testLogsPhysicalIndexDeleteFailure(): void
    {
        $failure = new RuntimeException('failed');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with('An obsolete OpenSearch index version could not be deleted.', [
                'index_name' => 'chunks_v1',
                'exception' => $failure,
            ]);

        (new Logger($logger))->physicalIndexDeleteFailed('chunks_v1', $failure);
    }

    public function testLogsABatchFailureWithAnException(): void
    {
        $failure = new RuntimeException('failed');
        $details = new ErrorDetails('500', 'failed');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with('Batch failed.', [
                'operation' => 'upsert',
                'stage' => 'opensearch',
                'index_version' => 7,
                'batch_id' => 2,
                'item_count' => 2,
                'product_ids' => [10],
                'backlog_ids' => [30, 40],
                'error_code' => '500',
                'error_message' => 'failed',
                'exception' => $failure,
            ]);

        (new Logger($logger))->batchFailed(
            Operation::Upsert,
            'opensearch',
            7,
            2,
            [10],
            [30, 40],
            $details,
            $failure
        );
    }

    public function testLogsABatchFailureWithoutAnException(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        (new Logger($logger))->batchFailed(
            Operation::Delete,
            'cache',
            7,
            2,
            [],
            [],
            new ErrorDetails(null, 'failed')
        );
    }
}
