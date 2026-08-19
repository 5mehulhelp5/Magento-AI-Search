<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Config\EmbeddedAttributesConfig;

use DavidBel\AiSearch\Config\EmbeddedAttribute;
use InvalidArgumentException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface;
use UnexpectedValueException;

class DynamicDocument
{
    private const string XML_PATH_ENABLED =
        'davidbel_ai_search_search_source/document_configuration/enable_dynamic_document';
    private const string XML_PATH_CONFIGURATION =
        'davidbel_ai_search_search_source/document_configuration/dynamic_document';

    /**
     * @var array<string, EmbeddedAttribute|null>
     */
    private array $documents = [];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly SerializerInterface $serializer
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        $this->validateStoreId($storeId);

        if ($storeId === null) {
            return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED);
        }

        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function get(?int $storeId = null): ?EmbeddedAttribute
    {
        $this->validateStoreId($storeId);
        $cacheKey = $storeId === null ? 'default' : 'store_' . $storeId;

        if (array_key_exists($cacheKey, $this->documents)) {
            return $this->documents[$cacheKey];
        }

        if (!$this->isEnabled($storeId)) {
            $this->documents[$cacheKey] = null;

            return null;
        }

        $this->documents[$cacheKey] = new EmbeddedAttribute(
            attributeCode: 'embedding_template',
            composite: false,
            parsingStrategy: 'text_as_is',
            template: null,
            children: $this->getChildren($storeId)
        );

        return $this->documents[$cacheKey];
    }

    /**
     * @return list<EmbeddedAttribute>
     */
    private function getChildren(?int $storeId): array
    {
        $children = [];

        foreach ($this->getConfiguredRows($storeId) as $rowKey => $configuredRow) {
            if ($rowKey === '__empty') {
                continue;
            }

            if (!is_array($configuredRow)) {
                throw new UnexpectedValueException('A Dynamic Document configuration row must be an array.');
            }

            $children[] = new EmbeddedAttribute(
                attributeCode: implode(
                    ',',
                    $this->getAttributeCodes($configuredRow['attribute_code'] ?? null)
                ),
                composite: $this->getComposite($configuredRow['composite'] ?? null),
                parsingStrategy: $this->getNonEmptyString(
                    $configuredRow['parsing_strategy'] ?? null,
                    'parsing_strategy'
                ),
                template: $this->getNonEmptyString(
                    $configuredRow['template'] ?? null,
                    'template'
                ),
                children: null
            );
        }

        if ($children === []) {
            throw new UnexpectedValueException(
                'An enabled Dynamic Document must contain at least one configured part.'
            );
        }

        return $children;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function getConfiguredRows(?int $storeId): array
    {
        $value = $storeId === null
            ? $this->scopeConfig->getValue(self::XML_PATH_CONFIGURATION)
            : $this->scopeConfig->getValue(
                self::XML_PATH_CONFIGURATION,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );

        if (!is_string($value) || trim($value) === '') {
            throw new UnexpectedValueException(
                sprintf(
                    'Configuration path "%s" must contain a serialized Dynamic Document.',
                    self::XML_PATH_CONFIGURATION
                )
            );
        }

        try {
            $configuredRows = $this->serializer->unserialize($value);
        } catch (InvalidArgumentException $exception) {
            throw new UnexpectedValueException(
                sprintf(
                    'Configuration path "%s" does not contain a valid serialized Dynamic Document.',
                    self::XML_PATH_CONFIGURATION
                ),
                0,
                $exception
            );
        }

        if (!is_array($configuredRows)) {
            throw new UnexpectedValueException(
                sprintf(
                    'Configuration path "%s" must contain a Dynamic Document array.',
                    self::XML_PATH_CONFIGURATION
                )
            );
        }

        return $configuredRows;
    }

    /**
     * @return list<string>
     */
    private function getAttributeCodes(mixed $value): array
    {
        $configuredCodes = is_string($value) ? explode(',', $value) : $value;

        if (!is_array($configuredCodes)) {
            throw new UnexpectedValueException(
                'A Dynamic Document part must contain one or more product attributes.'
            );
        }

        $attributeCodes = [];

        foreach ($configuredCodes as $configuredCode) {
            if (!is_string($configuredCode) || trim($configuredCode) === '') {
                throw new UnexpectedValueException(
                    'A Dynamic Document product attribute code must be a non-empty string.'
                );
            }

            $attributeCode = trim($configuredCode);
            $attributeCodes[$attributeCode] = $attributeCode;
        }

        if ($attributeCodes === []) {
            throw new UnexpectedValueException(
                'A Dynamic Document part must contain one or more product attributes.'
            );
        }

        return array_values($attributeCodes);
    }

    private function getComposite(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        if ($value === false || $value === 0 || $value === '0') {
            return false;
        }

        throw new UnexpectedValueException(
            'A Dynamic Document composition value must be either zero or one.'
        );
    }

    private function getNonEmptyString(mixed $value, string $field): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new UnexpectedValueException(
                sprintf('Dynamic Document field "%s" must contain a non-empty string.', $field)
            );
        }

        return trim($value);
    }

    private function validateStoreId(?int $storeId): void
    {
        if ($storeId !== null && $storeId < 1) {
            throw new UnexpectedValueException('A Dynamic Document store ID must be positive.');
        }
    }
}
