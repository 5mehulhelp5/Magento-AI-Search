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
use UnexpectedValueException;

class Documents
{
    private const string XML_PATH =
        'davidbel_ai_search_semantic_search_source/document_configuration/documents';

    /**
     * @var list<EmbeddedAttribute>|null
     */
    private ?array $documents = null;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * @return list<EmbeddedAttribute>
     */
    public function get(): array
    {
        if ($this->documents !== null) {
            return $this->documents;
        }

        $documents = [];
        $documentCodes = [];

        foreach ($this->getConfiguredRows() as $rowKey => $configuredRow) {
            if ($rowKey === '__empty') {
                continue;
            }

            $document = $this->getDocument($configuredRow);

            if (isset($documentCodes[$document->attributeCode])) {
                throw new UnexpectedValueException(
                    sprintf(
                        'Document attribute code "%s" is configured more than once.',
                        $document->attributeCode
                    )
                );
            }

            $documents[] = $document;
            $documentCodes[$document->attributeCode] = true;
        }

        $this->documents = $documents;

        return $this->documents;
    }

    private function getDocument(mixed $configuredRow): EmbeddedAttribute
    {
        if (!is_array($configuredRow)) {
            throw new UnexpectedValueException('A Document configuration row must be an array.');
        }

        return new EmbeddedAttribute(
            attributeCode: $this->getNonEmptyString(
                $configuredRow['attribute_code'] ?? null,
                'attribute_code'
            ),
            composite: $this->getComposite($configuredRow['composite'] ?? null),
            parsingStrategy: $this->getNonEmptyString(
                $configuredRow['parsing_strategy'] ?? null,
                'parsing_strategy'
            ),
            template: null,
            children: null
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    private function getConfiguredRows(): array
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH);

        if ($value === null || $value === '') {
            return [];
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException(
                sprintf('Configuration path "%s" must contain serialized Documents.', self::XML_PATH)
            );
        }

        try {
            $configuredRows = $this->serializer->unserialize($value);
        } catch (InvalidArgumentException $exception) {
            throw new UnexpectedValueException(
                sprintf('Configuration path "%s" does not contain valid serialized Documents.', self::XML_PATH),
                0,
                $exception
            );
        }

        if (!is_array($configuredRows)) {
            throw new UnexpectedValueException(
                sprintf('Configuration path "%s" must contain a Documents array.', self::XML_PATH)
            );
        }

        return $configuredRows;
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
            'A Document composition value must be either zero or one.'
        );
    }

    private function getNonEmptyString(mixed $value, string $field): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new UnexpectedValueException(
                sprintf('Document field "%s" must contain a non-empty string.', $field)
            );
        }

        return trim($value);
    }
}
