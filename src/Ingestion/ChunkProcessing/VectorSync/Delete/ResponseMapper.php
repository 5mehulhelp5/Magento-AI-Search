<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Delete;

use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\FailedItem;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Item;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\OpenSearchErrorMapper;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\Result;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorSync\ResultFactory;
use UnexpectedValueException;

class ResponseMapper
{
    public function __construct(
        private readonly OpenSearchErrorMapper $openSearchErrorMapper,
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
            throw new UnexpectedValueException('OpenSearch returned an invalid bulk delete response.');
        }

        if (count($responseItems) !== count($items)) {
            throw new UnexpectedValueException('OpenSearch returned an unexpected bulk delete item count.');
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
     * @return array{list<Item>, list<FailedItem>}
     */
    private function categorize(array $responseItems, array $items): array
    {
        $successfulItems = [];
        $failedItems = [];

        foreach ($responseItems as $index => $responseItem) {
            $item = $items[$index];
            $operation = $this->getOperation($responseItem, $item);

            if ($this->isSuccessfulStatus($operation['status'] ?? null)) {
                $successfulItems[] = $item;

                continue;
            }

            $failedItems[] = new FailedItem(
                $item,
                $this->openSearchErrorMapper->map($operation)
            );
        }

        return [$successfulItems, $failedItems];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function getOperation(mixed $responseItem, Item $item): array
    {
        $operation = is_array($responseItem) ? ($responseItem['delete'] ?? null) : null;

        if (!is_array($operation)
            || ($operation['_id'] ?? null) !== (string) $item->chunkId
        ) {
            throw new UnexpectedValueException('OpenSearch returned an invalid bulk delete item.');
        }

        return $operation;
    }

    private function isSuccessfulStatus(mixed $status): bool
    {
        return is_int($status) && (($status >= 200 && $status < 300) || $status === 404);
    }

    /**
     * @param list<Item> $successfulItems
     * @param list<FailedItem> $failedItems
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
