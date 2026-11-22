<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * ThrottleFilter
 * Middleware Rate Limiting berbasis CI4 Throttler untuk melindungi endpoint sensitif
 * (login, register, pemesanan kursi, dan pemicu backup) dari brute-force maupun penyalahgunaan.
 *
 * Cara pemakaian pada Routes.php:
 * - `['filter' => 'throttle:10,60']` -> maksimal 10 request per 60 detik per IP + endpoint.
 */
class ThrottleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return null;
        }

        $capacity = isset($arguments[0]) && is_numeric($arguments[0]) ? (int) $arguments[0] : 60;
        $seconds  = isset($arguments[1]) && is_numeric($arguments[1]) ? (int) $arguments[1] : 60;

        $throttler = service('throttler');
        $key       = 'rate_' . md5($request->getIPAddress() . '|' . $request->getUri()->getPath());

        if ($throttler->check($key, $capacity, $seconds) === false) {
            $retryAfter = max(1, (int) $throttler->getTokenTime());

            return service('response')
                ->setStatusCode(429)
                ->setHeader('Retry-After', (string) $retryAfter)
                ->setJSON([
                    'status'  => 429,
                    'message' => "Terlalu banyak permintaan. Silakan coba lagi dalam {$retryAfter} detik.",
                ]);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Tidak ada aksi tambahan setelah respons
    }
}
