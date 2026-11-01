<?php

namespace App\Controllers;

use App\Models\BlockModel;
use App\Models\CategoryModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

class Blocks extends BaseController
{
    protected $helpers = ['url', 'admin'];

    public function show(string $slug): string|RedirectResponse
    {
        $block = model(BlockModel::class, false)->publishedBySlug($slug);
        if ($block === null) {
            $current = service('blockSlugHistory')->publishedSlug($slug);
            $url     = $current === null ? '' : service('blockSeo')->publicUrl($current);
            if ($url !== '') {
                return redirect()->to($url, 301);
            }

            throw PageNotFoundException::forPageNotFound();
        }

        $category = model(CategoryModel::class, false)->find((int) ($block['category_id'] ?? 0));

        return view('blocks/show', [
            'title'        => (string) ($block['title'] ?? ''),
            'content'      => (string) ($block['content'] ?? ''),
            'author'       => (string) ($block['author_name'] ?? 'Admin'),
            'categoryName' => is_array($category) ? (string) ($category['name'] ?? '') : '',
            'publishedAt'  => (string) ($block['published_at'] ?? ''),
            'seo'          => service('blockSeo')->forBlock($block),
        ]);
    }
}
