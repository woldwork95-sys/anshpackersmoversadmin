<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Formatted block content needs more than a 64KB TEXT column.
 * SQLite already stores this as unlimited text, so only MySQL is altered.
 */
class WidenBlockContent extends Migration
{
    public function up(): void
    {
        if ($this->db->DBDriver !== 'MySQLi') {
            return;
        }

        $table = $this->db->escapeIdentifiers($this->db->prefixTable('blocks'));
        $this->db->query('ALTER TABLE ' . $table . ' MODIFY content MEDIUMTEXT NULL');
    }

    public function down(): void
    {
        if ($this->db->DBDriver !== 'MySQLi') {
            return;
        }

        $table = $this->db->escapeIdentifiers($this->db->prefixTable('blocks'));
        $this->db->query('ALTER TABLE ' . $table . ' MODIFY content TEXT NULL');
    }
}
