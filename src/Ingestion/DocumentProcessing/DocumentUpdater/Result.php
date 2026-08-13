<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdater;

readonly class Result
{
    /**
     * @param list<int> $upsertChunkIds
     * @param list<int> $deletionChunkIds
     */
    public function __construct(
        public array $upsertChunkIds,
        public array $deletionChunkIds
    ) {
    }
}
