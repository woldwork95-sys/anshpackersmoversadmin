<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBlocksAndCategories extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
            ],
            'slug' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'active',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey('status');
        $this->forge->createTable('categories', true);

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'category_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
            ],
            'slug' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
            ],
            'content' => [
                'type' => 'MEDIUMTEXT',
                'null' => true,
            ],
            'meta_title' => [
                'type'       => 'VARCHAR',
                'constraint' => 70,
                'null'       => true,
            ],
            'meta_description' => [
                'type'       => 'VARCHAR',
                'constraint' => 160,
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
                'constraint' => 160,
                'null'       => true,
            ],
            'og_image' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'null'       => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'draft',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey('category_id');
        $this->forge->addKey('status');
        $this->forge->addKey('updated_at');
        $this->forge->addForeignKey('category_id', 'categories', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('blocks', true);

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
        $this->forge->dropTable('block_slug_redirects', true);
        $this->forge->dropTable('blocks', true);
        $this->forge->dropTable('categories', true);
    }
}
