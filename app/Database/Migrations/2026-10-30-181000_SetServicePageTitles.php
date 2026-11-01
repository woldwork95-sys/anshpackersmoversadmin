<?php

namespace App\Database\Migrations;

use App\Database\ServicePageCopy;
use CodeIgniter\Database\Migration;

/**
 * The public heading is the service title, so it has to match the old page.
 */
class SetServicePageTitles extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('services')) {
            return;
        }

        foreach (ServicePageCopy::titles() as $slug => $title) {
            $this->db->table('services')->where('slug', $slug)->update(['title' => $title]);
        }
    }

    public function down(): void
    {
    }
}
