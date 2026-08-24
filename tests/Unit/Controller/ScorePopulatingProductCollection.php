<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Controller;

use DavidBel\AiSearch\Controller\Adminhtml\Search\TestSemanticSearch\ResultProvider\SearchScores;
use Magento\Catalog\Model\ResourceModel\Product\Collection;

class ScorePopulatingProductCollection extends Collection
{
    /**
     * @param array<array-key, \Magento\Catalog\Model\Product> $products
     * @param array<int, float> $productScores
     * @param array<int, float> $chunkScores
     * @phpstan-ignore constructor.missingParentCall
     */
    public function __construct(
        private readonly SearchScores $searchScores,
        private readonly array $products,
        private readonly array $productScores,
        private readonly array $chunkScores
    ) {
    }

    public function setPageSize(mixed $size): static
    {
        return $this;
    }

    public function setCurPage(mixed $page): static
    {
        return $this;
    }

    /**
     * The boolean flags are required by the inherited Magento collection API.
     *
     * @SuppressWarnings("PHPMD.BooleanArgumentFlag")
     */
    public function load(mixed $printQuery = false, mixed $logQuery = false): static
    {
        $this->searchScores->scoresByProductId = $this->productScores;
        $this->searchScores->scoresByChunkId = $this->chunkScores;

        return $this;
    }

    /**
     * @return array<array-key, \Magento\Catalog\Model\Product>
     */
    public function getItems(): array
    {
        return $this->products;
    }

    public function getSize(): int
    {
        return count($this->products);
    }
}
