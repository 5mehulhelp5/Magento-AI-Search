<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Stress;

use Magento\Framework\App\Area;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use RuntimeException;

class MagentoEnvironment
{
    private static ?ObjectManagerInterface $objectManager = null;

    public static function initialize(): void
    {
        if (getenv('AI_SEARCH_STRESS_TEST') !== '1') {
            throw new RuntimeException(
                'Set AI_SEARCH_STRESS_TEST=1 to confirm that this destructive test may use the local Magento database.'
            );
        }

        $magentoRoot = self::getMagentoRoot();
        $bootstrapFile = $magentoRoot . '/app/bootstrap.php';

        if (!is_file($bootstrapFile)) {
            throw new RuntimeException(sprintf('Magento bootstrap was not found at "%s".', $bootstrapFile));
        }

        require_once $bootstrapFile;

        $bootstrap = Bootstrap::create($magentoRoot, $_SERVER);
        self::$objectManager = $bootstrap->getObjectManager();
        self::initializeAreaCode(self::$objectManager);
    }

    public static function getObjectManager(): ObjectManagerInterface
    {
        if (self::$objectManager === null) {
            throw new RuntimeException('The Magento stress-test environment has not been initialized.');
        }

        return self::$objectManager;
    }

    public static function getMagentoRoot(): string
    {
        $configuredRoot = getenv('MAGENTO_ROOT');

        if (is_string($configuredRoot) && $configuredRoot !== '') {
            $resolvedRoot = realpath($configuredRoot);

            if ($resolvedRoot === false) {
                throw new RuntimeException('The configured Magento root directory could not be resolved.');
            }

            return $resolvedRoot;
        }

        $resolvedRoot = realpath(__DIR__ . '/../../../..');

        if ($resolvedRoot === false) {
            throw new RuntimeException('The Magento root directory could not be resolved.');
        }

        return $resolvedRoot;
    }

    private static function initializeAreaCode(ObjectManagerInterface $objectManager): void
    {
        /** @var State $state */
        $state = $objectManager->get(State::class);

        try {
            $state->getAreaCode();
        } catch (LocalizedException) {
            $state->setAreaCode(Area::AREA_ADMINHTML);
        }
    }
}
