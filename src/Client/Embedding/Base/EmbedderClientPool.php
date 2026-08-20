<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Client\Embedding\Base;

use DavidBel\AiSearch\Config\EmbedderConfig;
use UnexpectedValueException;

class EmbedderClientPool
{
    /**
     * @param array<string, EmbedderClientInterface> $clients
     */
    public function __construct(
        private readonly EmbedderConfig $embedderConfig,
        private readonly array $clients
    ) {
    }

    public function getClient(): EmbedderClientInterface
    {
        $protocol = $this->embedderConfig->getEmbeddingApiProtocol();
        $client = $this->clients[$protocol] ?? null;

        if (!$client instanceof EmbedderClientInterface) {
            throw new UnexpectedValueException(
                sprintf('Embedding API protocol "%s" is not supported.', $protocol)
            );
        }

        return $client;
    }
}
