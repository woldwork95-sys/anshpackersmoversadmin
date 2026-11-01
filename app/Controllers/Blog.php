<?php

namespace App\Controllers;

use App\Models\BlockModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use Config\App;

class Blog extends BaseController
{
    protected $helpers = ['url', 'admin'];

    public function index(): string
    {
        return view('blog/index', [
            'posts'     => model(BlockModel::class, false)->publishedPosts(),
            'canonical' => site_url('blog', null, config(App::class)),
        ]);
    }

    /**
     * Old /blocks/{slug} addresses move to the blog URL in one step.
     */
    public function legacy(string $slug): RedirectResponse
    {
        $seo    = service('blockSeo');
        $blocks = model(BlockModel::class, false);

        if ($blocks->publishedBySlug($slug) !== null) {
            $url = $seo->publicUrl($slug);
            if ($url !== '') {
                return redirect()->to($url, 301);
            }
        }

        $current = service('blockSlugHistory')->publishedSlug($slug);
        $url     = $current === null ? '' : $seo->publicUrl($current);

        if ($url !== '') {
            return redirect()->to($url, 301);
        }

        throw PageNotFoundException::forPageNotFound();
    }
}
