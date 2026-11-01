<?php

namespace App\Libraries\Html;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Allows only small integer measures on tables and images.
 */
final class MeasureSanitizer implements AttributeSanitizerInterface
{
    public function getSupportedElements(): ?array
    {
        return null;
    }

    public function getSupportedAttributes(): ?array
    {
        return ['colspan', 'rowspan', 'width', 'height'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        if (preg_match('/^[1-9][0-9]{0,3}$/', $value) !== 1) {
            return null;
        }

        $number = (int) $value;
        $limit  = in_array($attribute, ['colspan', 'rowspan'], true) ? 20 : 8000;

        if ($number > $limit) {
            return null;
        }

        return (string) $number;
    }
}
