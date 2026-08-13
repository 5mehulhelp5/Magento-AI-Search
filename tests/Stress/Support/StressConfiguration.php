<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Stress\Support;

use UnexpectedValueException;

class StressConfiguration
{
    private const int DEFAULT_CONFIGURABLE_PRODUCT_COUNT = 100;
    private const int SIMPLE_PRODUCTS_PER_CONFIGURABLE = 9;
    private const string DEFAULT_RUN_LABEL = 'scheduler_100x9';

    public function getConfigurableProductCount(): int
    {
        return $this->getPositiveInteger(
            'AI_SEARCH_STRESS_CONFIGURABLES',
            self::DEFAULT_CONFIGURABLE_PRODUCT_COUNT
        );
    }

    public function getSimpleProductsPerConfigurable(): int
    {
        return self::SIMPLE_PRODUCTS_PER_CONFIGURABLE;
    }

    public function getTotalProductCount(): int
    {
        return $this->getConfigurableProductCount()
            * (self::SIMPLE_PRODUCTS_PER_CONFIGURABLE + 1);
    }

    public function getRunLabel(): string
    {
        $value = getenv('AI_SEARCH_STRESS_RUN_LABEL');

        if ($value === false || trim($value) === '') {
            return self::DEFAULT_RUN_LABEL;
        }

        $label = trim($value);

        if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $label) !== 1) {
            throw new UnexpectedValueException(
                'AI_SEARCH_STRESS_RUN_LABEL must contain only lowercase letters, numbers, underscores, or hyphens.'
            );
        }

        return $label;
    }

    private function getPositiveInteger(string $name, int $default): int
    {
        $value = getenv($name);

        if ($value === false || $value === '') {
            return $default;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if (!is_int($integer) || $integer < 1) {
            throw new UnexpectedValueException(sprintf('%s must be a positive integer.', $name));
        }

        return $integer;
    }
}
