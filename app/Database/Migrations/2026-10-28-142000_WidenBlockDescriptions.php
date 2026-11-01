<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Meta and share descriptions were capped at 160 characters, so anything
 * typed past that was rejected. SQLite does not enforce VARCHAR length.
 */
class WidenBlockDescriptions extends Migration
{
    public function up(): void
    {
        if ($this->db->DBDriver !== 'MySQLi' || ! $this->db->fieldExists('meta_description', 'blocks')) {
            return;
        }

        $table = $this->db->escapeIdentifiers($this->db->prefixTable('blocks'));
        $this->db->query('ALTER TABLE ' . $table . ' MODIFY meta_description VARCHAR(300) NULL');
        $this->db->query('ALTER TABLE ' . $table . ' MODIFY og_description VARCHAR(300) NULL');
    }

    public function down(): void
    {
        if ($this->db->DBDriver !== 'MySQLi' || ! $this->db->fieldExists('meta_description', 'blocks')) {
            return;
        }

        $table = $this->db->escapeIdentifiers($this->db->prefixTable('blocks'));
        $this->db->query('ALTER TABLE ' . $table . ' MODIFY meta_description VARCHAR(160) NULL');
        $this->db->query('ALTER TABLE ' . $table . ' MODIFY og_description VARCHAR(160) NULL');
    }
}
