<?php

namespace App\Libraries;

use App\Models\ServiceModel;

/**
 * Remembers a service's previous public slug and resolves it to the current one.
 * Redirects point at the service, so a later rename does not create a chain.
 */
class ServiceSlugHistory
{
    public function remember(int $serviceId, string $previousSlug, string $newSlug): void
    {
        if ($serviceId < 1 || $previousSlug === '' || $previousSlug === $newSlug) {
            return;
        }

        $db = db_connect();
        $db->table('service_slug_redirects')->where('service_id', $serviceId)->where('slug', $newSlug)->delete();
        $db->table('service_slug_redirects')->where('slug', $previousSlug)->delete();
        $db->table('service_slug_redirects')->insert([
            'slug'       => $previousSlug,
            'service_id' => $serviceId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function takenByOtherService(string $slug, int $serviceId): bool
    {
        $row = db_connect()->table('service_slug_redirects')->where('slug', $slug)->get()->getRowArray();
        if (! is_array($row)) {
            return false;
        }

        return (int) ($row['service_id'] ?? 0) !== $serviceId;
    }

    /**
     * @return array<string, string>
     */
    public function publishedMap(): array
    {
        $rows = db_connect()->table('service_slug_redirects')
            ->select('service_slug_redirects.slug AS old_slug, services.slug AS slug')
            ->join('services', 'services.id = service_slug_redirects.service_id')
            ->where('services.status', ServiceModel::STATUS_PUBLISHED)
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $old = (string) ($row['old_slug'] ?? '');
            $new = (string) ($row['slug'] ?? '');
            if ($old === '' || $new === '' || $old === $new) {
                continue;
            }
            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $old) !== 1) {
                continue;
            }
            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $new) !== 1) {
                continue;
            }
            $map[$old] = $new;
        }

        return $map;
    }
}
