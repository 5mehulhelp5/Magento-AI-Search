<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Log;

use DavidBel\AiSearch\Model\EmbeddingBacklog\ErrorDetails;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Operation;
use Psr\Log\LoggerInterface;
use Throwable;

class Logger
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function cronStarted(string $cron): void
    {
        $this->logger->info('Cron started.', ['cron' => $cron]);
    }

    public function cronFinished(string $cron): void
    {
        $this->logger->info('Cron finished.', ['cron' => $cron]);
    }

    public function cronFailed(string $cron, Throwable $throwable): void
    {
        $errorDetails = new ErrorDetails(
            $throwable->getCode() > 0 ? (string) $throwable->getCode() : null,
            $throwable->getMessage()
        );

        $this->logger->error(
            'Cron failed.',
            [
                'cron' => $cron,
                'error_code' => $errorDetails->code,
                'error_message' => $errorDetails->message,
                'exception' => $throwable,
            ]
        );
    }

    public function indexerStarted(string $indexer, string $updateMode): void
    {
        $this->logger->info(
            'Indexer started.',
            [
                'indexer' => $indexer,
                'update_mode' => $updateMode,
            ]
        );
    }

    public function indexerCompleted(string $indexer, string $updateMode): void
    {
        $this->logger->info(
            'Indexer completed.',
            [
                'indexer' => $indexer,
                'update_mode' => $updateMode,
            ]
        );
    }

    public function indexerFailed(
        string $indexer,
        string $updateMode,
        Throwable $throwable
    ): void {
        $errorDetails = new ErrorDetails(
            $throwable->getCode() > 0 ? (string) $throwable->getCode() : null,
            $throwable->getMessage()
        );

        $this->logger->error(
            'Indexer failed.',
            [
                'indexer' => $indexer,
                'update_mode' => $updateMode,
                'error_code' => $errorDetails->code,
                'error_message' => $errorDetails->message,
                'exception' => $throwable,
            ]
        );
    }

    /**
     * @param list<int> $productIds
     */
    public function documentBatchStarted(
        string $updateMode,
        int $indexVersion,
        array $productIds
    ): void {
        $this->logger->info(
            'Document batch started.',
            [
                'update_mode' => $updateMode,
                'index_version' => $indexVersion,
                'product_count' => count($productIds),
                'product_ids' => $productIds,
            ]
        );
    }

    public function semanticSearchFailed(Throwable $throwable): void
    {
        $this->logger->error(
            'Semantic catalog search failed. Magento full-text search will be used.',
            ['exception' => $throwable]
        );
    }

    public function physicalIndexListingFailed(Throwable $throwable): void
    {
        $this->logger->warning(
            'Obsolete OpenSearch index versions could not be listed.',
            ['exception' => $throwable]
        );
    }

    public function physicalIndexDeleteFailed(
        string $indexName,
        Throwable $throwable
    ): void {
        $this->logger->warning(
            'An obsolete OpenSearch index version could not be deleted.',
            [
                'index_name' => $indexName,
                'exception' => $throwable,
            ]
        );
    }

    public function workerStarted(Operation $operation, int $indexVersion): void
    {
        $this->logger->info(
            'Worker started.',
            [
                'operation' => $operation->value,
                'index_version' => $indexVersion,
            ]
        );
    }

    /**
     * @param list<int> $productIds
     * @param list<int> $backlogIds
     */
    public function batchStarted(
        Operation $operation,
        int $indexVersion,
        int $batchId,
        array $productIds,
        array $backlogIds
    ): void {
        $this->logger->info(
            'Batch started.',
            [
                'operation' => $operation->value,
                'index_version' => $indexVersion,
                'batch_id' => $batchId,
                'item_count' => count($backlogIds),
                'product_ids' => $productIds,
                'backlog_ids' => $backlogIds,
            ]
        );
    }

    /**
     * @param list<int> $productIds
     * @param list<int> $backlogIds
     */
    public function batchFailed(
        Operation $operation,
        string $errorStage,
        int $indexVersion,
        int $batchId,
        array $productIds,
        array $backlogIds,
        ErrorDetails $errorDetails,
        ?Throwable $throwable = null
    ): void {
        $context = [
            'operation' => $operation->value,
            'stage' => $errorStage,
            'index_version' => $indexVersion,
            'batch_id' => $batchId,
            'item_count' => count($backlogIds),
            'product_ids' => $productIds,
            'backlog_ids' => $backlogIds,
            'error_code' => $errorDetails->code,
            'error_message' => $errorDetails->message,
        ];

        if ($throwable !== null) {
            $context['exception'] = $throwable;
        }

        $this->logger->error('Batch failed.', $context);
    }
}
