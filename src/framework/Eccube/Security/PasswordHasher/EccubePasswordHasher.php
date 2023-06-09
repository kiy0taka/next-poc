<?php

namespace Eccube\Security\PasswordHasher;

use Eccube\Common\EccubeConfig;
use Symfony\Component\PasswordHasher\LegacyPasswordHasherInterface;

class EccubePasswordHasher implements LegacyPasswordHasherInterface
{
    public const AUTH_TYPE_PLAIN = 'PLAIN';

    protected string $auth_magic;
    protected string $auth_type;
    protected string $password_hash_algos;

    public function __construct(string $auth_magic, string $auth_type, string $password_hash_algos)
    {
        $this->auth_magic = $auth_magic;
        $this->auth_type = $auth_type;
        $this->password_hash_algos = $password_hash_algos;
    }

    public function hash(string $plainPassword, string $salt = null): string
    {
        $salt = $salt ?? '';
        if ($salt === '') {
            $salt = $this->auth_magic;
        }
        if ($this->auth_type == self::AUTH_TYPE_PLAIN) {
            $res = $plainPassword;
        } else {
            $res = hash_hmac($this->password_hash_algos, $plainPassword.':'.$this->auth_magic, $salt);
        }

        return $res;
    }

    public function verify(string $hashedPassword, string $plainPassword, string $salt = null): bool
    {
        if ($hashedPassword === '') {
            return false;
        }

        if ($this->auth_type == self::AUTH_TYPE_PLAIN) {
            if ($plainPassword === $hashedPassword) {
                return true;
            }
        } else {
            // 旧バージョン(2.11未満)からの移行を考慮
            if (empty($salt)) {
                $hash = sha1($plainPassword.':'.$this->auth_magic);
            } else {
                $hash = $this->hash($plainPassword, $salt);
            }

            if ($hash === $hashedPassword) {
                return true;
            }
        }

        return false;
    }

    public function needsRehash(string $hashedPassword): bool
    {
        return password_needs_rehash($hashedPassword, PASSWORD_DEFAULT);
    }

    public function setAuthMagic(string $auth_magic): void
    {
        $this->auth_magic = $auth_magic;
    }

    public function createSalt(int $length = 5): string
    {
        return bin2hex(openssl_random_pseudo_bytes($length));
    }
}
