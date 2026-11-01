<?php

namespace App\Libraries;

/**
 * The public website reads one JSON document.
 * Building it renders every published article, so the result is reused
 * until a post, category, or service changes.
 */
class PublicContentCache
{
    public const KEY = 'public_content_v1';

    public const TTL = 120;

    public static function forget(): void
    {
        if (ENVIRONMENT === 'testing') {
            return;
        }

        cache()->delete(self::KEY);

        $path = self::filePath();
        if (is_file($path)) {
            unlink($path);
        }
    }

    public static function filePath(): string
    {
        return FCPATH . 'content.json';
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function write(array $payload): void
    {
        if (ENVIRONMENT === 'testing') {
            return;
        }

        $json = json_encode(
            ['data' => $payload],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($json === false) {
            return;
        }

        file_put_contents(self::filePath(), $json, LOCK_EX);
    }
}
