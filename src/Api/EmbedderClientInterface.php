<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Api;

use GuzzleHttp\Promise\PromiseInterface;

interface EmbedderClientInterface
{
    /**
     * Start generating an embedding vector for every document input.
     *
     * @param list<\DavidBel\AiSearch\Client\Embedding\EmbeddingInput> $inputs
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function embedDocumentsAsync(array $inputs): PromiseInterface;

    /**
     * Start generating an embedding vector for a search query.
     *
     * @param string $queryText
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function embedQueryAsync(string $queryText): PromiseInterface;
}
