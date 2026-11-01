<?php

namespace App\Controllers;

use App\Models\BlockModel;

class Sitemap extends BaseController
{
    public function index()
    {
        helper('url');
        $rows = model(BlockModel::class, false)->indexable();
        $seo  = service('blockSeo');
        $xml  = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
        $xml[] = '<url>';
        $xml[] = '<loc>' . $this->xml(site_url('blog', null, config(\Config\App::class))) . '</loc>';
        $xml[] = '</url>';

        foreach ($rows as $row) {
            $location = $seo->publicUrl((string) ($row['slug'] ?? ''));
            if ($location === '') {
                continue;
            }

            $xml[] = '<url>';
            $xml[] = '<loc>' . $this->xml($location) . '</loc>';
            $modified = $this->modified((string) ($row['updated_at'] ?? ''));
            if ($modified !== '') {
                $xml[] = '<lastmod>' . $this->xml($modified) . '</lastmod>';
            }
            $xml[] = '</url>';
        }

        $xml[] = '</urlset>';

        return $this->response
            ->setContentType('application/xml')
            ->setBody(implode("\n", $xml) . "\n");
    }

    private function modified(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $time = strtotime($value);

        return $time === false ? '' : gmdate('Y-m-d\TH:i:s\Z', $time);
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
