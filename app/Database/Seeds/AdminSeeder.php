<?php

namespace App\Database\Seeds;

use App\Models\AdminModel;
use CodeIgniter\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admins = model(AdminModel::class);
        $admin  = $admins->where('username', 'admin')->first();
        $password = (string) env('ADMIN_INITIAL_PASSWORD');

        if ($password === '' && $admin !== null) {
            return;
        }

        if (strlen($password) < 16) {
            throw new \RuntimeException('Set ADMIN_INITIAL_PASSWORD to at least 16 characters before creating the first admin.');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if ($admin !== null) {
            $admins->update((int) $admin['id'], ['password_hash' => $passwordHash]);

            return;
        }

        $admins->insert([
            'username'      => 'admin',
            'email'         => 'admin@anshpackersmovers.com',
            'password_hash' => $passwordHash,
        ]);
    }
}
