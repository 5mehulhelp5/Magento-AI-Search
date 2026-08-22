<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Client\Embedding\Base;

use UnexpectedValueException;

use function is_finite;

class ResponseValidator
{
    /**
     * @return array<array-key, mixed>
     */
    public function validateItems(mixed $items, int $inputCount): array
    {
        if (!is_array($items) || count($items) !== $inputCount) {
            throw new UnexpectedValueException(
                'Embedding response contains an unexpected item count.'
            );
        }

        return $items;
    }

    /**
     * @return list<mixed>
     */
    public function validateOrderedItems(mixed $items, int $inputCount): array
    {
        $items = $this->validateItems($items, $inputCount);

        if (!array_is_list($items)) {
            throw new UnexpectedValueException(
                'Embedding response contains an unexpected item count.'
            );
        }

        return $items;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function validateItem(mixed $item): array
    {
        if (!is_array($item)) {
            throw new UnexpectedValueException('Embedding response item must be an object.');
        }

        return $item;
    }

    /**
     * @return list<float>
     */
    public function validateVector(mixed $embedding, int $vectorDimensions): array
    {
        if (!is_array($embedding) || !array_is_list($embedding)) {
            throw new UnexpectedValueException('Embedding response vector must be a list.');
        }

        if (count($embedding) !== $vectorDimensions) {
            throw new UnexpectedValueException(
                'Embedding response contains an invalid vector dimension.'
            );
        }

        $vector = [];

        foreach ($embedding as $value) {
            $vector[] = $this->validateVectorValue($value);
        }

        return $vector;
    }

    private function validateVectorValue(mixed $value): float
    {
        if (!is_int($value) && !is_float($value)) {
            throw new UnexpectedValueException('Embedding vector must contain only numbers.');
        }

        $floatValue = (float) $value;

        if (!is_finite($floatValue)) {
            throw new UnexpectedValueException(
                'Embedding vector must contain only finite numbers.'
            );
        }

        return $floatValue;
    }
}
