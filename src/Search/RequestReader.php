<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Search;

use Magento\Framework\Search\Request\Query\BoolExpression;
use Magento\Framework\Search\Request\Query\Filter;
use Magento\Framework\Search\Request\Query\MatchQuery;
use Magento\Framework\Search\Request\QueryInterface;
use Magento\Framework\Search\Request\Dimension;
use Magento\Framework\Search\RequestInterface;
use UnexpectedValueException;

class RequestReader
{
    private const string QUICK_SEARCH_REQUEST = 'quick_search_container';
    private const string SEARCH_QUERY = 'search';
    private const string STORE_DIMENSION = 'scope';

    public function isQuickSearch(RequestInterface $request): bool
    {
        return $request->getName() === self::QUICK_SEARCH_REQUEST;
    }

    public function getQueryText(RequestInterface $request): string
    {
        return $this->findQueryText($request->getQuery()) ?? '';
    }

    public function getStoreId(RequestInterface $request): int
    {
        return $this->getPositiveStoreId(
            $this->getStoreDimension($request)->getValue()
        );
    }

    private function getStoreDimension(RequestInterface $request): Dimension
    {
        foreach ($request->getDimensions() as $dimension) {
            if ($dimension->getName() === self::STORE_DIMENSION) {
                return $dimension;
            }
        }

        throw new UnexpectedValueException('Quick search request does not contain a valid store scope.');
    }

    private function getPositiveStoreId(mixed $storeId): int
    {
        if (is_string($storeId) && ctype_digit($storeId)) {
            $storeId = (int) $storeId;
        }

        if (!is_int($storeId) || $storeId < 1) {
            throw new UnexpectedValueException('Quick search request does not contain a valid store scope.');
        }

        return $storeId;
    }

    private function findQueryText(QueryInterface $query): ?string
    {
        if ($query instanceof MatchQuery && $query->getName() === self::SEARCH_QUERY) {
            return trim($query->getValue());
        }

        if ($query instanceof BoolExpression) {
            return $this->findQueryTextInList(
                array_values(array_merge($query->getShould(), $query->getMust(), $query->getMustNot()))
            );
        }

        if ($query instanceof Filter && $query->getReferenceType() === Filter::REFERENCE_QUERY) {
            $reference = $query->getReference();

            if ($reference instanceof QueryInterface) {
                return $this->findQueryText($reference);
            }
        }

        return null;
    }

    /**
     * @param list<QueryInterface> $queries
     */
    private function findQueryTextInList(array $queries): ?string
    {
        foreach ($queries as $query) {
            $queryText = $this->findQueryText($query);

            if ($queryText !== null) {
                return $queryText;
            }
        }

        return null;
    }
}
