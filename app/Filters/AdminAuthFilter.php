<?php

namespace App\Filters;

use App\Libraries\AdminAuthenticator;
use App\Models\AdminModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AdminAuthFilter implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if ($this->isLoginRequest($request)) {
            return null;
        }

        if (! AdminAuthenticator::isLoggedIn()) {
            return redirect()->route('admin.login')->with('error', 'Please sign in to continue.');
        }

        $admin = model(AdminModel::class)
            ->select('id')
            ->where('id', AdminAuthenticator::id())
            ->first();

        if ($admin === null) {
            AdminAuthenticator::logout();

            return redirect()->route('admin.login')->with('error', 'Please sign in to continue.');
        }

        return null;
    }

    /**
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function isLoginRequest(RequestInterface $request): bool
    {
        if (! $request instanceof IncomingRequest) {
            return false;
        }

        $path = strtolower(trim($request->getPath(), '/'));

        return $path === 'admin/login';
    }
}
