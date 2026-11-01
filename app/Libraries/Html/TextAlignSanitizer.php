<?php

namespace App\Libraries\Html;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Keeps a single text-align declaration and discards every other CSS value.
 */
final class TextAlignSanitizer implements AttributeSanitizerInterface
{
    public function getSupportedElements(): ?array
    {
        return null;
    }

    public function getSupportedAttributes(): ?array
    {
        return ['style'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        if (preg_match('/(?:^|;)\s*text-align\s*:\s*(left|right|center|justify)\b/i', $value, $matches) !== 1) {
            return null;
        }

        return 'text-align: ' . strtolower($matches[1]);
    }
}
