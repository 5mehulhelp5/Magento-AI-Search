<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Api;

interface EmbedderClientInterface
{
    /**
     * Generate an embedding vector for every input text.
     *
     * @param list<string> $inputs
     * @return list<list<float>>
     */
    public function embed(array $inputs): array;
}
