<?php

namespace App\Models;

use App\Libraries\AdminSlug;
use CodeIgniter\Model;
use InvalidArgumentException;

class BlockModel extends Model
{
    use ForgetsPublicContent;
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_DRAFT     = 'draft';
    public const PER_PAGE         = 10;

    protected $table            = 'blocks';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'category_id',
        'title',
        'slug',
        'content',
        'meta_title',
        'meta_description',
        'robots_index',
        'robots_follow',
        'og_title',
        'og_description',
        'og_image',
        'excerpt',
        'author_name',
        'published_at',
        'status',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $beforeInsert = ['prepareBlock'];
    protected $beforeUpdate = ['prepareBlock'];
    protected $afterInsert  = ['forgetPublicContent'];
    protected $afterUpdate  = ['forgetPublicContent'];
    protected $afterDelete  = ['forgetPublicContent'];

    /**
     * One grouped query for every status count.
     *
     * @return array{total: int, published: int, draft: int}
     */
    public function statusCounts(): array
    {
        $rows = $this->db->table($this->table)
            ->select('status, COUNT(*) AS total')
            ->groupBy('status')
            ->get()
            ->getResultArray();

        $counts = [
            'total'     => 0,
            'published' => 0,
            'draft'     => 0,
        ];

        foreach ($rows as $row) {
            $total = (int) ($row['total'] ?? 0);
            $counts['total'] += $total;

            $status = (string) ($row['status'] ?? '');
            if ($status === self::STATUS_PUBLISHED) {
                $counts['published'] = $total;
            } elseif ($status === self::STATUS_DRAFT) {
                $counts['draft'] = $total;
            }
        }

        return $counts;
    }

    public function publishedBySlug(string $slug): ?array
    {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            return null;
        }

        $row = $this->db->table($this->table)
            ->where('slug', $slug)
            ->where('status', self::STATUS_PUBLISHED)
            ->get()
            ->getRowArray();

        return is_array($row) ? $row : null;
    }

    /**
     * Published posts for the public blog, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function publishedPosts(): array
    {
        $rows = $this->db->table($this->table)
            ->select('blocks.title, blocks.slug, blocks.content, blocks.excerpt, blocks.author_name, blocks.og_image, blocks.published_at, categories.name AS category_name')
            ->join('categories', 'categories.id = blocks.category_id', 'left')
            ->where('blocks.status', self::STATUS_PUBLISHED)
            ->orderBy('blocks.published_at', 'DESC')
            ->orderBy('blocks.id', 'DESC')
            ->get()
            ->getResultArray();

        return array_values($rows);
    }

    /**
     * Published pages that ask to be indexed.
     *
     * @return list<array<string, mixed>>
     */
    public function indexable(): array
    {
        $rows = $this->db->table($this->table)
            ->select('slug, updated_at')
            ->where('status', self::STATUS_PUBLISHED)
            ->where('robots_index', 'index')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        return array_values($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 5): array
    {
        $rows = $this->db->table($this->table)
            ->select('id, title, status, updated_at')
            ->orderBy('updated_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        return array_values($rows);
    }

    /**
     * @param array{q?: string, status?: string, categoryId?: int} $filters
     *
     * @return list<array<string, mixed>>
     */
    public function browse(array $filters, int $page): array
    {
        $q = (string) ($filters['q'] ?? '');
        if ($q !== '') {
            $this->groupStart()
                ->like('title', $q)
                ->orLike('slug', $q)
                ->groupEnd();
        }

        $status = (string) ($filters['status'] ?? '');
        if ($status !== '') {
            $this->where('status', $status);
        }

        $categoryId = (int) ($filters['categoryId'] ?? 0);
        if ($categoryId > 0) {
            $this->where('category_id', $categoryId);
        }

        $this->select('id, category_id, title, slug, status, updated_at');
        $this->orderBy('updated_at', 'DESC');
        $this->orderBy('id', 'DESC');

        return $this->paginate(self::PER_PAGE, 'default', $page);
    }

    /**
     * @param array<string, mixed> $event
     *
     * @return array<string, mixed>
     */
    protected function prepareBlock(array $event): array
    {
        $data   = is_array($event['data'] ?? null) ? $event['data'] : [];
        $insert = ! isset($event['id']);

        if (array_key_exists('title', $data)) {
            $data['title'] = trim(str_replace("\0", '', (string) $data['title']));
        }

        if (array_key_exists('slug', $data) || array_key_exists('title', $data)) {
            $slug = AdminSlug::from((string) ($data['slug'] ?? ''));
            if ($slug === '') {
                $slug = AdminSlug::from((string) ($data['title'] ?? ''));
            }
            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
                throw new InvalidArgumentException('Invalid slug.');
            }
            $data['slug'] = $slug;
        }

        if (array_key_exists('content', $data)) {
            $content = str_replace("\0", '', (string) $data['content']);
            $content = trim($content);
            $data['content'] = $content === '' ? null : $content;
        }

        if (array_key_exists('excerpt', $data)) {
            $excerpt = trim(str_replace("\0", '', (string) $data['excerpt']));
            $data['excerpt'] = $excerpt === '' ? null : $excerpt;
        }

        if (array_key_exists('author_name', $data) || $insert) {
            $author = trim(str_replace("\0", '', (string) ($data['author_name'] ?? '')));
            $data['author_name'] = $author === '' ? 'Admin' : $author;
        }

        if (array_key_exists('published_at', $data)) {
            $publishedAt = trim((string) $data['published_at']);
            $data['published_at'] = $publishedAt === '' ? null : $publishedAt;
        } elseif ($insert && ($data['status'] ?? '') === self::STATUS_PUBLISHED) {
            $data['published_at'] = date('Y-m-d H:i:s');
        }

        foreach (['meta_title', 'meta_description', 'og_title', 'og_description', 'og_image'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $value = trim(str_replace("\0", '', (string) $data[$field]));
            $data[$field] = $value === '' ? null : $value;
        }

        if (array_key_exists('og_image', $data) && $data['og_image'] !== null) {
            if (preg_match('/^[a-f0-9]{32}\.(?:jpe?g|png|gif|webp)$/', (string) $data['og_image']) !== 1) {
                throw new InvalidArgumentException('Invalid image.');
            }
        }

        if (array_key_exists('robots_index', $data) || $insert) {
            $index = (string) ($data['robots_index'] ?? 'index');
            if (! in_array($index, ['index', 'noindex'], true)) {
                throw new InvalidArgumentException('Invalid robots.');
            }
            $data['robots_index'] = $index;
        }

        if (array_key_exists('robots_follow', $data) || $insert) {
            $follow = (string) ($data['robots_follow'] ?? 'follow');
            if (! in_array($follow, ['follow', 'nofollow'], true)) {
                throw new InvalidArgumentException('Invalid robots.');
            }
            $data['robots_follow'] = $follow;
        }

        if (array_key_exists('status', $data) || $insert) {
            $status = (string) ($data['status'] ?? self::STATUS_DRAFT);
            if (! in_array($status, [self::STATUS_DRAFT, self::STATUS_PUBLISHED], true)) {
                throw new InvalidArgumentException('Invalid status.');
            }
            $data['status'] = $status;
        }

        if (array_key_exists('category_id', $data) || $insert) {
            $categoryId = (int) ($data['category_id'] ?? 0);
            if ($categoryId < 1 || $this->db->table('categories')->where('id', $categoryId)->countAllResults() !== 1) {
                throw new InvalidArgumentException('Invalid category.');
            }
            $data['category_id'] = $categoryId;
        }

        $event['data'] = $data;

        return $event;
    }
}
