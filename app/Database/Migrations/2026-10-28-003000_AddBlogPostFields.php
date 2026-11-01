<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Blog posts stay in the blocks table. These columns are the listing
 * excerpt, the byline, and the date shown on the public blog.
 */
class AddBlogPostFields extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('blocks') || $this->db->fieldExists('excerpt', 'blocks')) {
            return;
        }

        $this->forge->addColumn('blocks', [
            'excerpt' => [
                'type'       => 'VARCHAR',
                'constraint' => 300,
                'null'       => true,
            ],
            'author_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => false,
                'default'    => 'Admin',
            ],
            'published_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $table = $this->db->prefixTable('blocks');
        $this->db->query("UPDATE {$table} SET author_name = 'Admin' WHERE author_name IS NULL OR author_name = ''");
        $this->db->query("UPDATE {$table} SET published_at = created_at WHERE status = 'published' AND published_at IS NULL");
    }

    public function down(): void
    {
        if ($this->db->DBDriver !== 'MySQLi' || ! $this->db->fieldExists('excerpt', 'blocks')) {
            return;
        }

        $this->forge->dropColumn('blocks', 'published_at');
        $this->forge->dropColumn('blocks', 'author_name');
        $this->forge->dropColumn('blocks', 'excerpt');
    }
}
