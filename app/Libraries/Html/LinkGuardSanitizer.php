<?php

namespace App\Libraries\Html;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Rejects scriptable and traversal links that survive generic URL checks.
 */
final class LinkGuardSanitizer implements AttributeSanitizerInterface
{
    public function getSupportedElements(): ?array
    {
        return ['a'];
    }

    public function getSupportedAttributes(): ?array
    {
        return ['href'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $decoded = rawurldecode($value);
        $lower   = strtolower($decoded);

        if (
            str_contains($value, '\\')
            || str_contains($decoded, '..')
            || str_starts_with($value, '//')
            || str_contains($lower, 'javascript:')
            || str_contains($lower, 'vbscript:')
            || str_contains($lower, 'data:')
        ) {
            return null;
        }

        return $value;
    }
}
