<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing;

use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Api\DocumentRepositoryInterface;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdater\DocumentSourceUpdater;
use DavidBel\AiSearch\Ingestion\DocumentProcessing\DocumentUpdater\Result;
use LogicException;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;

class DocumentUpdater
{
    public function __construct(
        private readonly SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly DocumentSourceUpdater $documentSourceUpdater
    ) {
    }

    /**
     * @param list<DocumentSource> $sources
     */
    public function deltaUpdate(
        string $sourceEntityType,
        int $sourceEntityId,
        array $sources
    ): Result {
        return $this->update(
            $sourceEntityType,
            $sourceEntityId,
            $sources,
            UpdateMode::DeltaUpdate
        );
    }

    /**
     * @param list<DocumentSource> $sources
     */
    public function fullUpdate(
        string $sourceEntityType,
        int $sourceEntityId,
        array $sources
    ): Result {
        return $this->update(
            $sourceEntityType,
            $sourceEntityId,
            $sources,
            UpdateMode::FullUpdate
        );
    }

    /**
     * @param list<DocumentSource> $sources
     */
    private function update(
        string $sourceEntityType,
        int $sourceEntityId,
        array $sources,
        UpdateMode $updateMode
    ): Result {
        $documentsBySourceCode = $this->getDocumentsBySourceCode(
            $sourceEntityType,
            $sourceEntityId
        );
        $updateResults = [];
        $processedSourceCodes = [];

        foreach ($sources as $source) {
            if (isset($processedSourceCodes[$source->sourceCode])) {
                throw new LogicException(
                    'A document source code cannot be processed more than once.'
                );
            }

            $updateResults[] = $this->documentSourceUpdater->update(
                $sourceEntityType,
                $sourceEntityId,
                $source,
                $documentsBySourceCode[$source->sourceCode] ?? [],
                $updateMode
            );
            $processedSourceCodes[$source->sourceCode] = true;
            unset($documentsBySourceCode[$source->sourceCode]);
        }

        foreach ($documentsBySourceCode as $documentsByStoreId) {
            $updateResults[] = $this->documentSourceUpdater->deleteDocuments(
                $documentsByStoreId
            );
        }

        return $this->combineResults($updateResults);
    }

    /**
     * @return array<string, array<int, DocumentInterface>>
     */
    private function getDocumentsBySourceCode(
        string $sourceEntityType,
        int $sourceEntityId
    ): array {
        $searchCriteria = $this->searchCriteriaBuilderFactory->create()
            ->addFilter(DocumentInterface::SOURCE_ENTITY_TYPE, $sourceEntityType)
            ->addFilter(DocumentInterface::SOURCE_ENTITY_ID, $sourceEntityId)
            ->create();
        $documentsBySourceCode = [];

        foreach ($this->documentRepository->getList($searchCriteria)->getItems() as $document) {
            $documentsBySourceCode[$document->getSourceCode()][$document->getStoreId()] = $document;
        }

        return $documentsBySourceCode;
    }

    /**
     * @param list<Result> $results
     */
    private function combineResults(array $results): Result
    {
        $upsertChunkIds = [];
        $deleteChunkIds = [];

        foreach ($results as $result) {
            array_push($upsertChunkIds, ...$result->upsertChunkIds);
            array_push($deleteChunkIds, ...$result->deleteChunkIds);
        }

        return new Result($upsertChunkIds, $deleteChunkIds);
    }
}
