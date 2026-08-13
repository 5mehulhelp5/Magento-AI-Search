<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Stress;

use PHPUnit\Framework\TestCase;

abstract class StressTestCase extends TestCase
{
    /**
     * @template T of object
     * @param class-string<T> $type
     * @return T
     */
    protected function create(string $type): object
    {
        $instance = MagentoEnvironment::getObjectManager()->create($type);

        if (!$instance instanceof $type) {
            self::fail(sprintf('Magento did not create an instance of "%s".', $type));
        }

        return $instance;
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return T
     */
    protected function get(string $type): object
    {
        $instance = MagentoEnvironment::getObjectManager()->get($type);

        if (!$instance instanceof $type) {
            self::fail(sprintf('Magento did not return an instance of "%s".', $type));
        }

        return $instance;
    }
}
