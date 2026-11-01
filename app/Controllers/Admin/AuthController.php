<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\AdminAuthenticator;
use App\Models\AdminModel;
use CodeIgniter\HTTP\RedirectResponse;

class AuthController extends BaseController
{
    protected $helpers = ['url'];

    public function login(): string|RedirectResponse
    {
        if (AdminAuthenticator::isLoggedIn()) {
            return redirect()->route('admin.home');
        }

        return view('admin/auth/login', [
            'identity' => (string) (session('login_identity') ?? ''),
        ]);
    }

    public function attempt(): RedirectResponse
    {
        $payload = [
            'identity' => trim((string) $this->request->getPost('identity')),
            'password' => (string) $this->request->getPost('password'),
        ];

        if (! $this->validateData($payload, $this->rules(), $this->messages())) {
            return redirect()->route('admin.login')
                ->with('error', 'Please correct the errors below.')
                ->with('errors', $this->validator->getErrors())
                ->with('login_identity', $payload['identity']);
        }

        $ip = $this->request->getIPAddress();

        if (! AdminAuthenticator::allowAttempt($ip)) {
            $wait = AdminAuthenticator::secondsUntilRetry();

            return redirect()->route('admin.login')
                ->with('error', 'Too many login attempts. Try again in ' . $wait . ' seconds.')
                ->with('login_identity', $payload['identity']);
        }

        $admins   = model(AdminModel::class);
        $admin    = $admins->findByIdentity($payload['identity']);
        $verified = $admins->checkPassword($admin, $payload['password']);

        $adminId  = is_array($admin) ? (int) $admin['id'] : 0;
        $username = is_array($admin) ? (string) $admin['username'] : '';
        unset($admin);

        if (! $verified || $adminId < 1) {
            log_message('notice', 'Failed admin login attempt.');

            return redirect()->route('admin.login')
                ->with('error', 'Those credentials do not match our records.')
                ->with('login_identity', $payload['identity']);
        }

        AdminAuthenticator::clearAttempts($ip);
        $admins->update($adminId, [
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);
        AdminAuthenticator::login($adminId, $username);

        return redirect()->route('admin.home');
    }

    public function logout(): RedirectResponse
    {
        AdminAuthenticator::logout();

        return redirect()->route('admin.login')->with('success', 'You have been signed out.');
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function rules(): array
    {
        return [
            'identity' => [
                'label' => 'Email or username',
                'rules' => 'required|max_length[191]|regex_match[/^[A-Za-z0-9._@+\-]+$/]',
            ],
            'password' => [
                'label' => 'Password',
                'rules' => 'required|max_length[255]',
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function messages(): array
    {
        return [
            'identity' => [
                'required'    => 'Enter your email or username.',
                'max_length'  => 'Email or username must be 191 characters or fewer.',
                'regex_match' => 'Email or username contains characters that are not allowed.',
            ],
            'password' => [
                'required'   => 'Enter your password.',
                'max_length' => 'Password must be 255 characters or fewer.',
            ],
        ];
    }
}
