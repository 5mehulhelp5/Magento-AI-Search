<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Test\Stress\Support;

use JsonException;
use RuntimeException;

class Measurement
{
    private const string RESULT_FILE = '.phpunit.stress.cache/measurements.json';

    public function __construct(
        private readonly StressConfiguration $configuration
    ) {
    }

    public function resetRun(): void
    {
        $data = $this->read();
        $runs = $this->getRuns($data);
        unset($runs[$this->configuration->getRunLabel()]);
        $data['runs'] = $runs;
        $this->write($data);
    }

    /**
     * @param array<string, bool|float|int|string|null> $values
     */
    public function recordStage(string $stage, array $values): void
    {
        $data = $this->read();
        $runLabel = $this->configuration->getRunLabel();
        $runs = $this->getRuns($data);
        $run = $this->getRun($runs, $runLabel);
        $stages = $this->getSection($run, 'stages');
        $stages[$stage] = $values;
        $run['parameters'] = $this->getParameters();
        $run['stages'] = $stages;
        $runs[$runLabel] = $run;
        $data['runs'] = $runs;
        $this->write($data);
    }

    /**
     * @param array<string, bool|float|int|string|null> $values
     */
    public function appendCycle(string $stage, array $values): void
    {
        $data = $this->read();
        $runLabel = $this->configuration->getRunLabel();
        $runs = $this->getRuns($data);
        $run = $this->getRun($runs, $runLabel);
        $cycles = $this->getSection($run, 'cycles');
        $stageCycles = $cycles[$stage] ?? [];

        if (!is_array($stageCycles)) {
            throw new RuntimeException('The stress measurement cycle data is invalid.');
        }

        $values['cycle'] = count($stageCycles) + 1;
        $stageCycles[] = $values;
        $cycles[$stage] = $stageCycles;
        $run['parameters'] = $this->getParameters();
        $run['cycles'] = $cycles;
        $runs[$runLabel] = $run;
        $data['runs'] = $runs;
        $this->write($data);
    }

    /**
     * @param array<string, mixed>|list<array<string, mixed>> $values
     */
    public function recordDetails(string $name, array $values): void
    {
        $data = $this->read();
        $runLabel = $this->configuration->getRunLabel();
        $runs = $this->getRuns($data);
        $run = $this->getRun($runs, $runLabel);
        $details = $this->getSection($run, 'details');
        $details[$name] = $values;
        $run['parameters'] = $this->getParameters();
        $run['details'] = $details;
        $runs[$runLabel] = $run;
        $data['runs'] = $runs;
        $this->write($data);
    }

    /**
     * @return array<string, int|string>
     */
    private function getParameters(): array
    {
        return [
            'run_label' => $this->configuration->getRunLabel(),
            'dataset_type' => $this->configuration->usesStandaloneSimpleProducts()
                ? 'standalone_simple'
                : 'configurable',
            'configurable_products' => $this->configuration->getConfigurableProductCount(),
            'standalone_simple_products' => $this->configuration->usesStandaloneSimpleProducts()
                ? $this->configuration->getSimpleProductCount()
                : 0,
            'simple_products_per_configurable' =>
                $this->configuration->getSimpleProductsPerConfigurable(),
            'total_products' => $this->configuration->getTotalProductCount(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $path = $this->getPath();

        if (!is_file($path)) {
            return [
                'header' => [
                    'package' => 'davidbel/magento-ai-search by David Belicza',
                    'license' => 'SPDX-License-Identifier: MIT',
                    'repository' => 'https://github.com/DavidBelicza/Magento-AI-Search',
                ],
                'runs' => [],
            ];
        }

        $contents = file_get_contents($path);

        if (!is_string($contents)) {
            throw new RuntimeException('The stress measurement file could not be read.');
        }

        try {
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The stress measurement file is invalid.', 0, $exception);
        }

        if (!is_array($data)) {
            throw new RuntimeException('The stress measurement file is invalid.');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function getRuns(array $data): array
    {
        $runs = $data['runs'] ?? [];

        if (!is_array($runs)) {
            throw new RuntimeException('The stress measurement run data is invalid.');
        }

        /** @var array<string, mixed> $runs */
        return $runs;
    }

    /**
     * @param array<string, mixed> $runs
     * @return array<string, mixed>
     */
    private function getRun(array $runs, string $runLabel): array
    {
        $run = $runs[$runLabel] ?? [];

        if (!is_array($run)) {
            throw new RuntimeException('The stress measurement run is invalid.');
        }

        /** @var array<string, mixed> $run */
        return $run;
    }

    /**
     * @param array<string, mixed> $run
     * @return array<string, mixed>
     */
    private function getSection(array $run, string $name): array
    {
        $section = $run[$name] ?? [];

        if (!is_array($section)) {
            throw new RuntimeException('The stress measurement section is invalid.');
        }

        /** @var array<string, mixed> $section */
        return $section;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function write(array $data): void
    {
        $path = $this->getPath();
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('The stress measurement directory could not be created.');
        }

        try {
            $contents = json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
        } catch (JsonException $exception) {
            throw new RuntimeException('The stress measurements could not be encoded.', 0, $exception);
        }

        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException('The stress measurement file could not be written.');
        }
    }

    private function getPath(): string
    {
        return dirname(__DIR__, 3) . '/' . self::RESULT_FILE;
    }
}
