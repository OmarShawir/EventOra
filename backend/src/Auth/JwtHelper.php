<?php

namespace App\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Thin wrapper around firebase/php-jwt matching how AuthController and
 * JwtAuthMiddleware use it: issue() takes a users-table row and returns a
 * signed token string; verify() takes that token back and returns the
 * payload as an array (['sub' => ..., 'role' => ..., ...]).
 */
class JwtHelper
{
    private const ALGORITHM = 'HS256';

    private static function secret(): string
    {
        return getenv('JWT_SECRET') ?: ($_ENV['JWT_SECRET'] ?? '');
    }

    private static function expirySeconds(): int
    {
        $value = getenv('JWT_EXPIRY_SECONDS') ?: ($_ENV['JWT_EXPIRY_SECONDS'] ?? '86400');
        return (int) $value;
    }

    public static function issue(array $user): string
    {
        $now = time();

        $payload = [
            'sub'     => (int) $user['id'],
            'email'   => $user['email'],
            'role'    => $user['role'],
            'name'    => $user['name'],
            'society' => $user['society'] ?? null,
            'iat'     => $now,
            'exp'     => $now + self::expirySeconds(),
        ];

        return JWT::encode($payload, self::secret(), self::ALGORITHM);
    }

    /** @return array<string, mixed> */
    public static function verify(string $token): array
    {
        $decoded = JWT::decode($token, new Key(self::secret(), self::ALGORITHM));
        return (array) $decoded;
    }
}
