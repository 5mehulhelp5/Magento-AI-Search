<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingResultHandler;

use DavidBel\AiSearch\Model\EmbeddingBacklog\ErrorDetails;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use OpenSearch\Exception\HttpExceptionInterface;
use Throwable;

class FailureReasonMapper
{
    public function map(mixed $reason): ErrorDetails
    {
        if (!$reason instanceof Throwable) {
            return new ErrorDetails(null, 'Processing failed without an exception.');
        }

        if ($this->isTimeout($reason)) {
            return new ErrorDetails('timeout', 'The remote request timed out.');
        }

        if ($reason instanceof ConnectException) {
            return new ErrorDetails(
                'connection_error',
                'The remote service could not be reached.'
            );
        }

        if ($reason instanceof RequestException && $reason->getResponse() !== null) {
            $status = $reason->getResponse()->getStatusCode();

            return new ErrorDetails(
                (string) $status,
                sprintf('The remote request failed with HTTP status %d.', $status)
            );
        }

        if ($reason instanceof HttpExceptionInterface) {
            $status = $reason->getStatusCode();

            return new ErrorDetails(
                (string) $status,
                sprintf('OpenSearch request failed with HTTP status %d.', $status)
            );
        }

        return new ErrorDetails(
            $this->getErrorCode($reason),
            $reason->getMessage()
        );
    }

    private function isTimeout(Throwable $reason): bool
    {
        if ($reason instanceof ConnectException) {
            $handlerContext = $reason->getHandlerContext();

            if (($handlerContext['errno'] ?? null) === 28) {
                return true;
            }
        }

        return str_contains(strtolower($reason::class), 'timeout');
    }

    private function getErrorCode(Throwable $reason): ?string
    {
        return $reason->getCode() > 0 ? (string) $reason->getCode() : null;
    }
}
