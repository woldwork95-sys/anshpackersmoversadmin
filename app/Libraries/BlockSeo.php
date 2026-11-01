<?php

namespace App\Libraries;

use Config\App;

/**
 * Search metadata for a public blog post.
 *
 * A published post is the public page: its address is /blog/{slug}. Categories
 * only group posts in the admin and do not have public URLs, so they do not
 * carry search fields. The canonical URL is built from Config\App::$baseURL
 * and the route, never from the request Host header.
 */
class BlockSeo
{
    public const TITLE_LIMIT       = 70;
    public const DESCRIPTION_LIMIT = 300;
    public const EXCERPT_LIMIT     = 160;

    /**
     * @param array<string, mixed> $block
     *
     * @return array{
     *     title: string,
     *     description: string,
     *     canonical: string,
     *     robots: string,
     *     ogTitle: string,
     *     ogDescription: string,
     *     image: string
     * }
     */
    public function forBlock(array $block): array
    {
        $title = $this->plain((string) ($block['meta_title'] ?? ''));
        if ($title === '') {
            $title = $this->plain((string) ($block['title'] ?? ''));
        }

        $description = $this->plain((string) ($block['meta_description'] ?? ''));
        if ($description === '') {
            $description = $this->excerpt((string) ($block['content'] ?? ''));
        }

        $ogTitle = $this->plain((string) ($block['og_title'] ?? ''));
        if ($ogTitle === '') {
            $ogTitle = $title;
        }

        $ogDescription = $this->plain((string) ($block['og_description'] ?? ''));
        if ($ogDescription === '') {
            $ogDescription = $description;
        }

        $index  = (string) ($block['robots_index'] ?? '') === 'noindex' ? 'noindex' : 'index';
        $follow = (string) ($block['robots_follow'] ?? '') === 'nofollow' ? 'nofollow' : 'follow';

        return [
            'title'         => $title,
            'description'   => $description,
            'canonical'     => $this->publicUrl((string) ($block['slug'] ?? '')),
            'robots'        => $index . ', ' . $follow,
            'ogTitle'       => $ogTitle,
            'ogDescription' => $ogDescription,
            'image'         => $this->imageUrl((string) ($block['og_image'] ?? '')),
        ];
    }

    /**
     * Search fields for a category. There is no public category page, so
     * the canonical address is left empty.
     *
     * @param array<string, mixed> $category
     *
     * @return array{title: string, description: string, robots: string, ogTitle: string, ogDescription: string}
     */
    public function forCategory(array $category): array
    {
        $fields = $this->forBlock([
            'title'            => (string) ($category['name'] ?? ''),
            'slug'             => 'category',
            'content'          => (string) ($category['description'] ?? ''),
            'meta_title'       => (string) ($category['meta_title'] ?? ''),
            'meta_description' => (string) ($category['meta_description'] ?? ''),
            'robots_index'     => (string) ($category['robots_index'] ?? 'index'),
            'robots_follow'    => (string) ($category['robots_follow'] ?? 'follow'),
            'og_title'         => (string) ($category['og_title'] ?? ''),
            'og_description'   => (string) ($category['og_description'] ?? ''),
        ]);

        return [
            'title'         => $fields['title'],
            'description'   => $fields['description'],
            'robots'        => $fields['robots'],
            'ogTitle'       => $fields['ogTitle'],
            'ogDescription' => $fields['ogDescription'],
        ];
    }

    public function publicUrl(string $slug): string
    {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            return '';
        }

        helper('url');

        return site_url('blog/' . $slug, null, config(App::class));
    }

    public function imageUrl(string $filename): string
    {
        if (preg_match('/^[a-f0-9]{32}\.(?:jpe?g|png|gif|webp)$/', $filename) !== 1) {
            return '';
        }

        return rtrim(config(App::class)->baseURL, '/') . '/uploads/seo/' . $filename;
    }

    public function excerpt(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if ($text === '' || mb_strlen($text) <= self::EXCERPT_LIMIT) {
            return $text;
        }

        $cut   = mb_substr($text, 0, self::EXCERPT_LIMIT);
        $space = mb_strrpos($cut, ' ');
        if ($space !== false && $space >= 80) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, " \t.,;:-");
    }

    private function plain(string $value): string
    {
        $value = trim(str_replace("\0", '', $value));
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value;
    }
}
