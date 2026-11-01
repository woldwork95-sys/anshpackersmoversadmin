<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Existing MySQL tables received nullable robots columns. Keep the
 * index/follow default and require a value so sitemap and page tags agree.
 */
class RequireBlockRobots extends Migration
{
    public function up(): void
    {
        if ($this->db->DBDriver !== 'MySQLi' || ! $this->db->fieldExists('robots_index', 'blocks')) {
            return;
        }

        $this->db->query("ALTER TABLE {$this->db->prefixTable('blocks')} MODIFY robots_index VARCHAR(10) NOT NULL DEFAULT 'index', MODIFY robots_follow VARCHAR(10) NOT NULL DEFAULT 'follow'");
    }

    public function down(): void
    {
        if ($this->db->DBDriver !== 'MySQLi' || ! $this->db->fieldExists('robots_index', 'blocks')) {
            return;
        }

        $this->db->query("ALTER TABLE {$this->db->prefixTable('blocks')} MODIFY robots_index VARCHAR(10) NULL DEFAULT 'index', MODIFY robots_follow VARCHAR(10) NULL DEFAULT 'follow'");
    }
}
