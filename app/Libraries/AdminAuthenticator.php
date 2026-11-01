<?php

namespace App\Libraries;

use Config\AdminAuth;

class AdminAuthenticator
{
    public const FLAG     = 'admin_logged_in';
    public const ID       = 'admin_id';
    public const USERNAME = 'admin_username';

    public static function isLoggedIn(): bool
    {
        $session = session();
        $id      = $session->get(self::ID);

        return $session->get(self::FLAG) === true
            && is_numeric($id)
            && (int) $id > 0;
    }

    public static function id(): int
    {
        return (int) session()->get(self::ID);
    }

    public static function login(int $adminId, string $username): void
    {
        $session = session();
        $session->regenerate(true);
        $session->set([
            self::FLAG     => true,
            self::ID       => $adminId,
            self::USERNAME => $username,
        ]);
    }

    public static function logout(): void
    {
        $session = session();
        $session->remove([self::FLAG, self::ID, self::USERNAME]);
        // New session id, old session storage deleted. Flash data can still be saved after this.
        $session->regenerate(true);
    }

    public static function allowAttempt(string $ip): bool
    {
        $config = config(AdminAuth::class);

        return service('throttler')->check(self::throttleKey($ip), $config->maxAttempts, $config->decaySeconds);
    }

    public static function secondsUntilRetry(): int
    {
        return service('throttler')->getTokenTime();
    }

    public static function clearAttempts(string $ip): void
    {
        service('throttler')->remove(self::throttleKey($ip));
    }

    private static function throttleKey(string $ip): string
    {
        return 'admin_login_' . hash('sha256', $ip);
    }
}
