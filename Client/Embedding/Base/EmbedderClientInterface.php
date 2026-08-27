<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Client\Embedding\Base;

use DavidBel\AiSearch\Indexer\Versioning\PhysicalIndex\QueryConfigurationSnapshot;
use GuzzleHttp\Promise\PromiseInterface;

interface EmbedderClientInterface
{
    /**
     * @param list<EmbeddingInput> $inputs
     */
    public function embedDocumentsAsync(array $inputs): PromiseInterface;

    public function embedQueryAsync(
        string $queryText,
        int $requestTimeoutSeconds,
        QueryConfigurationSnapshot $configurationSnapshot
    ): PromiseInterface;
}
