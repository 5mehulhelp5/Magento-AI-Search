<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\TestDouble;

use stdClass;

use function class_alias;
use function class_exists;

/**
 * Makes Magento factory types available to PHPUnit before Magento code generation.
 */
readonly class GeneratedFactoryStub
{
    public static function register(string ...$factoryClasses): void
    {
        foreach ($factoryClasses as $factoryClass) {
            if (class_exists($factoryClass, false)) {
                continue;
            }

            class_alias(self::class, $factoryClass);
        }
    }

    public function create(): object
    {
        return new stdClass();
    }
}
