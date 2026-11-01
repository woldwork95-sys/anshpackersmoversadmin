<?php

namespace App\Controllers\Admin;

class SettingsController extends AdminController
{
    public function index(): string
    {
        return $this->render('admin/settings/index', 'Settings', 'settings');
    }
}
