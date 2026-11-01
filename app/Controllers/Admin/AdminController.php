<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\AdminAuthenticator;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Model;
use InvalidArgumentException;
use Throwable;

abstract class AdminController extends BaseController
{
    protected $helpers = ['url', 'admin'];

    /**
     * @param array<string, mixed> $data
     */
    protected function render(string $view, string $title, string $activeNav, array $data = []): string
    {
        return view($view, array_merge($data, [
            'title'     => $title,
            'activeNav' => $activeNav,
            'username'  => (string) session(AdminAuthenticator::USERNAME),
        ]));
    }

    protected function postedString(string $field): string
    {
        $value = $this->request->getPost($field);

        if (! is_string($value)) {
            return '';
        }

        return trim(str_replace("\0", '', $value));
    }

    protected function queryString(string $field, int $max = 100): string
    {
        $value = $this->request->getGet($field);

        if (! is_string($value)) {
            return '';
        }

        $value = trim(str_replace("\0", '', $value));

        if (strlen($value) > $max) {
            $value = substr($value, 0, $max);
        }

        return $value;
    }

    protected function pageNumber(): int
    {
        $page = $this->request->getGet('page');

        if (! is_string($page) || preg_match('/^[1-9][0-9]*$/', $page) !== 1) {
            return 1;
        }

        return (int) $page;
    }

    /**
     * @return array<string, mixed>
     */
    protected function findOrFail(Model $model, int $id): array
    {
        if ($id < 1) {
            throw PageNotFoundException::forPageNotFound();
        }

        $row = $model->find($id);

        if (! is_array($row)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $row;
    }

    /**
     * @param array<string, string> $errors
     */
    protected function reject(string $url, array $errors): RedirectResponse
    {
        return redirect()->to($url)->withInput()->with('errors', $errors);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function writeRow(Model $model, array $data, ?int $id = null): ?string
    {
        try {
            $saved = $id === null
                ? $model->insert($data) !== false
                : $model->update($id, $data);

            return $saved ? null : 'The record could not be saved.';
        } catch (DatabaseException | InvalidArgumentException $exception) {
            return $this->safeWriteError($exception);
        }
    }

    private function safeWriteError(Throwable $exception): string
    {
        if ($exception instanceof InvalidArgumentException) {
            return match ($exception->getMessage()) {
                'Invalid slug.'     => 'Enter a valid slug.',
                'Invalid status.'   => 'Choose a valid status.',
                'Invalid category.' => 'Choose a valid category.',
                'Invalid robots.'   => 'Choose a valid robots directive.',
                'Invalid image.'    => 'This file type is not allowed.',
                'Invalid order.'    => 'Enter a valid order.',
                default             => 'The record could not be saved.',
            };
        }

        $message = strtolower($exception->getMessage());
        if (str_contains($message, 'unique') || str_contains($message, 'duplicate')) {
            return 'That slug is already in use.';
        }

        if (str_contains($message, 'foreign key') || str_contains($message, 'constraint')) {
            return 'Choose a valid category.';
        }

        return 'The record could not be saved.';
    }
}
