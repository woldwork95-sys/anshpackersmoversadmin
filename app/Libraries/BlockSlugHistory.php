<?php

namespace App\Libraries;

use App\Models\BlockModel;

/**
 * Remembers a block's previous public slug and resolves it to the current one.
 * Redirects point at the block, so a later rename does not create a chain.
 */
class BlockSlugHistory
{
    public function remember(int $blockId, string $previousSlug, string $newSlug): void
    {
        if ($blockId < 1 || $previousSlug === '' || $previousSlug === $newSlug) {
            return;
        }

        $db = db_connect();
        $db->table('block_slug_redirects')->where('block_id', $blockId)->where('slug', $newSlug)->delete();
        $db->table('block_slug_redirects')->where('slug', $previousSlug)->delete();
        $db->table('block_slug_redirects')->insert([
            'slug'       => $previousSlug,
            'block_id'   => $blockId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function takenByOtherBlock(string $slug, int $blockId): bool
    {
        $row = db_connect()->table('block_slug_redirects')->where('slug', $slug)->get()->getRowArray();
        if (! is_array($row)) {
            return false;
        }

        return (int) ($row['block_id'] ?? 0) !== $blockId;
    }

    public function publishedSlug(string $slug): ?string
    {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            return null;
        }

        $row = db_connect()->table('block_slug_redirects')
            ->select('blocks.slug')
            ->join('blocks', 'blocks.id = block_slug_redirects.block_id')
            ->where('block_slug_redirects.slug', $slug)
            ->where('blocks.status', BlockModel::STATUS_PUBLISHED)
            ->get()
            ->getRowArray();

        if (! is_array($row)) {
            return null;
        }

        $current = (string) ($row['slug'] ?? '');
        if ($current === '' || $current === $slug || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $current) !== 1) {
            return null;
        }

        return $current;
    }
}
