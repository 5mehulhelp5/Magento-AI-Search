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
     * Start generating an embedding vector for every input text.
     *
     * @param list<string> $inputs
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function embedAsync(array $inputs): PromiseInterface;
}
