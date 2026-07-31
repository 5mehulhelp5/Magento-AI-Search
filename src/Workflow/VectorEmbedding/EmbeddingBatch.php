<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Workflow\VectorEmbedding;

use InvalidArgumentException;

class EmbeddingBatch
{
    /**
     * @var non-empty-list<EmbeddingInput>
     */
    private readonly array $inputs;

    /**
     * @param list<EmbeddingInput> $inputs
     */
    public function __construct(array $inputs)
    {
        if ($inputs === []) {
            throw new InvalidArgumentException('An embedding batch must contain at least one input.');
        }

        $this->inputs = $inputs;
    }

    /**
     * @return list<int>
     */
    public function getBacklogIds(): array
    {
        return array_map(
            static fn (EmbeddingInput $input): int => $input->backlogId,
            $this->inputs
        );
    }

    /**
     * @return list<string>
     */
    public function getContents(): array
    {
        return array_map(
            static fn (EmbeddingInput $input): string => $input->content,
            $this->inputs
        );
    }

    public function getLastInput(): EmbeddingInput
    {
        return $this->inputs[array_key_last($this->inputs)];
    }

    /**
     * @return non-empty-list<EmbeddingInput>
     */
    public function getInputs(): array
    {
        return $this->inputs;
    }
}
