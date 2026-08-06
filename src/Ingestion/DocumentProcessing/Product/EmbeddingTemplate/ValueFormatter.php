<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing\Product\EmbeddingTemplate;

class ValueFormatter
{
    /**
     * @param list<string> $values
     */
    public function format(array $values): string
    {
        $uniqueValues = [];

        foreach ($values as $value) {
            $value = trim($value);

            if ($value === '' || in_array($value, $uniqueValues, true)) {
                continue;
            }

            $uniqueValues[] = $value;
        }

        $valueCount = count($uniqueValues);

        if ($valueCount === 0) {
            return '';
        }

        if ($valueCount === 1) {
            return $uniqueValues[0];
        }

        $lastValue = $uniqueValues[$valueCount - 1];
        unset($uniqueValues[$valueCount - 1]);
        $separator = $valueCount === 2 ? ' and ' : ', and ';

        return implode(', ', $uniqueValues) . $separator . $lastValue;
    }
}
