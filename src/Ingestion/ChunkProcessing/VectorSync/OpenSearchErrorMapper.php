<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync;

use DavidBel\AiSearch\Model\EmbeddingBacklog\ErrorDetails;

class OpenSearchErrorMapper
{
    /**
     * @param array<array-key, mixed> $operation
     */
    public function map(array $operation): ErrorDetails
    {
        $status = $operation['status'] ?? null;
        $error = $operation['error'] ?? null;

        return new ErrorDetails(
            is_int($status) ? (string) $status : null,
            $this->getErrorMessage($error, $status)
        );
    }

    private function getErrorMessage(mixed $error, mixed $status): string
    {
        if (is_string($error) && trim($error) !== '') {
            return $error;
        }

        if (is_array($error)) {
            $message = $this->getStructuredErrorMessage($error);

            if ($message !== null) {
                return $message;
            }
        }

        return $this->getFallbackMessage($status);
    }

    /**
     * @param array<array-key, mixed> $error
     */
    private function getStructuredErrorMessage(array $error): ?string
    {
        $type = $error['type'] ?? null;
        $reason = $error['reason'] ?? null;

        if (is_string($type) && is_string($reason)) {
            return sprintf('%s: %s', $type, $reason);
        }

        if (is_string($reason)) {
            return $reason;
        }

        return is_string($type) ? $type : null;
    }

    private function getFallbackMessage(mixed $status): string
    {
        return is_int($status)
            ? sprintf('OpenSearch bulk item failed with HTTP status %d.', $status)
            : 'OpenSearch bulk item failed.';
    }
}
