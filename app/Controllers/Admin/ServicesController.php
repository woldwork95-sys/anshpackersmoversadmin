<?php

namespace App\Controllers\Admin;

use App\Libraries\AdminSlug;
use App\Libraries\BlockHtml;
use App\Libraries\BlockImageStore;
use App\Models\ServiceModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\RedirectResponse;

class ServicesController extends AdminController
{
    public function index(): string
    {
        $filters  = $this->filters();
        $services = model(ServiceModel::class, false);
        $rows     = $services->browse([
            'q'      => $filters['q'],
            'status' => $filters['status'],
        ], $this->pageNumber());
        $services->pager?->only(['q', 'status']);

        return $this->render('admin/services/index', 'Services', 'services', [
            'services' => $rows,
            'pager'    => $services->pager,
            'filters'  => $filters,
        ]);
    }

    public function create(): string
    {
        return $this->render('admin/services/form', 'New service', 'services', [
            'service' => null,
        ]);
    }

    public function store(): RedirectResponse
    {
        $payload = $this->payload();
        if (! $this->validateData($payload, $this->rules(0), $this->messages())) {
            return $this->reject(site_url('admin/services/create'), $this->validator->getErrors());
        }
        if (service('serviceSlugHistory')->takenByOtherService($payload['slug'], 0)) {
            return $this->reject(site_url('admin/services/create'), ['slug' => 'That slug is already in use.']);
        }

        $record = $this->record($payload);
        $image  = $this->ogImage(null);
        if ($image['error'] !== null) {
            return redirect()->to(site_url('admin/services/create'))->withInput()->with('error', $image['error']);
        }
        $record['og_image']     = $image['name'];
        $record['published_at'] = $this->publishedAt($payload['status'], null);

        $error = $this->writeRow(model(ServiceModel::class, false), $record);
        if ($error !== null) {
            $this->deleteSeoFile($image['uploaded']);

            return redirect()->to(site_url('admin/services/create'))->withInput()->with('error', $error);
        }
        $this->deleteSeoFile($image['delete']);

        return redirect()->to(site_url('admin/services'))->with('success', 'Service saved.');
    }

    public function show(int $id): string
    {
        return $this->render('admin/services/show', 'Service', 'services', [
            'service' => $this->findOrFail(model(ServiceModel::class, false), $id),
        ]);
    }

    public function edit(int $id): string
    {
        return $this->render('admin/services/form', 'Edit service', 'services', [
            'service' => $this->findOrFail(model(ServiceModel::class, false), $id),
        ]);
    }

    public function update(int $id): RedirectResponse
    {
        $existing = $this->findOrFail(model(ServiceModel::class, false), $id);
        $payload  = $this->payload();

        if (! $this->validateData($payload, $this->rules($id), $this->messages())) {
            return $this->reject(site_url('admin/services/' . $id . '/edit'), $this->validator->getErrors());
        }
        if (service('serviceSlugHistory')->takenByOtherService($payload['slug'], $id)) {
            return $this->reject(site_url('admin/services/' . $id . '/edit'), ['slug' => 'That slug is already in use.']);
        }

        $record = $this->record($payload);
        $image  = $this->ogImage(isset($existing['og_image']) ? (string) $existing['og_image'] : null);
        if ($image['error'] !== null) {
            return redirect()->to(site_url('admin/services/' . $id . '/edit'))->withInput()->with('error', $image['error']);
        }
        $record['og_image']     = $image['name'];
        $record['published_at'] = $this->publishedAt($payload['status'], $existing);

        $error = $this->writeRow(model(ServiceModel::class, false), $record, $id);
        if ($error !== null) {
            $this->deleteSeoFile($image['uploaded']);

            return redirect()->to(site_url('admin/services/' . $id . '/edit'))->withInput()->with('error', $error);
        }

        $this->deleteSeoFile($image['delete']);
        service('serviceSlugHistory')->remember($id, (string) ($existing['slug'] ?? ''), (string) $record['slug']);

        return redirect()->to(site_url('admin/services'))->with('success', 'Service updated.');
    }

    public function delete(int $id): string
    {
        return $this->render('admin/services/delete', 'Delete service', 'services', [
            'service' => $this->findOrFail(model(ServiceModel::class, false), $id),
        ]);
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->findOrFail(model(ServiceModel::class, false), $id);

        try {
            model(ServiceModel::class, false)->delete($id);
        } catch (DatabaseException) {
            return redirect()->to(site_url('admin/services/' . $id . '/delete'))
                ->with('error', 'The service could not be deleted.');
        }

        return redirect()->to(site_url('admin/services'))->with('success', 'Service deleted.');
    }

    /**
     * @return array{q: string, status: string}
     */
    private function filters(): array
    {
        $status = $this->queryString('status', 20);
        if (! in_array($status, [ServiceModel::STATUS_DRAFT, ServiceModel::STATUS_PUBLISHED], true)) {
            $status = '';
        }

        return [
            'q'      => $this->queryString('q'),
            'status' => $status,
        ];
    }

    /**
     * @return array{title: string, slug: string, content: string, status: string, excerpt: string, image_alt: string, sort_order: string, meta_title: string, meta_description: string, robots_index: string, robots_follow: string, og_title: string, og_description: string}
     */
    private function payload(): array
    {
        $title = $this->postedString('title');
        $slug  = $this->postedString('slug');

        return [
            'title'            => $title,
            'slug'             => AdminSlug::from($slug !== '' ? $slug : $title),
            'content'          => $this->postedString('content'),
            'status'           => $this->postedString('status'),
            'excerpt'          => $this->postedString('excerpt'),
            'image_alt'        => $this->postedString('image_alt'),
            'sort_order'       => $this->postedString('sort_order'),
            'meta_title'       => $this->postedString('meta_title'),
            'meta_description' => $this->postedString('meta_description'),
            'robots_index'     => $this->postedString('robots_index'),
            'robots_follow'    => $this->postedString('robots_follow'),
            'og_title'         => $this->postedString('og_title'),
            'og_description'   => $this->postedString('og_description'),
        ];
    }

    /**
     * @param array{title: string, slug: string, content: string, status: string, excerpt: string, image_alt: string, sort_order: string, meta_title: string, meta_description: string, robots_index: string, robots_follow: string, og_title: string, og_description: string} $payload
     *
     * @return array<string, mixed>
     */
    private function record(array $payload): array
    {
        $order = preg_match('/^[0-9]+$/', $payload['sort_order']) === 1 ? (int) $payload['sort_order'] : 0;

        return [
            'title'            => $payload['title'],
            'slug'             => $payload['slug'],
            'content'          => service('blockHtml')->normalize($payload['content']),
            'status'           => $payload['status'],
            'excerpt'          => $payload['excerpt'],
            'image_alt'        => $payload['image_alt'],
            'sort_order'       => $order,
            'meta_title'       => $payload['meta_title'],
            'meta_description' => $payload['meta_description'],
            'robots_index'     => $payload['robots_index'] !== '' ? $payload['robots_index'] : 'index',
            'robots_follow'    => $payload['robots_follow'] !== '' ? $payload['robots_follow'] : 'follow',
            'og_title'         => $payload['og_title'],
            'og_description'   => $payload['og_description'],
        ];
    }

    /**
     * @param array<string, mixed>|null $existing
     */
    private function publishedAt(string $status, ?array $existing): ?string
    {
        $current = is_array($existing) ? trim((string) ($existing['published_at'] ?? '')) : '';
        if ($current !== '') {
            return $current;
        }

        if ($status === ServiceModel::STATUS_PUBLISHED) {
            return date('Y-m-d H:i:s');
        }

        return null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function rules(int $id): array
    {
        $unique = $id > 0
            ? 'is_unique[services.slug,id,' . $id . ']'
            : 'is_unique[services.slug]';

        return [
            'title' => [
                'label' => 'Title',
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
            'content' => [
                'label' => 'Content',
                'rules' => 'permit_empty|max_length[' . BlockHtml::MAX_LENGTH . ']',
            ],
            'excerpt' => [
                'label' => 'Excerpt',
                'rules' => 'permit_empty|max_length[300]',
            ],
            'image_alt' => [
                'label' => 'Image description',
                'rules' => 'permit_empty|max_length[191]',
            ],
            'sort_order' => [
                'label' => 'Order',
                'rules' => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[9999]',
            ],
            'status' => [
                'label'  => 'Status',
                'rules'  => 'required|in_list[' . ServiceModel::STATUS_DRAFT . ',' . ServiceModel::STATUS_PUBLISHED . ']',
                'errors' => [
                    'in_list' => 'Choose a valid status.',
                ],
            ],
            'meta_title' => [
                'label' => 'Meta title',
                'rules' => 'permit_empty|max_length[' . \App\Libraries\BlockSeo::TITLE_LIMIT . ']',
            ],
            'meta_description' => [
                'label' => 'Meta description',
                'rules' => 'permit_empty|max_length[' . \App\Libraries\BlockSeo::DESCRIPTION_LIMIT . ']',
            ],
            'og_title' => [
                'label' => 'Open Graph title',
                'rules' => 'permit_empty|max_length[' . \App\Libraries\BlockSeo::TITLE_LIMIT . ']',
            ],
            'og_description' => [
                'label' => 'Open Graph description',
                'rules' => 'permit_empty|max_length[' . \App\Libraries\BlockSeo::DESCRIPTION_LIMIT . ']',
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
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [];
    }

    /**
     * @return array{error: ?string, name: ?string, delete: ?string, uploaded: ?string}
     */
    private function ogImage(?string $current): array
    {
        $current = is_string($current) && $current !== '' ? $current : null;
        $remove  = $this->request->getPost('remove_og_image') === '1';
        $file    = $this->request->getFile('og_image');
        $hasFile = $file instanceof UploadedFile && $file->getError() !== UPLOAD_ERR_NO_FILE;

        if ($hasFile) {
            $stored = (new BlockImageStore(null, FCPATH . 'uploads/seo'))->store($file);
            if (! $stored['ok']) {
                return [
                    'error'    => $stored['error'],
                    'name'     => $current,
                    'delete'   => null,
                    'uploaded' => null,
                ];
            }

            return [
                'error'    => null,
                'name'     => $stored['name'],
                'delete'   => $current,
                'uploaded' => $stored['name'],
            ];
        }

        if ($remove) {
            return [
                'error'    => null,
                'name'     => null,
                'delete'   => $current,
                'uploaded' => null,
            ];
        }

        return [
            'error'    => null,
            'name'     => $current,
            'delete'   => null,
            'uploaded' => null,
        ];
    }

    private function deleteSeoFile(?string $filename): void
    {
        if ($filename === null || preg_match('/^[a-f0-9]{32}\.(?:jpe?g|png|gif|webp)$/', $filename) !== 1) {
            return;
        }

        $directory = realpath(FCPATH . 'uploads/seo');
        $path      = $directory === false ? false : realpath($directory . DIRECTORY_SEPARATOR . $filename);
        if ($directory !== false && is_string($path) && str_starts_with($path, $directory . DIRECTORY_SEPARATOR) && is_file($path)) {
            unlink($path);
        }
    }
}
