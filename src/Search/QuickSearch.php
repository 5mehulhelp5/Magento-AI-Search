<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Search;

use Magento\Framework\Search\RequestInterface;

class QuickSearch
{
    public function __construct(
        private readonly RequestReader $requestReader,
        private readonly QueryEmbedding $queryEmbedding,
        private readonly VectorSearch $vectorSearch,
        private readonly CatalogQueryModifier $catalogQueryModifier
    ) {
    }

    /**
     * @param array<string, mixed> $catalogQuery
     * @return array<string, mixed>
     */
    public function execute(RequestInterface $request, array $catalogQuery): array
    {
        if (!$this->requestReader->isQuickSearch($request)) {
            return $catalogQuery;
        }

        $queryText = $this->requestReader->getQueryText($request);

        if ($queryText === '') {
            return $catalogQuery;
        }

        $vector = $this->queryEmbedding->execute($queryText);
        $candidates = $this->vectorSearch->execute(
            $vector,
            $this->requestReader->getStoreId($request)
        );

        return $this->catalogQueryModifier->execute($catalogQuery, $candidates);
    }
}
