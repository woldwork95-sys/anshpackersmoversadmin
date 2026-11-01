<?php

namespace App\Models;

use App\Libraries\PublicContentCache;

/**
 * Drops the public JSON cache after a saved content change.
 */
trait ForgetsPublicContent
{
    /**
     * @param array<string, mixed> $event
     *
     * @return array<string, mixed>
     */
    protected function forgetPublicContent(array $event): array
    {
        PublicContentCache::forget();

        return $event;
    }
}
