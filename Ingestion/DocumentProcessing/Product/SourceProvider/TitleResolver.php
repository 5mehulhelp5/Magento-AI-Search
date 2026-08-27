<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\SourceProvider;

class TitleResolver
{
    private const int DEFAULT_STORE_ID = 0;

    /**
     * @param array<string, string> $values
     */
    public function getTitle(array $values, int $productId, int $storeId): ?string
    {
        $title = $values[$this->getValueKey($productId, $storeId)]
            ?? $values[$this->getValueKey($productId, self::DEFAULT_STORE_ID)]
            ?? null;

        if ($title === null || trim($title) === '') {
            return null;
        }

        return $title;
    }

    private function getValueKey(int $productId, int $storeId): string
    {
        return $productId . ':' . $storeId;
    }
}
