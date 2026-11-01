<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds page SEO columns and previous-slug redirects for databases
 * created before those fields existed. Fresh installs already have them.
 */
class AddBlockSeo extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('meta_title', 'blocks')) {
            $this->forge->addColumn('blocks', [
                'meta_title' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 70,
                    'null'       => true,
                    'after'      => 'content',
                ],
                'meta_description' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 160,
                    'null'       => true,
                    'after'      => 'meta_title',
                ],
                'robots_index' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 10,
                    'null'       => false,
                    'default'    => 'index',
                    'after'      => 'meta_description',
                ],
                'robots_follow' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 10,
                    'null'       => false,
                    'default'    => 'follow',
                    'after'      => 'robots_index',
                ],
                'og_title' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 70,
                    'null'       => true,
                    'after'      => 'robots_follow',
                ],
                'og_description' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 160,
                    'null'       => true,
                    'after'      => 'og_title',
                ],
                'og_image' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 40,
                    'null'       => true,
                    'after'      => 'og_description',
                ],
            ]);
        }

        if ($this->db->tableExists('block_slug_redirects')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'slug' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
            ],
            'block_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey('block_id');
        $this->forge->addForeignKey('block_id', 'blocks', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('block_slug_redirects', true);
    }

    public function down(): void
    {
        if ($this->db->DBDriver !== 'MySQLi') {
            return;
        }

        $this->forge->dropTable('block_slug_redirects', true);

        foreach ([
            'og_image',
            'og_description',
            'og_title',
            'robots_follow',
            'robots_index',
            'meta_description',
            'meta_title',
        ] as $field) {
            if ($this->db->fieldExists($field, 'blocks')) {
                $this->forge->dropColumn('blocks', $field);
            }
        }
    }
}
