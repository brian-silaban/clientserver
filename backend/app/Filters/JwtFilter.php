<?php

namespace App\Filters;

use App\Services\JwtService;
use App\Services\RlsContextService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * JwtFilter
 * Middleware autentikasi Bearer JWT dan Role-Based Access Control (RBAC).
 *
 * Cara pemakaian pada Routes.php:
 * - `['filter' => 'jwt:user,admin']` : Member atau Admin yang sudah login.
 * - `['filter' => 'jwt:admin']`      : Khusus Administrator.
 */
class JwtFilter implements FilterInterface
{
    /**
     * Memvalidasi header Authorization Bearer JWT dan memeriksa role yang diizinkan.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        RlsContextService::setAuthUser(null);

        // Lewati preflight CORS OPTIONS
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return null;
        }

        $authHeader = $request->getHeaderLine('Authorization');
        if (! preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $matches)) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 401,
                    'message' => 'Token autentikasi tidak ditemukan. Silakan masuk terlebih dahulu.',
                ]);
        }

        $jwt  = new JwtService();
        $user = $jwt->verifyToken($matches[1]);

        if ($user === null) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 401,
                    'message' => 'Token autentikasi tidak valid atau sudah kedaluwarsa. Silakan masuk kembali.',
                ]);
        }

        // Periksa kecocokan role jika filter mencantumkan argumen role (mis. jwt:admin)
        if (! empty($arguments) && ! in_array($user['role'], $arguments, true)) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON([
                    'status'  => 403,
                    'message' => 'Akses ditolak. Peran akun Anda tidak memiliki izin untuk endpoint ini.',
                ]);
        }

        // Simpan identitas user agar controller & RlsContextService dapat meneruskannya ke PostgreSQL
        RlsContextService::setAuthUser($user);

        return null;
    }

    /**
     * Membersihkan konteks autentikasi setelah respons selesai dibentuk.
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        RlsContextService::setAuthUser(null);
    }
}
