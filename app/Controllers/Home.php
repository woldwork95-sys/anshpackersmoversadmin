<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use Config\App;

class Home extends BaseController
{
    /**
     * The public site entry for this app is the blog. The canonical listing
     * lives at /blog and is the URL included in the sitemap.
     */
    public function index(): RedirectResponse
    {
        helper('url');

        return redirect()->to(site_url('blog', null, config(App::class)), 301);
    }
}
