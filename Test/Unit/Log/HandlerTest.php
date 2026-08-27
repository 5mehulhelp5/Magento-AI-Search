<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Unit\Log;

use DavidBel\AiSearch\Log\Handler;
use Magento\Framework\Filesystem\DriverInterface;
use Monolog\Formatter\LineFormatter;
use PHPUnit\Framework\TestCase;

use function define;
use function defined;

class HandlerTest extends TestCase
{
    public function testUsesLineFormatterThatPreservesMultilineExceptions(): void
    {
        if (!defined('BP')) {
            define('BP', '/tmp');
        }

        $handler = new Handler(self::createStub(DriverInterface::class));

        self::assertInstanceOf(LineFormatter::class, $handler->getFormatter());
    }
}
