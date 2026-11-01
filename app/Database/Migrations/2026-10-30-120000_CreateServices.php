<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Public services are a separate list from blog posts.
 * The website reads published rows from the content API.
 */
class CreateServices extends Migration
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
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
            ],
            'slug' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
            ],
            'excerpt' => [
                'type'       => 'VARCHAR',
                'constraint' => 300,
                'null'       => true,
            ],
            'content' => [
                'type' => 'MEDIUMTEXT',
                'null' => true,
            ],
            'image_alt' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
                'null'       => true,
            ],
            'cover_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
                'null'       => true,
            ],
            'og_image' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'null'       => true,
            ],
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
            'sort_order' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'draft',
            ],
            'published_at' => [
                'type' => 'DATETIME',
                'null' => true,
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
        $this->forge->addKey('sort_order');
        $this->forge->createTable('services', true);

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
            'service_id' => [
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
        $this->forge->addKey('service_id');
        $this->forge->addForeignKey('service_id', 'services', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('service_slug_redirects', true);

        $this->seedWebsiteServices();
    }

    public function down(): void
    {
        $this->forge->dropTable('service_slug_redirects', true);
        $this->forge->dropTable('services', true);
    }

    /**
     * Tests start from an empty services table. A real install gets the
     * six services already shown on the website.
     */
    private function seedWebsiteServices(): void
    {
        if (ENVIRONMENT === 'testing' || $this->db->table('services')->countAllResults() > 0) {
            return;
        }

        $now  = date('Y-m-d H:i:s');
        $rows = [
            [
                'title'            => 'Car & Bike Transportation',
                'slug'             => 'car-bike-transportation',
                'excerpt'          => 'Cars and bikes are loaded separately from household cartons and moved between Bangalore and other cities. Share the vehicle type and both addresses for a transport quotation.',
                'image_alt'        => 'Car carrier prepared for vehicle transportation from Bangalore',
                'cover_path'       => '/img/carbike.webp',
                'meta_title'       => 'Car & Bike Transport Services in Bangalore | Ansh Packers',
                'meta_description' => 'Car and bike transport from Bangalore. Share the vehicle, pickup and destination for a quotation from Ansh Packers and Movers.',
                'sort_order'       => 1,
            ],
            [
                'title'            => 'Local Household Shifting',
                'slug'             => 'local-household-shifting',
                'excerpt'          => 'House shifting inside Bangalore includes packing, loading, and unloading. Tell us the floor, lift access, and society timing so the crew can plan the day.',
                'image_alt'        => 'Household goods packed for local house shifting in Bangalore',
                'cover_path'       => '/img/localhousehold.webp',
                'meta_title'       => 'Local Household Shifting in Bangalore | Ansh Packers',
                'meta_description' => 'Need local household shifting in Bangalore? Ansh Packers makes moving easier with reliable packing and shifting services. Contact us today for a free quote!',
                'sort_order'       => 2,
            ],
            [
                'title'            => 'Domestic Shifting Services',
                'slug'             => 'domestic-shifting-services',
                'excerpt'          => 'Intercity moves from Bangalore are packed for a longer journey. The quotation depends on volume, distance, and the packing you need.',
                'image_alt'        => 'Household cartons loaded for intercity transport from Bangalore',
                'cover_path'       => '/img/domestic1.webp',
                'meta_title'       => 'Domestic Packers & Movers in Bangalore | Ansh Packers',
                'meta_description' => 'Domestic shifting from Bangalore to another city in India. Share the destination, volume and date for a quotation from Ansh Packers and Movers.',
                'sort_order'       => 3,
            ],
            [
                'title'            => 'Corporate Relocation Services',
                'slug'             => 'corporate-relocation-services',
                'excerpt'          => 'Office relocation covers workstations, files, and equipment. Share access hours so the move can be planned around your working day.',
                'image_alt'        => 'Office furniture packed for a corporate relocation in Bangalore',
                'cover_path'       => '/img/corprate.webp',
                'meta_title'       => 'Corporate Relocation Services in Bangalore | Ansh Packers',
                'meta_description' => 'Office and corporate relocation in Bangalore for furniture, files and IT equipment. Ask about a move to another Indian city and get a written quote.',
                'sort_order'       => 4,
            ],
            [
                'title'            => 'International Transportation',
                'slug'             => 'international-transportation',
                'excerpt'          => 'Goods sent overseas are packed for a longer transit. Ask what documents and packing are included when you request a quotation.',
                'image_alt'        => 'Packed cargo prepared for international transportation',
                'cover_path'       => '/img/banner4.webp',
                'meta_title'       => 'International Packers and Movers in Bangalore | Ansh',
                'meta_description' => 'International transportation from Bangalore. Tell Ansh Packers and Movers the destination and what is moving, and ask for a written quotation.',
                'sort_order'       => 5,
            ],
            [
                'title'            => 'Warehouse Services',
                'slug'             => 'warehouse-service',
                'excerpt'          => 'Household or office goods can be stored when the next place is not ready. Ask about space and duration when you request a quotation.',
                'image_alt'        => 'Warehouse storage space for household and office goods in Bangalore',
                'cover_path'       => '/img/warehouse.webp',
                'meta_title'       => 'Warehouse Services in Bangalore | Ansh Packers',
                'meta_description' => 'Warehouse services in Bangalore for business goods and storage during a move. Contact Ansh Packers and Movers to check availability and request a quote.',
                'sort_order'       => 6,
            ],
        ];

        foreach ($rows as $row) {
            $this->db->table('services')->insert($row + [
                'content'       => null,
                'robots_index'  => 'index',
                'robots_follow' => 'follow',
                'status'        => 'published',
                'published_at'  => $now,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }
    }
}
