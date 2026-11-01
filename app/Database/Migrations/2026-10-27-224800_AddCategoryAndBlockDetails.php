<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Brings databases created by the first blocks migration up to the
 * category and block schema. Fresh installs already have these columns.
 */
class AddCategoryAndBlockDetails extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('slug', 'categories')) {
            $this->forge->addColumn('categories', [
                'slug' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 191,
                    'null'       => false,
                    'default'    => '',
                ],
                'description' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'null'       => false,
                    'default'    => 'active',
                ],
            ]);
            $this->fillSlugs('categories', 'name', 'category');
            $this->db->resetDataCache();
            $this->forge->addUniqueKey('slug');
            $this->forge->addKey('status');
            $this->forge->processIndexes('categories');
        }

        if (! $this->db->fieldExists('slug', 'blocks')) {
            $this->forge->addColumn('blocks', [
                'category_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'slug' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 191,
                    'null'       => false,
                    'default'    => '',
                ],
                'content' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
            ]);
            $this->fillSlugs('blocks', 'title', 'block');
            $this->requireBlockCategories();
            $this->db->resetDataCache();
            $this->forge->addUniqueKey('slug');
            $this->forge->addKey('category_id');
            $this->forge->addForeignKey('category_id', 'categories', 'id', 'CASCADE', 'RESTRICT');
            $this->forge->processIndexes('blocks');
        }
    }

    public function down(): void
    {
        // SQLite test databases drop these tables in the previous migration.
        if ($this->db->DBDriver !== 'MySQLi') {
            return;
        }

        if ($this->db->fieldExists('category_id', 'blocks')) {
            $blocks = $this->db->escapeIdentifiers($this->db->prefixTable('blocks'));
            $this->db->query('ALTER TABLE ' . $blocks . ' DROP FOREIGN KEY ' . $this->db->escapeIdentifiers('blocks_category_id_foreign'));
            $this->forge->dropColumn('blocks', ['category_id', 'slug', 'content']);
        }

        if ($this->db->fieldExists('slug', 'categories')) {
            $this->forge->dropColumn('categories', ['slug', 'description', 'status']);
        }
    }

    private function fillSlugs(string $table, string $source, string $prefix): void
    {
        $rows = $this->db->table($table)->select('id, ' . $source . ', slug')->get()->getResultArray();
        $used = [];

        foreach ($rows as $row) {
            $current = (string) ($row['slug'] ?? '');
            if ($current !== '') {
                $used[$current] = true;

                continue;
            }

            $base = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', (string) ($row[$source] ?? '')));
            $base = trim($base, '-');
            if ($base === '') {
                $base = $prefix . '-' . $row['id'];
            }

            $slug = $base;
            $n    = 2;
            while (isset($used[$slug])) {
                $slug = $base . '-' . $n;
                $n++;
            }

            $used[$slug] = true;
            $this->db->table($table)->where('id', $row['id'])->update(['slug' => $slug]);
        }
    }

    /**
     * Existing blocks need a real category before the foreign key is added.
     */
    private function requireBlockCategories(): void
    {
        $missing = (int) $this->db->table('blocks')
            ->groupStart()
                ->where('category_id', null)
                ->orWhere('category_id', 0)
            ->groupEnd()
            ->countAllResults();

        if ($missing === 0) {
            $this->db->query('ALTER TABLE ' . $this->db->escapeIdentifiers($this->db->prefixTable('blocks')) . ' MODIFY category_id INT(11) UNSIGNED NOT NULL');

            return;
        }

        $categoryId = $this->db->table('categories')->select('id')->orderBy('id', 'ASC')->get()->getRowArray();
        if ($categoryId === null) {
            $this->db->table('categories')->insert([
                'name'       => 'Uncategorized',
                'slug'       => 'uncategorized',
                'status'     => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $id = (int) $this->db->insertID();
        } else {
            $id = (int) $categoryId['id'];
        }

        $this->db->table('blocks')
            ->groupStart()
                ->where('category_id', null)
                ->orWhere('category_id', 0)
            ->groupEnd()
            ->update(['category_id' => $id]);

        $this->db->query('ALTER TABLE ' . $this->db->escapeIdentifiers($this->db->prefixTable('blocks')) . ' MODIFY category_id INT(11) UNSIGNED NOT NULL');
    }
}
