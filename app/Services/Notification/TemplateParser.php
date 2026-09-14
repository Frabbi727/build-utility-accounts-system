<?php

namespace App\Services\Notification;

use DateTimeInterface;

class TemplateParser
{
    /**
     * Parse and replace placeholder tokens in a template string.
     *
     * @param  array<string, mixed>  $data
     */
    public function parse(string $template, array $data = []): string
    {
        if ($template === '') {
            return '';
        }

        $rendered = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function (array $matches) use ($data): string {
            $token = $matches[1];

            if (! array_key_exists($token, $data) || $data[$token] === null) {
                return '';
            }

            return $this->formatTokenValue($token, $data[$token]);
        }, $template);

        if ($rendered === null) {
            return $template;
        }

        return $this->cleanWhitespace($rendered);
    }

    /**
     * Static helper to render a template with given data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function render(string $template, array $data = []): string
    {
        return (new self)->parse($template, $data);
    }

    /**
     * Extract token names from a template string.
     *
     * @return array<int, string>
     */
    public function extractTokens(string $template): array
    {
        if ($template === '') {
            return [];
        }

        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $template, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Static helper to extract tokens from a template string.
     *
     * @return array<int, string>
     */
    public static function tokens(string $template): array
    {
        return (new self)->extractTokens($template);
    }

    /**
     * Format a token value according to token key rules.
     */
    protected function formatTokenValue(string $token, mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            if ($token === 'billing_month') {
                return $value->format('F Y');
            }

            return $value->format('d M Y');
        }

        if ($token === 'amount') {
            if (is_numeric($value)) {
                return number_format((float) $value, 2);
            }

            return (string) $value;
        }

        return (string) $value;
    }

    /**
     * Normalize spaces and clean up orphaned punctuation resulting from missing tokens.
     */
    protected function cleanWhitespace(string $text): string
    {
        // Collapse consecutive horizontal spaces
        $cleaned = preg_replace('/[^\S\r\n]+/', ' ', $text) ?? $text;

        // Clean spaces preceding punctuation (e.g., "word ." -> "word.")
        $cleaned = preg_replace('/\s+([.,;:!?])/', '$1', $cleaned) ?? $cleaned;

        return trim($cleaned);
    }
}
