<?php

namespace App\Controllers;

use App\Libraries\CinemaService;
use CodeIgniter\HTTP\ResponseInterface;

class Home extends BaseController
{
    /**
     * Endpoint informasi root Server Backend AniCine v2.0 (REST API v1).
     */
    public function index(): ResponseInterface
    {
        $cinemaStatus = (new CinemaService())->getStatus();

        return $this->response->setJSON([
            'status'       => 200,
            'message'      => 'AniCine v2.0 REST API Server aktif',
            'version'      => 'v1',
            'api_base'     => '/api/v1',
            'cinema_mode'  => $cinemaStatus['mode'],
            'endpoints'    => [
                'public' => [
                    'POST /api/v1/auth/register',
                    'POST /api/v1/auth/login',
                    'GET  /api/v1/anime',
                    'GET  /api/v1/anime/meta',
                    'GET  /api/v1/anime/theatrical',
                    'GET  /api/v1/anime/{id}',
                    'GET  /api/v1/cinema/status',
                    'GET  /api/v1/cinema/showtimes',
                    'GET  /api/v1/cinema/showtimes/{id}/seats',
                    'GET  /api/v1/cinema/studios',
                ],
                'member_jwt' => [
                    'GET|PUT /api/v1/auth/me',
                    'GET|POST /api/v1/bookings',
                    'GET /api/v1/bookings/{id}',
                    'POST /api/v1/bookings/{id}/pay',
                    'POST /api/v1/bookings/{id}/cancel',
                    'GET /api/v1/watchlist',
                    'PUT|DELETE /api/v1/watchlist/{anime_id}',
                ],
                'admin_jwt' => [
                    'POST|PUT|DELETE /api/v1/anime',
                    'GET /api/v1/admin/dashboard',
                    'GET|POST|PUT|DELETE /api/v1/admin/theatrical',
                    'GET|PATCH /api/v1/admin/bookings',
                    'GET|PATCH /api/v1/admin/users',
                    'GET /api/v1/admin/audit-logs',
                    'GET|POST /api/v1/backups',
                    'GET /api/v1/backups/{name}/verify',
                ],
            ],
        ]);
    }
}
