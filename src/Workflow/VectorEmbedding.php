<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow;

use DavidBel\AiSearch\Api\Data\ChunkInterface;
use DavidBel\AiSearch\Api\Data\DocumentInterface;
use DavidBel\AiSearch\Api\Data\EmbeddingBacklogInterface;
use DavidBel\AiSearch\Api\EmbedderClientInterface;
use DavidBel\AiSearch\Model\ResourceModel\EmbeddingBacklog\CollectionFactory;
use DavidBel\AiSearch\Workflow\VectorEmbedding\EmbeddingInput;
use Throwable;
use UnexpectedValueException;

class VectorEmbedding
{
    private const int BATCH_SIZE = 100;
    private const string EMBEDDER_ERROR_CATEGORY = 'embedder';

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly EmbedderClientInterface $embedderClient
    ) {
    }

    public function execute(): int
    {
        $resource = $this->collectionFactory->create()->getResourceModel();
        $inputs = $this->createEmbeddingInputs(
            $resource->getUpsertsForEmbedding(self::BATCH_SIZE)
        );

        if ($inputs === []) {
            return 0;
        }

        $backlogIds = array_map(
            static fn (EmbeddingInput $input): int => $input->backlogId,
            $inputs
        );

        try {
            $vectors = $this->embedderClient->embed(
                array_map(
                    static fn (EmbeddingInput $input): string => $input->content,
                    $inputs
                )
            );
            $resource->markEmbeddedByIds($backlogIds);
        } catch (Throwable $throwable) {
            $resource->markFailedByIds($backlogIds, self::EMBEDDER_ERROR_CATEGORY);
            throw $throwable;
        }

        return count($vectors);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<EmbeddingInput>
     */
    private function createEmbeddingInputs(array $rows): array
    {
        $inputs = [];

        foreach ($rows as $row) {
            $inputs[] = new EmbeddingInput(
                $this->toInteger($row[EmbeddingBacklogInterface::BACKLOG_ID] ?? null, 'backlog_id'),
                $this->toInteger($row[EmbeddingBacklogInterface::CHUNK_ID] ?? null, 'chunk_id'),
                $this->toString($row[DocumentInterface::SOURCE_ENTITY_TYPE] ?? null, 'source_entity_type'),
                $this->toInteger($row[DocumentInterface::SOURCE_ENTITY_ID] ?? null, 'source_entity_id'),
                $this->toInteger($row[DocumentInterface::STORE_ID] ?? null, 'store_id'),
                $this->toString($row[DocumentInterface::SOURCE_CODE] ?? null, 'source_code'),
                $this->toInteger($row[ChunkInterface::CHUNK_INDEX] ?? null, 'chunk_index'),
                $this->toString($row[ChunkInterface::CONTENT] ?? null, 'content'),
                $this->toString($row[ChunkInterface::CONTENT_HASH] ?? null, 'content_hash')
            );
        }

        return $inputs;
    }

    private function toInteger(mixed $value, string $field): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if ($integer === false || $integer < 0) {
            throw new UnexpectedValueException(sprintf('%s must be a non-negative integer.', $field));
        }

        return $integer;
    }

    private function toString(mixed $value, string $field): string
    {
        if (!is_string($value)) {
            throw new UnexpectedValueException(sprintf('%s must be a string.', $field));
        }

        return $value;
    }
}
