<?php

use App\Libraries\AdminAuthenticator;
use App\Models\AdminModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * @internal
 */
final class AdminAuthTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';

    private int $adminId;
    private string $password = 'Correct-Horse-9';
    private string $csrfHash;

    protected function setUp(): void
    {
        parent::setUp();

        Services::resetSingle('throttler');
        $this->csrfHash = bin2hex(random_bytes(16));
        $this->adminId  = (int) model(AdminModel::class)->insert([
            'username'      => 'admin',
            'email'         => 'admin@example.com',
            'password_hash' => password_hash($this->password, PASSWORD_DEFAULT),
        ]);
    }

    public function testLoginPageRendersForm(): void
    {
        $result = $this->get('/admin/login');

        $result->assertOK();
        $result->assertSee('Email or username');
        $result->assertSee('Password');
        $result->assertSee('csrf_test_name');
        $result->assertDontSee($this->storedHash());
    }

    public function testValidLogin(): void
    {
        $result = $this->postLogin([
            'identity' => 'admin',
            'password' => $this->password,
        ]);

        $result->assertRedirectTo(site_url('admin'));
        $result->assertSessionHas(AdminAuthenticator::FLAG, true);
        $result->assertSessionHas(AdminAuthenticator::ID, $this->adminId);
        $result->assertSessionHas(AdminAuthenticator::USERNAME, 'admin');
        $result->assertSessionMissing('password');
        $result->assertSessionMissing('password_hash');
        $this->assertTrue(service('session')->didRegenerate);
        $this->assertStringNotContainsString($this->password, (string) json_encode($_SESSION));
        $this->assertStringNotContainsString($this->storedHash(), (string) json_encode($_SESSION));
        $this->assertNotNull($this->storedAdmin()['last_login_at']);
        $this->assertNotSame($this->password, $this->storedHash());
        $this->assertTrue(password_verify($this->password, $this->storedHash()));
    }

    public function testValidLoginWithEmailIgnoringCase(): void
    {
        $result = $this->postLogin([
            'identity' => 'Admin@Example.com',
            'password' => $this->password,
        ]);

        $result->assertRedirectTo(site_url('admin'));
        $result->assertSessionHas(AdminAuthenticator::FLAG, true);
        $result->assertSessionHas(AdminAuthenticator::USERNAME, 'admin');
    }

    public function testLoginIgnoresSuppliedRedirectUrl(): void
    {
        $result = $this->postLogin([
            'identity' => 'admin',
            'password' => $this->password,
            'redirect' => 'https://evil.example/phish',
            'return'   => 'http://evil.example/steal',
        ]);

        $result->assertRedirectTo(site_url('admin'));
        $this->assertStringNotContainsString('evil.example', (string) $result->getRedirectUrl());
    }

    public function testInvalidPassword(): void
    {
        $result = $this->postLogin([
            'identity' => 'admin',
            'password' => 'wrong-password',
        ]);

        $result->assertRedirectTo(site_url('admin/login'));
        $result->assertSessionHas('error', 'Those credentials do not match our records.');
        $result->assertSessionMissing(AdminAuthenticator::FLAG);
        $result->assertSessionMissing('password');
        $result->assertSessionMissing('password_hash');
        $result->assertSessionMissing('_ci_old_input');
        $this->assertStringNotContainsString('wrong-password', $result->getBody());
        $this->assertStringNotContainsString($this->storedHash(), $result->getBody());
        $this->assertStringNotContainsString('wrong-password', (string) json_encode($_SESSION));
        $this->assertTrue(password_verify($this->password, $this->storedHash()));
    }

    public function testUnknownUser(): void
    {
        $result = $this->postLogin([
            'identity' => 'nobody',
            'password' => 'wrong-password',
        ]);

        $result->assertRedirectTo(site_url('admin/login'));
        $result->assertSessionHas('error', 'Those credentials do not match our records.');
        $result->assertSessionMissing(AdminAuthenticator::FLAG);
        $result->assertSessionMissing('password_hash');
        $this->assertSame(0, $this->db->table('admins')->where('username', 'nobody')->countAllResults());
    }

    public function testMissingCredentials(): void
    {
        $result = $this->postLogin([
            'identity' => '',
            'password' => '',
        ]);

        $result->assertRedirectTo(site_url('admin/login'));
        $result->assertSessionHas('error', 'Please correct the errors below.');
        $result->assertSessionHas('errors');
        $result->assertSessionMissing(AdminAuthenticator::FLAG);

        $errors = $_SESSION['errors'];
        $this->assertSame('Enter your email or username.', $errors['identity']);
        $this->assertSame('Enter your password.', $errors['password']);
    }

    public function testLogout(): void
    {
        Services::resetSingle('security');

        $result = $this->withSession($this->authSession())->post('/admin/logout', [
            'csrf_test_name' => $this->csrfHash,
        ]);

        $result->assertRedirectTo(site_url('admin/login'));
        $result->assertSessionHas('success', 'You have been signed out.');
        $result->assertSessionMissing(AdminAuthenticator::FLAG);
        $result->assertSessionMissing(AdminAuthenticator::ID);
        $result->assertSessionMissing(AdminAuthenticator::USERNAME);
    }

    public function testProtectedRoutesWithoutAuthentication(): void
    {
        $home = $this->get('/admin');
        $home->assertRedirectTo(site_url('admin/login'));
        $home->assertSessionHas('error', 'Please sign in to continue.');

        Services::resetSingle('security');
        $logout = $this->post('/admin/logout');
        $logout->assertRedirectTo(site_url('admin/login'));
        $logout->assertSessionHas('error', 'Please sign in to continue.');
        $logout->assertSessionMissing(AdminAuthenticator::FLAG);
    }

    public function testAuthenticatedAccess(): void
    {
        $result = $this->withSession($this->authSession())->get('/admin');

        $result->assertOK();
        $result->assertSee('Signed in as admin');
        $result->assertSee('Sign out');
        $result->assertDontSee($this->storedHash());
        $result->assertDontSee($this->password);
    }

    public function testAuthenticatedUserIsRedirectedAwayFromLogin(): void
    {
        $result = $this->withSession($this->authSession())->get('/admin/login');

        $result->assertRedirectTo(site_url('admin'));
    }

    public function testLoginRejectsMissingCsrfToken(): void
    {
        Services::resetSingle('security');

        $result = $this->post('/admin/login', [
            'identity' => 'admin',
            'password' => $this->password,
        ]);

        $result->assertRedirect();
        $result->assertSessionHas('error', 'The action you requested is not allowed.');
        $result->assertSessionMissing(AdminAuthenticator::FLAG);
        $result->assertSessionMissing('password');
        $this->assertStringNotContainsString($this->password, $result->getBody());
        $this->assertStringNotContainsString($this->password, (string) json_encode($_SESSION));
    }

    public function testLoginIsThrottledAfterRepeatedFailures(): void
    {
        service('throttler')->setTestTime(1_700_000_000);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $result = $this->postLogin([
                'identity' => 'admin',
                'password' => 'wrong-password',
            ]);

            $result->assertSessionHas('error', 'Those credentials do not match our records.');
            $result->assertSessionMissing(AdminAuthenticator::FLAG);
        }

        $blocked = $this->postLogin([
            'identity' => 'admin',
            'password' => 'wrong-password',
        ]);

        $blocked->assertRedirectTo(site_url('admin/login'));
        $blocked->assertSessionMissing(AdminAuthenticator::FLAG);
        $this->assertStringContainsString('Too many login attempts.', (string) ($_SESSION['error'] ?? ''));
        $this->assertStringNotContainsString('wrong-password', (string) json_encode($_SESSION));
        $this->assertStringNotContainsString($this->storedHash(), (string) json_encode($_SESSION));
    }

    /**
     * @param array<string, string> $overrides
     */
    private function postLogin(array $overrides): \CodeIgniter\Test\TestResponse
    {
        Services::resetSingle('security');

        return $this->withSession([
            'csrf_test_name' => $this->csrfHash,
        ])->post('/admin/login', array_merge([
            'csrf_test_name' => $this->csrfHash,
            'identity'       => '',
            'password'       => '',
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function authSession(): array
    {
        return [
            AdminAuthenticator::FLAG     => true,
            AdminAuthenticator::ID       => $this->adminId,
            AdminAuthenticator::USERNAME => 'admin',
            'csrf_test_name'             => $this->csrfHash,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function storedAdmin(): array
    {
        return $this->db->table('admins')->where('id', $this->adminId)->get()->getRowArray();
    }

    private function storedHash(): string
    {
        return (string) $this->storedAdmin()['password_hash'];
    }
}
