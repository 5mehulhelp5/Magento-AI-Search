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

class ProcessingLogger
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
                'error_type' => $throwable::class,
                'error_code' => $errorDetails->code,
                'error_message' => $errorDetails->message,
                'stack_trace' => $throwable->getTraceAsString(),
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
            $context['error_type'] = $throwable::class;
            $context['stack_trace'] = $throwable->getTraceAsString();
        }

        $this->logger->error('Batch failed.', $context);
    }
}
