<?php

namespace App\Models;

use App\Libraries\AdminSlug;
use CodeIgniter\Model;
use InvalidArgumentException;

class ServiceModel extends Model
{
    use ForgetsPublicContent;
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_DRAFT     = 'draft';
    public const PER_PAGE         = 10;

    protected $table            = 'services';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'title',
        'slug',
        'excerpt',
        'content',
        'image_alt',
        'cover_path',
        'og_image',
        'meta_title',
        'meta_description',
        'robots_index',
        'robots_follow',
        'og_title',
        'og_description',
        'sort_order',
        'status',
        'published_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $beforeInsert = ['prepareService'];
    protected $beforeUpdate = ['prepareService'];
    protected $afterInsert  = ['forgetPublicContent'];
    protected $afterUpdate  = ['forgetPublicContent'];
    protected $afterDelete  = ['forgetPublicContent'];

    /**
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

    /**
     * Published services in the order shown on the website.
     *
     * @return list<array<string, mixed>>
     */
    public function publishedServices(): array
    {
        $rows = $this->db->table($this->table)
            ->where('status', self::STATUS_PUBLISHED)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        return array_values($rows);
    }

    /**
     * @param array{q?: string, status?: string} $filters
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

        $this->select('id, title, slug, status, sort_order, updated_at');
        $this->orderBy('sort_order', 'ASC');
        $this->orderBy('id', 'ASC');

        return $this->paginate(self::PER_PAGE, 'default', $page);
    }

    /**
     * @param array<string, mixed> $event
     *
     * @return array<string, mixed>
     */
    protected function prepareService(array $event): array
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
            $content = trim(str_replace("\0", '', (string) $data['content']));
            $data['content'] = $content === '' ? null : $content;
        }

        foreach (['excerpt', 'image_alt'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $value = trim(str_replace("\0", '', (string) $data[$field]));
            $data[$field] = $value === '' ? null : $value;
        }

        if (array_key_exists('cover_path', $data)) {
            $path = trim(str_replace("\0", '', (string) $data['cover_path']));
            if ($path === '') {
                $data['cover_path'] = null;
            } elseif (preg_match('#^/img/[A-Za-z0-9._-]+$#', $path) !== 1) {
                throw new InvalidArgumentException('Invalid image.');
            } else {
                $data['cover_path'] = $path;
            }
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

        if (array_key_exists('sort_order', $data) || $insert) {
            $order = (int) ($data['sort_order'] ?? 0);
            if ($order < 0 || $order > 9999) {
                throw new InvalidArgumentException('Invalid order.');
            }
            $data['sort_order'] = $order;
        }

        $event['data'] = $data;

        return $event;
    }
}
