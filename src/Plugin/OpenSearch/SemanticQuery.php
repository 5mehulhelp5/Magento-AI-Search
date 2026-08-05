<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Plugin\OpenSearch;

use DavidBel\AiSearch\Search\QuickSearch;
use Magento\Framework\Search\RequestInterface;
use Magento\OpenSearch\SearchAdapter\Mapper;
use Psr\Log\LoggerInterface;
use Throwable;

class SemanticQuery
{
    public function __construct(
        private readonly QuickSearch $quickSearch,
        private readonly LoggerInterface $logger
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
            $this->logger->error(
                'Semantic catalog search failed. Magento full-text search will be used.',
                ['exception' => $exception]
            );

            return $query;
        }
    }
}
