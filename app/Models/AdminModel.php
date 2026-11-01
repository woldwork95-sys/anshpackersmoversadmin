<?php

namespace App\Models;

use CodeIgniter\Model;
use SensitiveParameter;

class AdminModel extends Model
{
    /**
     * Valid hash used only so unknown accounts still pay the password-verify cost.
     */
    private const DUMMY_PASSWORD_HASH = '$2y$10$TCct3sQbRplAA9pjfgEpSONthhuxtJd253z.z8ruuEposEUPeGUnW';

    protected $table            = 'admins';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'username',
        'email',
        'password_hash',
        'last_login_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdentity(string $identity): ?array
    {
        $identity = mb_strtolower(trim($identity));

        if ($identity === '') {
            return null;
        }

        $table = $this->db->prefixTable($this->table);
        $row   = $this->db->query(
            'SELECT id, username, email, password_hash FROM ' . $table
            . ' WHERE LOWER(username) = ? OR LOWER(email) = ? LIMIT 1',
            [$identity, $identity],
        )->getRowArray();

        return $row === null ? null : $row;
    }

    /**
     * @param array<string, mixed>|null $admin
     */
    public function checkPassword(?array $admin, #[SensitiveParameter] string $password): bool
    {
        $hash     = is_array($admin) ? (string) ($admin['password_hash'] ?? '') : '';
        $verified = password_verify($password, $hash !== '' ? $hash : self::DUMMY_PASSWORD_HASH);

        if (! is_array($admin) || ! $verified || $hash === '') {
            return false;
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $this->update($admin['id'], [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        }

        return true;
    }
}
