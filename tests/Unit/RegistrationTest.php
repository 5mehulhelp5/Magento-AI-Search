<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit;

use Magento\Framework\Component\ComponentRegistrar;
use PHPUnit\Framework\TestCase;

class RegistrationTest extends TestCase
{
    public function testModuleIsRegisteredFromSourceDirectory(): void
    {
        $registrar = new ComponentRegistrar();
        $modulePath = $registrar->getPath(ComponentRegistrar::MODULE, 'DavidBel_AiSearch');

        self::assertNotNull($modulePath);
        self::assertSame(realpath(__DIR__ . '/../../src'), realpath($modulePath));
    }
}
