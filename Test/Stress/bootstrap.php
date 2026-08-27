<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

use DavidBel\AiSearch\Test\Stress\MagentoEnvironment;

$magentoRoot = realpath(__DIR__ . '/../../../..');

if ($magentoRoot === false) {
    throw new RuntimeException('The Magento root directory could not be resolved.');
}

/** @var \Composer\Autoload\ClassLoader $autoloader */
$autoloader = require $magentoRoot . '/vendor/autoload.php';
$autoloader->addPsr4('DavidBel\\AiSearch\\Test\\', dirname(__DIR__) . '/');

MagentoEnvironment::initialize();
