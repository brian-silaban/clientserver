<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Konfigurasi Cross-Origin Resource Sharing (CORS) AniCine v2.0
 *
 * Membaca `cors.allowedOrigins` dari `.env` (dipisahkan koma) agar aman di lokal maupun VM Azure.
 */
class Cors extends BaseConfig
{
    /**
     * @var array{
     *      allowedOrigins: list<string>,
     *      allowedOriginsPatterns: list<string>,
     *      supportsCredentials: bool,
     *      allowedHeaders: list<string>,
     *      exposedHeaders: list<string>,
     *      allowedMethods: list<string>,
     *      maxAge: int,
     *  }
     */
    public array $default = [
        'allowedOrigins'         => ['http://localhost:8084', 'http://127.0.0.1:8084'],
        'allowedOriginsPatterns' => [],
        'supportsCredentials'    => false,
        'allowedHeaders'         => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With'],
        'exposedHeaders'         => ['Retry-After'],
        'allowedMethods'         => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'maxAge'                 => 7200,
    ];

    public function __construct()
    {
        parent::__construct();

        // Override origin yang diizinkan dari .env (contoh: "http://localhost:8084,http://70.153.136.38:8084")
        $rawOrigins = trim((string) env('cors.allowedOrigins', ''));
        if ($rawOrigins !== '') {
            $origins = array_values(array_filter(array_map('trim', explode(',', $rawOrigins))));
            if ($origins !== []) {
                $this->default['allowedOrigins'] = $origins;
            }
        }
    }
}
