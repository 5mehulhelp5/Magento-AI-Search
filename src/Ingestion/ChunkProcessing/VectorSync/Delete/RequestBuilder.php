<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Delete;

class RequestBuilder
{
    /**
     * @return list<array<string, mixed>>
     */
    public function build(Batch $batch): array
    {
        $body = [];

        foreach ($batch->getItems() as $item) {
            $body[] = [
                'delete' => [
                    '_id' => (string) $item->chunkId,
                ],
            ];
        }

        return $body;
    }
}
