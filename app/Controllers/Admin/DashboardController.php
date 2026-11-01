<?php

namespace App\Controllers\Admin;

use App\Libraries\DashboardStats;

class DashboardController extends AdminController
{
    public function index(): string
    {
        return $this->render('admin/dashboard', 'Dashboard', 'dashboard', [
            'stats' => (new DashboardStats())->snapshot(),
        ]);
    }
}
