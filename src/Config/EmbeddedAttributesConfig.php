<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Config;

use DavidBel\AiSearch\Config\EmbeddedAttributesConfig\DynamicDocument;
use DavidBel\AiSearch\Config\EmbeddedAttributesConfig\Documents;
use Magento\Framework\App\Config\ScopeConfigInterface;
use UnexpectedValueException;

class EmbeddedAttributesConfig
{
    private const string XML_PATH_ENABLE_DOCUMENT_TITLE =
        'davidbel_ai_search_search_source/document_configuration/enable_document_title';
    private const string XML_PATH_DOCUMENT_TITLE =
        'davidbel_ai_search_search_source/document_configuration/document_title';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Documents $documents,
        private readonly DynamicDocument $dynamicDocument
    ) {
    }

    public function isDocumentTitleEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLE_DOCUMENT_TITLE);
    }

    public function getDocumentTitleAttributeCode(): string
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_DOCUMENT_TITLE);

        if (!is_string($value)) {
            throw new UnexpectedValueException(
                sprintf(
                    'Configuration path "%s" must contain a string.',
                    self::XML_PATH_DOCUMENT_TITLE
                )
            );
        }

        return $value;
    }

    public function isDynamicDocumentEnabled(?int $storeId = null): bool
    {
        return $this->dynamicDocument->isEnabled($storeId);
    }

    /**
     * @return list<EmbeddedAttribute>
     */
    public function getAttributes(?int $storeId = null): array
    {
        $attributes = $this->documents->get();
        $dynamicDocument = $this->getDynamicDocument($storeId);

        if ($dynamicDocument !== null) {
            $attributes[] = $dynamicDocument;
        }

        return $attributes;
    }

    public function getDynamicDocument(?int $storeId = null): ?EmbeddedAttribute
    {
        return $this->dynamicDocument->get($storeId);
    }
}
