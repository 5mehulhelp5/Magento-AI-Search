<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Tests\Unit\TestDouble;

use Magento\Framework\ObjectManagerInterface;
use RuntimeException;

class ObjectManagerStub implements ObjectManagerInterface
{
    /**
     * @param array<class-string, object> $instances
     */
    public function __construct(
        private readonly array $instances
    ) {
    }

    /**
     * @param string $type
     * @param array<array-key, mixed> $arguments
     */
    public function create(mixed $type, array $arguments = []): object
    {
        /** @var class-string $type */
        return $this->get($type);
    }

    /** @inheritDoc */
    public function get($type): object
    {
        /** @var class-string $type */
        return $this->instances[$type]
            ?? throw new RuntimeException(sprintf('Unexpected ObjectManager request for %s.', $type));
    }

    /**
     * @param array<array-key, mixed> $configuration
     */
    public function configure(array $configuration): void
    {
        // No runtime configuration is needed by this test double.
    }
}
