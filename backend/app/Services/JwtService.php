<?php

namespace App\Services;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * JwtService
 * Layanan pembuatan dan verifikasi JSON Web Token (JWT) HS256 untuk autentikasi API AniCine.
 */
class JwtService
{
    private string $secret;
    private int $ttl;

    public function __construct()
    {
        $secret = (string) env('jwt.secret', '');
        if (strlen($secret) < 32) {
            throw new \RuntimeException('Konfigurasi jwt.secret pada .env belum diatur atau terlalu pendek (minimal 32 karakter).');
        }

        $this->secret = $secret;
        $this->ttl    = max(300, (int) env('jwt.ttl', 86400));
    }

    /**
     * Menghasilkan access token JWT untuk akun pengguna yang berhasil login/register.
     *
     * @param array<string, mixed> $user
     * @return array{access_token: string, token_type: string, expires_in: int}
     */
    public function issueToken(array $user): array
    {
        $issuedAt  = time();
        $expiresAt = $issuedAt + $this->ttl;

        $payload = [
            'iss'      => 'anicine-api',
            'iat'      => $issuedAt,
            'exp'      => $expiresAt,
            'sub'      => (int) $user['user_id'],
            'username' => (string) $user['username'],
            'email'    => (string) ($user['email'] ?? ''),
            'role'     => (string) ($user['role'] ?? 'user'),
        ];

        $token = JWT::encode($payload, $this->secret, 'HS256');

        return [
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => $this->ttl,
        ];
    }

    /**
     * Memverifikasi signature dan masa berlaku JWT lalu mengembalikan data klaim user.
     *
     * @return array{user_id: int, username: string, email: string, role: string}|null
     */
    public function verifyToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));

            if (! isset($decoded->sub, $decoded->role)) {
                return null;
            }

            return [
                'user_id'  => (int) $decoded->sub,
                'username' => (string) ($decoded->username ?? ''),
                'email'    => (string) ($decoded->email ?? ''),
                'role'     => (string) $decoded->role,
            ];
        } catch (Exception $e) {
            return null;
        }
    }
}
