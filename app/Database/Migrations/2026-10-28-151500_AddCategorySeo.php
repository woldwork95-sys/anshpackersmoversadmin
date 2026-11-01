<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Categories get the same optional search fields as blog posts.
 */
class AddCategorySeo extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('categories') || $this->db->fieldExists('meta_title', 'categories')) {
            return;
        }

        $this->forge->addColumn('categories', [
            'meta_title' => [
                'type'       => 'VARCHAR',
                'constraint' => 70,
                'null'       => true,
            ],
            'meta_description' => [
                'type'       => 'VARCHAR',
                'constraint' => 300,
                'null'       => true,
            ],
            'robots_index' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => false,
                'default'    => 'index',
            ],
            'robots_follow' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => false,
                'default'    => 'follow',
            ],
            'og_title' => [
                'type'       => 'VARCHAR',
                'constraint' => 70,
                'null'       => true,
            ],
            'og_description' => [
                'type'       => 'VARCHAR',
                'constraint' => 300,
                'null'       => true,
            ],
        ]);
    }

    public function down(): void
    {
        if (! $this->db->fieldExists('meta_title', 'categories')) {
            return;
        }

        $this->forge->dropColumn('categories', 'og_description');
        $this->forge->dropColumn('categories', 'og_title');
        $this->forge->dropColumn('categories', 'robots_follow');
        $this->forge->dropColumn('categories', 'robots_index');
        $this->forge->dropColumn('categories', 'meta_description');
        $this->forge->dropColumn('categories', 'meta_title');
    }
}
