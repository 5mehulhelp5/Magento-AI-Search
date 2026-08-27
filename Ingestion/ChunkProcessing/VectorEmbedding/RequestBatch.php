<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\ChunkProcessing\VectorEmbedding;

use DavidBel\AiSearch\Client\Embedding\Base\EmbeddingInput;
use DavidBel\AiSearch\Ingestion\ChunkProcessing\ProcessingBatch;

class RequestBatch
{
    /**
     * @var list<EmbeddingInput>
     */
    private readonly array $uniqueInputs;

    /**
     * @var list<int>
     */
    private readonly array $uniqueInputIndexes;

    public function __construct(ProcessingBatch $processingBatch)
    {
        $uniqueInputs = [];
        $uniqueInputIndexes = [];
        $inputIndexByTitleAndText = [];

        foreach ($processingBatch->getItems() as $item) {
            $titleKey = $item->title === null ? 'null' : 'title:' . $item->title;
            $inputIndex = $inputIndexByTitleAndText[$titleKey][$item->content] ?? null;

            if ($inputIndex === null) {
                $inputIndex = count($uniqueInputs);
                $inputIndexByTitleAndText[$titleKey][$item->content] = $inputIndex;
                $uniqueInputs[] = new EmbeddingInput(
                    title: $item->title,
                    text: $item->content
                );
            }

            $uniqueInputIndexes[] = $inputIndex;
        }

        $this->uniqueInputs = $uniqueInputs;
        $this->uniqueInputIndexes = $uniqueInputIndexes;
    }

    /**
     * @return list<EmbeddingInput>
     */
    public function getUniqueInputs(): array
    {
        return $this->uniqueInputs;
    }

    /**
     * @param list<list<float>> $vectors
     * @return list<list<float>>
     */
    public function expandVectors(array $vectors): array
    {
        $expandedVectors = [];

        foreach ($this->uniqueInputIndexes as $inputIndex) {
            $expandedVectors[] = $vectors[$inputIndex];
        }

        return $expandedVectors;
    }
}
