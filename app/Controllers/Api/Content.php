<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\PublicContentCache;
use App\Models\BlockModel;
use App\Models\CategoryModel;
use App\Models\ServiceModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * One read-only JSON document for the public website.
 * Drafts and inactive categories stay out of the response.
 */
class Content extends BaseController
{
    public function index(): ResponseInterface
    {
        $payload = $this->cachedPayload();

        return $this->response
            ->setHeader('Cache-Control', ENVIRONMENT === 'testing' ? 'no-store' : 'public, max-age=60')
            ->setJSON(['data' => $payload]);
    }

    /**
     * @return array<string, mixed>
     */
    private function cachedPayload(): array
    {
        if (ENVIRONMENT !== 'testing') {
            $cached = cache()->get(PublicContentCache::KEY);
            if (is_array($cached)) {
                if (! is_file(PublicContentCache::filePath())) {
                    PublicContentCache::write($cached);
                }

                return $cached;
            }
        }

        $payload = $this->buildPayload();

        if (ENVIRONMENT !== 'testing') {
            cache()->save(PublicContentCache::KEY, $payload, PublicContentCache::TTL);
            PublicContentCache::write($payload);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(): array
    {
        $cardsByCategory = [];
        $posts           = [];

        foreach ($this->publishedRows() as $row) {
            $posts[] = $this->document($row);

            $categoryId = (int) ($row['category_id'] ?? 0);
            if ($categoryId > 0 && ($row['category_status'] ?? '') === CategoryModel::STATUS_ACTIVE) {
                $cardsByCategory[$categoryId][] = $this->card($row);
            }
        }

        $categories = [];
        foreach ($this->activeCategories() as $category) {
            $categories[] = $this->categoryDocument($category) + [
                'posts' => $cardsByCategory[(int) $category['id']] ?? [],
            ];
        }

        $redirects = service('serviceSlugHistory')->publishedMap();

        return [
            'categories'        => $categories,
            'posts'             => $posts,
            'services'          => $this->serviceDocuments(),
            'service_redirects' => $redirects === [] ? new \stdClass() : $redirects,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function publishedRows(): array
    {
        return db_connect()->table('blocks')
            ->select('blocks.category_id, blocks.title, blocks.slug, blocks.content, blocks.excerpt, blocks.author_name, blocks.og_image, blocks.published_at, blocks.meta_title, blocks.meta_description, blocks.robots_index, blocks.robots_follow, blocks.og_title, blocks.og_description, categories.name AS category_name, categories.slug AS category_slug, categories.status AS category_status')
            ->join('categories', 'categories.id = blocks.category_id', 'left')
            ->where('blocks.status', BlockModel::STATUS_PUBLISHED)
            ->orderBy('blocks.published_at', 'DESC')
            ->orderBy('blocks.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activeCategories(): array
    {
        return model(CategoryModel::class, false)
            ->where('status', CategoryModel::STATUS_ACTIVE)
            ->orderBy('name', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function document(array $row): array
    {
        $seo     = service('blockSeo')->forBlock($row);
        $content = service('blockHtml')->render((string) ($row['content'] ?? ''));

        return $this->card($row) + [
            'content' => $content,
            'status'  => BlockModel::STATUS_PUBLISHED,
            'seo'     => [
                'title'          => $seo['title'],
                'description'    => $seo['description'],
                'canonical'      => $seo['canonical'],
                'robots'         => $seo['robots'],
                'og_title'       => $seo['ogTitle'],
                'og_description' => $seo['ogDescription'],
                'image'          => $seo['image'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function card(array $row): array
    {
        $slug = (string) ($row['slug'] ?? '');

        return [
            'title'        => (string) ($row['title'] ?? ''),
            'slug'         => $slug,
            'url'          => service('blockSeo')->publicUrl($slug),
            'excerpt'      => $this->excerpt($row),
            'author'       => (string) ($row['author_name'] ?? 'Admin'),
            'published_at' => (string) ($row['published_at'] ?? ''),
            'image'        => service('blockSeo')->imageUrl((string) ($row['og_image'] ?? '')),
            'category'     => $this->categoryRef($row),
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function categoryDocument(array $row): array
    {
        $seo = service('blockSeo')->forCategory($row);

        return [
            'name'        => (string) ($row['name'] ?? ''),
            'slug'        => (string) ($row['slug'] ?? ''),
            'description' => $this->text((string) ($row['description'] ?? '')),
            'status'      => CategoryModel::STATUS_ACTIVE,
            'seo'         => [
                'title'          => $seo['title'],
                'description'    => $seo['description'],
                'robots'         => $seo['robots'],
                'og_title'       => $seo['ogTitle'],
                'og_description' => $seo['ogDescription'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{name: string, slug: string}
     */
    private function categoryRef(array $row): array
    {
        if (($row['category_status'] ?? $row['status'] ?? '') !== CategoryModel::STATUS_ACTIVE) {
            return ['name' => '', 'slug' => ''];
        }

        return [
            'name' => (string) ($row['category_name'] ?? $row['name'] ?? ''),
            'slug' => (string) ($row['category_slug'] ?? $row['slug'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function excerpt(array $row): string
    {
        $excerpt = trim((string) ($row['excerpt'] ?? ''));
        if ($excerpt !== '') {
            return $this->text($excerpt);
        }

        return service('blockSeo')->excerpt((string) ($row['content'] ?? ''));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serviceDocuments(): array
    {
        $documents = [];
        foreach (model(ServiceModel::class, false)->publishedServices() as $row) {
            $documents[] = $this->serviceDocument($row);
        }

        return $documents;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function serviceDocument(array $row): array
    {
        $content = service('blockHtml')->render((string) ($row['content'] ?? ''));

        return [
            'title'        => (string) ($row['title'] ?? ''),
            'slug'         => (string) ($row['slug'] ?? ''),
            'excerpt'      => $this->excerpt($row),
            'image'        => $this->serviceImage($row),
            'image_alt'    => $this->text((string) ($row['image_alt'] ?? '')),
            'sort_order'   => (int) ($row['sort_order'] ?? 0),
            'published_at' => (string) ($row['published_at'] ?? ''),
            'content'      => $content,
            'status'       => ServiceModel::STATUS_PUBLISHED,
            'seo'          => $this->serviceSeo($row),
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{title: string, description: string, robots: string, og_title: string, og_description: string, image: string}
     */
    private function serviceSeo(array $row): array
    {
        $title = $this->text((string) ($row['meta_title'] ?? ''));
        if ($title === '') {
            $title = $this->text((string) ($row['title'] ?? ''));
        }

        $description = $this->text((string) ($row['meta_description'] ?? ''));
        if ($description === '') {
            $description = $this->excerpt($row);
        }

        $ogTitle = $this->text((string) ($row['og_title'] ?? ''));
        if ($ogTitle === '') {
            $ogTitle = $title;
        }

        $ogDescription = $this->text((string) ($row['og_description'] ?? ''));
        if ($ogDescription === '') {
            $ogDescription = $description;
        }

        $index  = (string) ($row['robots_index'] ?? '') === 'noindex' ? 'noindex' : 'index';
        $follow = (string) ($row['robots_follow'] ?? '') === 'nofollow' ? 'nofollow' : 'follow';
        $image  = $this->serviceImage($row);

        return [
            'title'          => $title,
            'description'    => $description,
            'robots'         => $index . ', ' . $follow,
            'og_title'       => $ogTitle,
            'og_description' => $ogDescription,
            'image'          => $image,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function serviceImage(array $row): string
    {
        $uploaded = service('blockSeo')->imageUrl((string) ($row['og_image'] ?? ''));
        if ($uploaded !== '') {
            return $uploaded;
        }

        $path = (string) ($row['cover_path'] ?? '');
        if (preg_match('#^/img/[A-Za-z0-9._-]+$#', $path) === 1) {
            return $path;
        }

        return '';
    }

    private function text(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace("\xc2\xa0", ' ', $value);

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }
}
