<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Search;

use DavidBel\AiSearch\Api\EmbedderClientInterface;
use UnexpectedValueException;

class QueryEmbedding
{
    public function __construct(
        private readonly EmbedderClientInterface $embedderClient
    ) {
    }

    /**
     * @return list<float>
     */
    public function execute(string $queryText): array
    {
        $vectors = $this->embedderClient->embedQueryAsync($queryText)->wait();

        if (!is_array($vectors) || count($vectors) !== 1) {
            throw new UnexpectedValueException('Query embedding returned an unexpected vector count.');
        }

        $vector = reset($vectors);

        if (!is_array($vector) || !array_is_list($vector)) {
            throw new UnexpectedValueException('Query embedding returned an invalid vector.');
        }

        $validatedVector = [];

        foreach ($vector as $value) {
            if (!is_float($value)) {
                throw new UnexpectedValueException('Query embedding returned an invalid vector value.');
            }

            $validatedVector[] = $value;
        }

        return $validatedVector;
    }
}
