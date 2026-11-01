<?php

namespace App\Libraries;

final class AdminSlug
{
    public static function from(string $value): string
    {
        $value = strtolower(strip_tags($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        if (strlen($value) > 191) {
            $value = rtrim(substr($value, 0, 191), '-');
        }

        return $value;
    }
}
