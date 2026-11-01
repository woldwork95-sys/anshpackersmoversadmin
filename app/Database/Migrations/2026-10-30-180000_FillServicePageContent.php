<?php

namespace App\Database\Migrations;

use App\Database\ServicePageCopy;
use CodeIgniter\Database\Migration;

/**
 * Moves the original website service pages into the admin records.
 */
class FillServicePageContent extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('services')) {
            return;
        }

        $html = service('blockHtml');

        foreach (ServicePageCopy::titles() as $slug => $title) {
            $this->db->table('services')->where('slug', $slug)->update(['title' => $title]);
        }

        foreach (ServicePageCopy::pages() as $slug => $page) {
            $content = $html->normalize($page);
            if ($content === null) {
                continue;
            }

            $this->db->table('services')->where('slug', $slug)->update([
                'content' => $content,
            ]);
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('services')) {
            return;
        }

        $this->db->table('services')->whereIn('slug', array_keys(ServicePageCopy::pages()))->update([
            'content' => null,
        ]);
    }
}
