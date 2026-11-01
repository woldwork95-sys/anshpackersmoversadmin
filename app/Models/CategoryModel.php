<?php

namespace App\Models;

use App\Libraries\AdminSlug;
use CodeIgniter\Model;
use InvalidArgumentException;

/**
 * Categories group posts. Search fields are optional and fall back
 * to the category name and description.
 */
class CategoryModel extends Model
{
    use ForgetsPublicContent;
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const PER_PAGE        = 10;

    protected $table            = 'categories';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'name',
        'slug',
        'description',
        'meta_title',
        'meta_description',
        'robots_index',
        'robots_follow',
        'og_title',
        'og_description',
        'status',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $beforeInsert = ['prepareCategory'];
    protected $beforeUpdate = ['prepareCategory'];
    protected $afterInsert  = ['forgetPublicContent'];
    protected $afterUpdate  = ['forgetPublicContent'];
    protected $afterDelete  = ['forgetPublicContent'];

    /**
     * @param array{q?: string} $filters
     *
     * @return list<array<string, mixed>>
     */
    public function browse(array $filters, int $page): array
    {
        $q = (string) ($filters['q'] ?? '');
        if ($q !== '') {
            $this->groupStart()
                ->like('name', $q)
                ->orLike('slug', $q)
                ->groupEnd();
        }

        $this->select('id, name, slug, status, updated_at');
        $this->orderBy('name', 'ASC');
        $this->orderBy('id', 'ASC');

        return $this->paginate(self::PER_PAGE, 'default', $page);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function options(): array
    {
        $rows = $this->builder()
            ->select('id, name, status')
            ->orderBy('name', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        return array_values($rows);
    }

    public function blockCount(int $id): int
    {
        return $this->db->table('blocks')->where('category_id', $id)->countAllResults();
    }

    /**
     * @param array<string, mixed> $event
     *
     * @return array<string, mixed>
     */
    protected function prepareCategory(array $event): array
    {
        $data = is_array($event['data'] ?? null) ? $event['data'] : [];

        if (array_key_exists('name', $data)) {
            $data['name'] = trim(str_replace("\0", '', (string) $data['name']));
        }

        if (array_key_exists('slug', $data) || array_key_exists('name', $data)) {
            $slug = AdminSlug::from((string) ($data['slug'] ?? ''));
            if ($slug === '') {
                $slug = AdminSlug::from((string) ($data['name'] ?? ''));
            }
            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
                throw new InvalidArgumentException('Invalid slug.');
            }
            $data['slug'] = $slug;
        }

        if (array_key_exists('description', $data)) {
            $description = trim(str_replace("\0", '', (string) $data['description']));
            $data['description'] = $description === '' ? null : $description;
        }

        foreach (['meta_title', 'meta_description', 'og_title', 'og_description'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $value = trim(str_replace("\0", '', (string) $data[$field]));
            $data[$field] = $value === '' ? null : $value;
        }

        $insert = ! isset($event['id']);
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
            $status = (string) ($data['status'] ?? self::STATUS_ACTIVE);
            if (! in_array($status, [self::STATUS_ACTIVE, self::STATUS_INACTIVE], true)) {
                throw new InvalidArgumentException('Invalid status.');
            }
            $data['status'] = $status;
        }

        $event['data'] = $data;

        return $event;
    }
}
