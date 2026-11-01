<?php

namespace App\Database\Seeds;

use App\Models\AdminModel;
use CodeIgniter\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admins = model(AdminModel::class);

        if ($admins->where('username', 'admin')->first() !== null) {
            return;
        }

        $admins->insert([
            'username'      => 'admin',
            'email'         => 'admin@anshpackersmovers.local',
            'password_hash' => password_hash('AnshAdmin@2026', PASSWORD_DEFAULT),
        ]);
    }
}
