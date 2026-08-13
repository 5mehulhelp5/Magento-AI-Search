<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Delete;

use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Item;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Result;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\ResultFactory;
use UnexpectedValueException;

class ResponseMapper
{
    public function __construct(
        private readonly ResultFactory $resultFactory
    ) {
    }

    /**
     * @param array<array-key, mixed> $response
     * @param list<Item> $items
     */
    public function map(array $response, array $items): Result
    {
        $errors = $response['errors'] ?? null;
        $responseItems = $response['items'] ?? null;

        if (!is_bool($errors) || !is_array($responseItems) || !array_is_list($responseItems)) {
            throw new UnexpectedValueException('OpenSearch returned an invalid bulk deletion response.');
        }

        if (count($responseItems) !== count($items)) {
            throw new UnexpectedValueException('OpenSearch returned an unexpected bulk deletion item count.');
        }

        [$successfulItems, $failedItems] = $this->categorize(
            $responseItems,
            $items
        );

        return $this->createResult($successfulItems, $failedItems);
    }

    /**
     * @param list<mixed> $responseItems
     * @param list<Item> $items
     * @return array{list<Item>, list<Item>}
     */
    private function categorize(array $responseItems, array $items): array
    {
        $successfulItems = [];
        $failedItems = [];

        foreach ($responseItems as $index => $responseItem) {
            $item = $items[$index];

            if ($this->isSuccessful($responseItem, $item)) {
                $successfulItems[] = $item;

                continue;
            }

            $failedItems[] = $item;
        }

        return [$successfulItems, $failedItems];
    }

    private function isSuccessful(mixed $responseItem, Item $item): bool
    {
        $operation = is_array($responseItem) ? ($responseItem['delete'] ?? null) : null;

        if (!is_array($operation)
            || ($operation['_id'] ?? null) !== (string) $item->chunkId
        ) {
            throw new UnexpectedValueException('OpenSearch returned an invalid bulk deletion item.');
        }

        $status = $operation['status'] ?? null;

        return is_int($status) && (($status >= 200 && $status < 300) || $status === 404);
    }

    /**
     * @param list<Item> $successfulItems
     * @param list<Item> $failedItems
     */
    private function createResult(
        array $successfulItems,
        array $failedItems
    ): Result {
        return $this->resultFactory->create([
            'successfulItems' => $successfulItems,
            'failedItems' => $failedItems,
        ]);
    }
}
