<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Ui\Component\Listing\Column\Backlog;

use DavidBel\AiSearch\Model\EmbeddingBacklog\Operation;
use Magento\Framework\Data\OptionSourceInterface;

class OperationOptions implements OptionSourceInterface
{
    /**
     * @return list<array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        $options = [];

        foreach (Operation::cases() as $operation) {
            $options[] = [
                'value' => $operation->value,
                'label' => __($operation->name),
            ];
        }

        return $options;
    }
}
