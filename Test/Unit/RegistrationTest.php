<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit;

use LogicException;
use Magento\Framework\Component\ComponentRegistrar;
use PHPUnit\Framework\TestCase;

class RegistrationTest extends TestCase
{
    public function testModuleIsRegisteredFromPackageRoot(): void
    {
        $registrar = new ComponentRegistrar();
        $modulePath = $registrar->getPath(ComponentRegistrar::MODULE, 'DavidBel_AiSearch');

        self::assertNotNull($modulePath);
        self::assertSame(realpath(dirname(__DIR__, 2)), realpath($modulePath));
    }

    public function testRegistersMagentoModule(): void
    {
        try {
            require dirname(__DIR__, 2) . '/registration.php';
            self::fail('The Composer bootstrap should already register the module.');
        } catch (LogicException $exception) {
            self::assertStringContainsString(
                "Module 'DavidBel_AiSearch'",
                $exception->getMessage()
            );
        }
    }
}
