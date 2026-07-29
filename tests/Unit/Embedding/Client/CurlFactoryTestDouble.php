<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\Embedding\Client;

use Magento\Framework\HTTP\Client\Curl;

readonly class CurlFactoryTestDouble
{
    public function create(): Curl
    {
        return new Curl();
    }
}
