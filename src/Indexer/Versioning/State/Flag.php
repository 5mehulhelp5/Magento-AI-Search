<?php
/**
 * davidbel/ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Indexer\Versioning\State;

use DavidBel\AiSearch\Indexer\Versioning\State;
use Magento\Framework\FlagManager;
use UnexpectedValueException;

class Flag
{
    private const string FLAG_CODE = 'davidbel_ai_search_index_version';

    public function __construct(
        private readonly FlagManager $flagManager,
        private readonly Mapper $stateMapper
    ) {
    }

    public function get(): State
    {
        $data = $this->flagManager->getFlagData(self::FLAG_CODE);

        if ($data === null) {
            return new State();
        }

        if (!is_array($data)) {
            throw new UnexpectedValueException('The stored search index version state is invalid.');
        }

        return $this->stateMapper->map($data);
    }

    public function save(State $state): void
    {
        $this->flagManager->saveFlag(self::FLAG_CODE, $state->toArray());
    }
}
