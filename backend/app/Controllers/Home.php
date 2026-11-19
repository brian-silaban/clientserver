<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class Home extends BaseController
{
    /**
     * Endpoint utama Server Backend AniCine (REST API).
     * Mengembalikan informasi status layanan dalam format JSON.
     */
    public function index(): ResponseInterface
    {
        $serviceInfo = [
            'status'       => 200,
            'message'      => 'AniCine Backend REST API Server aktif',
            'service_name' => 'AniCine Backend REST API (Anime & Cinema Schedule Integration)',
            'database'     => [
                'primary' => 'PostgreSQL (db_anitrack - schema: anitrack)',
                'partner' => 'PostgreSQL / REST API (db_bioskop - schema: public)',
            ],
            'endpoints'    => [
                'GET /api/anime'             => 'Daftar katalog anime dengan paginasi (?page=1&per_page=20) & pencarian (?q=)',
                'GET /api/anime/tahun/{thn}' => 'Daftar anime berdasarkan tahun rilis',
                'GET /api/anime/theatrical'  => 'Daftar 5 anime movie tayang di bioskop + jadwal bioskop Medan',
                'GET /api/anime/{id}'        => 'Detail lengkap anime + otomatis menyematkan jadwal bioskop Medan',
                'POST /api/anime'            => 'Tambah data anime baru',
                'PUT /api/anime/{id}'        => 'Perbarui data anime berdasarkan ID',
                'DELETE /api/anime/{id}'     => 'Hapus data anime berdasarkan ID',
                'GET /api/cinema/status'     => 'Status koneksi ke layanan bioskop partner (EXTERNAL_API / LOCAL_FALLBACK)',
                'GET /api/cinema/movies'     => 'Daftar film di bioskop Medan (?anime_only=1)',
                'GET /api/cinema/movies/{id}'=> 'Detail film bioskop berdasarkan ID',
                'GET /api/cinema/showtimes'  => 'Jadwal penayangan bioskop di Medan (?movie_id= & ?date=)',
                'GET /api/cinema/studios'    => 'Daftar bioskop & studio di Medan beserta kapasitas kursi',
            ],
        ];

        return $this->response->setJSON($serviceInfo);
    }
}
