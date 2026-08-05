<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Search;

readonly class Candidates
{
    /**
     * @param array<int, float> $scoresByProductId
     */
    public function __construct(
        public array $scoresByProductId
    ) {
    }
}
