<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Stress\Support;

use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Client\OpenSearch;
use DavidBel\AiSearch\Indexer\Versioning\State\Flag;
use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndexProvider;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Operation;
use DavidBel\AiSearch\Model\EmbeddingBacklog\Status;
use DavidBel\AiSearch\Model\ResourceModel\Chunk\CollectionFactory as ChunkCollectionFactory;
use DavidBel\AiSearch\Model\ResourceModel\Document\Collection as DocumentCollection;
use DavidBel\AiSearch\Model\ResourceModel\Document\CollectionFactory as DocumentCollectionFactory;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory as BacklogCollectionFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use RuntimeException;

class PipelineState
{
    private const string SOURCE_ENTITY_TYPE = 'product';

    public function __construct(
        private readonly DocumentCollectionFactory $documentCollectionFactory,
        private readonly ChunkCollectionFactory $chunkCollectionFactory,
        private readonly BacklogCollectionFactory $backlogCollectionFactory,
        private readonly Flag $versionFlag,
        private readonly PhysicalIndexProvider $physicalIndexProvider,
        private readonly OpenSearch $openSearch
    ) {
    }

    /**
     * @param list<int> $productIds
     */
    public function getDocumentCount(array $productIds): int
    {
        return $this->createDocumentCollection($productIds)->getSize();
    }

    /**
     * @param list<int> $productIds
     * @return list<string>
     */
    public function getSourceCodes(array $productIds): array
    {
        $sourceCodes = [];

        foreach ($this->createDocumentCollection($productIds)->getItems() as $document) {
            $sourceCode = $document->getSourceCode();

            if (!is_string($sourceCode)) {
                throw new RuntimeException('A stress document has an invalid source code.');
            }

            $sourceCodes[$sourceCode] = true;
        }

        $result = array_keys($sourceCodes);
        sort($result);

        return $result;
    }

    /**
     * @param list<int> $productIds
     */
    public function getChunkCount(array $productIds, ?string $sourceCode = null): int
    {
        $documentIds = $this->getDocumentIds($productIds, $sourceCode);

        if ($documentIds === []) {
            return 0;
        }

        $collection = $this->chunkCollectionFactory->create();
        $collection->addFieldToFilter('document_id', ['in' => $documentIds]);

        return $collection->getSize();
    }

    /**
     * @param list<int> $productIds
     */
    public function getBacklogCount(
        array $productIds,
        Operation $operation,
        ?Status $status = null
    ): int {
        if ($productIds === []) {
            return 0;
        }

        $collection = $this->backlogCollectionFactory->create();
        $collection->addFieldToFilter('source_entity_type', self::SOURCE_ENTITY_TYPE);
        $collection->addFieldToFilter('source_entity_id', ['in' => $productIds]);
        $collection->addFieldToFilter('operation', $operation->value);

        if ($status !== null) {
            $collection->addFieldToFilter('status', $status->value);
        }

        return $collection->getSize();
    }

    public function getAllDocumentCount(): int
    {
        return $this->documentCollectionFactory->create()->getSize();
    }

    public function getAllChunkCount(): int
    {
        return $this->chunkCollectionFactory->create()->getSize();
    }

    public function getAllBacklogCount(?Operation $operation = null, ?Status $status = null): int
    {
        $collection = $this->backlogCollectionFactory->create();

        if ($operation !== null) {
            $collection->addFieldToFilter('operation', $operation->value);
        }

        if ($status !== null) {
            $collection->addFieldToFilter('status', $status->value);
        }

        return $collection->getSize();
    }

    public function hasWritableIndexForCurrentConfiguration(): bool
    {
        return $this->physicalIndexProvider->getTargetForCurrentConfiguration() !== null
            || $this->physicalIndexProvider->getActiveForCurrentConfiguration() !== null;
    }

    public function hasActiveIndexForCurrentConfiguration(): bool
    {
        return $this->physicalIndexProvider->getActiveForCurrentConfiguration() !== null;
    }

    /**
     * @param list<int> $productIds
     */
    public function getRemoteDocumentCount(array $productIds): int
    {
        if ($productIds === []) {
            return 0;
        }

        $state = $this->versionFlag->get();
        $physicalIndex = $state->target !== null
            ? $state->target->physicalIndex
            : $state->active;

        if ($physicalIndex === null) {
            return 0;
        }

        $response = $this->openSearch->search($physicalIndex, [
            'size' => 0,
            'track_total_hits' => true,
            'query' => [
                'bool' => [
                    'filter' => [
                        ['term' => ['source_entity_type' => self::SOURCE_ENTITY_TYPE]],
                        ['terms' => ['source_entity_id' => $productIds]],
                    ],
                ],
            ],
        ]);

        return $this->getTotalHits($response);
    }

    /**
     * @param list<int> $productIds
     */
    public function removeLocalData(array $productIds): void
    {
        if ($productIds === []) {
            return;
        }

        $backlogResource = $this->backlogCollectionFactory->create()->getResourceModel();
        $connection = $backlogResource->getConnection();

        if (!$connection instanceof AdapterInterface) {
            throw new RuntimeException('The AI search database connection is unavailable.');
        }

        $connection->delete(
            $backlogResource->getMainTable(),
            [
                'source_entity_type = ?' => self::SOURCE_ENTITY_TYPE,
                'source_entity_id IN (?)' => $productIds,
            ]
        );
        $documentResource = $this->documentCollectionFactory->create()->getResourceModel();
        $connection->delete(
            $documentResource->getMainTable(),
            [
                'source_entity_type = ?' => self::SOURCE_ENTITY_TYPE,
                'source_entity_id IN (?)' => $productIds,
            ]
        );
    }

    public function markMissingChunkUpsertsOutdated(): int
    {
        return $this->backlogCollectionFactory
            ->create()
            ->getResourceModel()
            ->markMissingChunkUpsertsOutdated();
    }

    /**
     * @param list<int> $productIds
     * @return list<int>
     */
    private function getDocumentIds(array $productIds, ?string $sourceCode): array
    {
        $collection = $this->createDocumentCollection($productIds);

        if ($sourceCode !== null) {
            $collection->addFieldToFilter(DocumentInterface::SOURCE_CODE, $sourceCode);
        }

        $documentIds = [];

        foreach ($collection->getAllIds() as $documentId) {
            $normalizedId = filter_var($documentId, FILTER_VALIDATE_INT);

            if ($normalizedId === false || $normalizedId < 1) {
                throw new RuntimeException('A stress document has an invalid ID.');
            }

            $documentIds[] = $normalizedId;
        }

        return $documentIds;
    }

    /**
     * @param list<int> $productIds
     */
    private function createDocumentCollection(array $productIds): DocumentCollection
    {
        $collection = $this->documentCollectionFactory->create();
        $collection->addFieldToFilter(DocumentInterface::SOURCE_ENTITY_TYPE, self::SOURCE_ENTITY_TYPE);

        if ($productIds === []) {
            $collection->addFieldToFilter(DocumentInterface::SOURCE_ENTITY_ID, '-1');

            return $collection;
        }

        $collection->addFieldToFilter(DocumentInterface::SOURCE_ENTITY_ID, ['in' => $productIds]);

        return $collection;
    }

    /**
     * @param array<array-key, mixed> $response
     */
    private function getTotalHits(array $response): int
    {
        $hits = $response['hits'] ?? null;

        if (!is_array($hits)) {
            throw new RuntimeException('OpenSearch returned no stress search hits.');
        }

        $total = $hits['total'] ?? null;

        if (is_array($total)) {
            $total = $total['value'] ?? null;
        }

        $documentCount = filter_var($total, FILTER_VALIDATE_INT);

        if ($documentCount === false || $documentCount < 0) {
            throw new RuntimeException('OpenSearch returned an invalid stress document count.');
        }

        return $documentCount;
    }
}
