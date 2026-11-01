<?php

namespace App\Controllers\Admin;

use App\Libraries\AdminSlug;
use App\Libraries\BlockSeo;
use App\Models\CategoryModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\HTTP\RedirectResponse;

class CategoriesController extends AdminController
{
    public function index(): string
    {
        $q          = $this->queryString('q');
        $categories = model(CategoryModel::class, false);
        $rows       = $categories->browse(['q' => $q], $this->pageNumber());
        $categories->pager?->only(['q']);

        return $this->render('admin/categories/index', 'Categories', 'categories', [
            'categories' => $rows,
            'pager'      => $categories->pager,
            'filters'    => ['q' => $q],
        ]);
    }

    public function create(): string
    {
        return $this->render('admin/categories/form', 'New category', 'categories', [
            'category' => null,
        ]);
    }

    public function store(): RedirectResponse
    {
        $payload = $this->payload();
        if (! $this->validateData($payload, $this->rules(0), $this->messages())) {
            return $this->reject(site_url('admin/categories/create'), $this->validator->getErrors());
        }

        $error = $this->writeRow(model(CategoryModel::class, false), $this->record($payload));
        if ($error !== null) {
            return redirect()->to(site_url('admin/categories/create'))->withInput()->with('error', $error);
        }

        return redirect()->to(site_url('admin/categories'))->with('success', 'Category saved.');
    }

    public function edit(int $id): string
    {
        return $this->render('admin/categories/form', 'Edit category', 'categories', [
            'category' => $this->findOrFail(model(CategoryModel::class, false), $id),
        ]);
    }

    public function update(int $id): RedirectResponse
    {
        $this->findOrFail(model(CategoryModel::class, false), $id);
        $payload = $this->payload();

        if (! $this->validateData($payload, $this->rules($id), $this->messages())) {
            return $this->reject(site_url('admin/categories/' . $id . '/edit'), $this->validator->getErrors());
        }

        $error = $this->writeRow(model(CategoryModel::class, false), $this->record($payload), $id);
        if ($error !== null) {
            return redirect()->to(site_url('admin/categories/' . $id . '/edit'))->withInput()->with('error', $error);
        }

        return redirect()->to(site_url('admin/categories'))->with('success', 'Category updated.');
    }

    public function delete(int $id): string
    {
        $category = $this->findOrFail(model(CategoryModel::class, false), $id);
        $count    = model(CategoryModel::class, false)->blockCount($id);

        return $this->render('admin/categories/delete', 'Delete category', 'categories', [
            'category' => $category,
            'inUse'    => $count > 0,
            'message'  => $count > 0 ? $this->inUseMessage($count) : '',
        ]);
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->findOrFail(model(CategoryModel::class, false), $id);
        $count = model(CategoryModel::class, false)->blockCount($id);
        if ($count > 0) {
            return redirect()->to(site_url('admin/categories/' . $id . '/delete'))
                ->with('error', $this->inUseMessage($count));
        }

        try {
            model(CategoryModel::class, false)->delete($id);
        } catch (DatabaseException) {
            return redirect()->to(site_url('admin/categories/' . $id . '/delete'))
                ->with('error', 'This category is still used by blocks and cannot be deleted.');
        }

        return redirect()->to(site_url('admin/categories'))->with('success', 'Category deleted.');
    }

    /**
     * @return array{name: string, slug: string, description: string, status: string, meta_title: string, meta_description: string, robots_index: string, robots_follow: string, og_title: string, og_description: string}
     */
    private function payload(): array
    {
        $name = $this->postedString('name');
        $slug = $this->postedString('slug');

        return [
            'name'             => $name,
            'slug'             => AdminSlug::from($slug !== '' ? $slug : $name),
            'description'      => $this->postedString('description'),
            'status'           => $this->postedString('status'),
            'meta_title'       => $this->postedString('meta_title'),
            'meta_description' => $this->postedString('meta_description'),
            'robots_index'     => $this->postedString('robots_index'),
            'robots_follow'    => $this->postedString('robots_follow'),
            'og_title'         => $this->postedString('og_title'),
            'og_description'   => $this->postedString('og_description'),
        ];
    }

    /**
     * @param array{name: string, slug: string, description: string, status: string, meta_title: string, meta_description: string, robots_index: string, robots_follow: string, og_title: string, og_description: string} $payload
     *
     * @return array{name: string, slug: string, description: string, status: string, meta_title: string, meta_description: string, robots_index: string, robots_follow: string, og_title: string, og_description: string}
     */
    private function record(array $payload): array
    {
        return [
            'name'             => $payload['name'],
            'slug'             => $payload['slug'],
            'description'      => $payload['description'],
            'status'           => $payload['status'],
            'meta_title'       => $payload['meta_title'],
            'meta_description' => $payload['meta_description'],
            'robots_index'     => $payload['robots_index'] !== '' ? $payload['robots_index'] : 'index',
            'robots_follow'    => $payload['robots_follow'] !== '' ? $payload['robots_follow'] : 'follow',
            'og_title'         => $payload['og_title'],
            'og_description'   => $payload['og_description'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function rules(int $id): array
    {
        $unique = $id > 0
            ? 'is_unique[categories.slug,id,' . $id . ']'
            : 'is_unique[categories.slug]';

        return [
            'name' => [
                'label' => 'Name',
                'rules' => 'required|min_length[2]|max_length[191]',
            ],
            'slug' => [
                'label'  => 'Slug',
                'rules'  => 'required|max_length[191]|regex_match[/^[a-z0-9]+(?:-[a-z0-9]+)*$/]|' . $unique,
                'errors' => [
                    'is_unique'   => 'That slug is already in use.',
                    'regex_match' => 'Use lowercase letters, numbers, and hyphens.',
                ],
            ],
            'description' => [
                'label' => 'Description',
                'rules' => 'permit_empty|max_length[5000]',
            ],
            'meta_title' => [
                'label' => 'Meta title',
                'rules' => 'permit_empty|max_length[' . BlockSeo::TITLE_LIMIT . ']',
            ],
            'meta_description' => [
                'label' => 'Meta description',
                'rules' => 'permit_empty|max_length[' . BlockSeo::DESCRIPTION_LIMIT . ']',
            ],
            'og_title' => [
                'label' => 'Open Graph title',
                'rules' => 'permit_empty|max_length[' . BlockSeo::TITLE_LIMIT . ']',
            ],
            'og_description' => [
                'label' => 'Open Graph description',
                'rules' => 'permit_empty|max_length[' . BlockSeo::DESCRIPTION_LIMIT . ']',
            ],
            'robots_index' => [
                'label'  => 'Indexing',
                'rules'  => 'permit_empty|in_list[index,noindex]',
                'errors' => [
                    'in_list' => 'Choose a valid robots directive.',
                ],
            ],
            'robots_follow' => [
                'label'  => 'Following',
                'rules'  => 'permit_empty|in_list[follow,nofollow]',
                'errors' => [
                    'in_list' => 'Choose a valid robots directive.',
                ],
            ],
            'status' => [
                'label'  => 'Status',
                'rules'  => 'required|in_list[' . CategoryModel::STATUS_ACTIVE . ',' . CategoryModel::STATUS_INACTIVE . ']',
                'errors' => [
                    'in_list' => 'Choose a valid status.',
                ],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [];
    }

    private function inUseMessage(int $count): string
    {
        $label = $count === 1 ? '1 block' : $count . ' blocks';

        return 'This category is used by ' . $label . ' and cannot be deleted.';
    }
}
