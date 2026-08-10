<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ingestion\DocumentProcessing;

use DavidBel\AiSearch\Ingestion\DocumentProcessing\Parsing\ParsingInterface;
use InvalidArgumentException;

class Parsing
{
    /**
     * @var array<string, ParsingInterface>
     */
    private readonly array $parsingStrategies;

    /**
     * @param list<ParsingInterface> $parsingStrategies
     */
    public function __construct(array $parsingStrategies)
    {
        if ($parsingStrategies === []) {
            throw new InvalidArgumentException('At least one parsing strategy is required.');
        }

        $strategiesByCode = [];

        foreach ($parsingStrategies as $parsingStrategy) {
            $code = $parsingStrategy->getCode();

            if (trim($code) === '') {
                throw new InvalidArgumentException('A parsing strategy code cannot be empty.');
            }

            if (isset($strategiesByCode[$code])) {
                throw new InvalidArgumentException(
                    sprintf('Parsing strategy code "%s" is configured more than once.', $code)
                );
            }

            $strategiesByCode[$code] = $parsingStrategy;
        }

        $this->parsingStrategies = $strategiesByCode;
    }

    public function parse(string $text, string $strategy): string
    {
        $parsingStrategy = $this->parsingStrategies[$strategy] ?? null;

        if ($parsingStrategy === null) {
            throw new InvalidArgumentException(
                sprintf('Parsing strategy "%s" is not configured.', $strategy)
            );
        }

        return $parsingStrategy->parse($text);
    }
}
