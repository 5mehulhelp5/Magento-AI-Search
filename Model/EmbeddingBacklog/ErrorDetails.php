<?php
/**
 * davidbel/magento-ai-search by David Belicza
 * SPDX-License-Identifier: MIT
 * https://github.com/DavidBelicza/Magento-AI-Search
 */
declare(strict_types=1);

namespace DavidBel\AiSearch\Model\EmbeddingBacklog;

readonly class ErrorDetails
{
    private const int MAXIMUM_CODE_LENGTH = 64;
    private const int MAXIMUM_MESSAGE_LENGTH = 1_000;

    public ?string $code;
    public string $message;

    public function __construct(?string $code, string $message)
    {
        $normalizedCode = $code === null ? '' : trim($code);
        $normalizedMessage = preg_replace('/\s+/u', ' ', trim($message));

        $this->code = $normalizedCode === ''
            ? null
            : mb_substr($normalizedCode, 0, self::MAXIMUM_CODE_LENGTH);
        $this->message = mb_substr(
            $normalizedMessage === null || $normalizedMessage === ''
                ? 'Processing failed.'
                : $normalizedMessage,
            0,
            self::MAXIMUM_MESSAGE_LENGTH
        );
    }
}
