<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Plugin\OpenSearch;

use DavidBel\AiSearch\Log\Logger;
use DavidBel\AiSearch\Search\QuickSearch;
use Magento\Framework\Search\RequestInterface;
use Magento\OpenSearch\SearchAdapter\Mapper;
use Throwable;

class SemanticQuery
{
    public function __construct(
        private readonly QuickSearch $quickSearch,
        private readonly Logger $logger
    ) {
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function afterBuildQuery(
        Mapper $subject,
        array $query,
        RequestInterface $request
    ): array {
        try {
            return $this->quickSearch->execute($request, $query);
        } catch (Throwable $exception) {
            $this->logger->semanticSearchFailed($exception);

            return $query;
        }
    }
}
