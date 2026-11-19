<?php

namespace App\Controllers\Api;

use App\Libraries\CinemaService;
use CodeIgniter\RESTful\ResourceController;

/**
 * Controller API Cinema
 * Menyediakan endpoint konsumsi layanan informasi bioskop Medan (partner Sat).
 */
class Cinema extends ResourceController
{
    protected $format = 'json';
    protected CinemaService $cinema;

    public function __construct()
    {
        $this->cinema = new CinemaService();
    }

    /**
     * GET /api/cinema/status
     * Menampilkan status integrasi (apakah terhubung ke External API atau Local Fallback DB).
     */
    public function status()
    {
        return $this->respond([
            'status'  => 200,
            'message' => 'Status Layanan Integrasi Bioskop',
            'data'    => $this->cinema->getStatus(),
        ]);
    }

    /**
     * GET /api/cinema/movies
     * Mengambil daftar film yang tayang di bioskop.
     * Query params: ?q=pencarian&anime_only=1
     */
    public function movies()
    {
        $q = $this->request->getGet('q');
        $animeOnly = (bool) $this->request->getGet('anime_only');

        $movies = $this->cinema->getMovies($q, $animeOnly);

        return $this->respond([
            'status'  => 200,
            'message' => 'Daftar film bioskop',
            'total'   => count($movies),
            'data'    => $movies,
        ]);
    }

    /**
     * GET /api/cinema/movies/{id}
     * Detail film bioskop berdasarkan ID.
     */
    public function movieDetail($id = null)
    {
        if (! ctype_digit((string) $id)) {
            return $this->failValidationError('ID film harus berupa angka.');
        }

        $movie = $this->cinema->getMovieDetail((int) $id);

        if (! $movie) {
            return $this->failNotFound("Film bioskop dengan ID {$id} tidak ditemukan.");
        }

        return $this->respond([
            'status'  => 200,
            'message' => 'Detail film bioskop',
            'data'    => $movie,
        ]);
    }

    /**
     * GET /api/cinema/showtimes
     * Mengambil jadwal tayang film di bioskop Medan.
     * Query params: ?movie_id=372058&date=2026-10-15
     */
    public function showtimes()
    {
        $movieId = $this->request->getGet('movie_id');
        $date    = $this->request->getGet('date');

        $showtimes = $this->cinema->getShowtimes(
            $movieId ? (int) $movieId : null,
            $date ? (string) $date : null
        );

        return $this->respond([
            'status'  => 200,
            'message' => 'Jadwal tayang bioskop Medan',
            'total'   => count($showtimes),
            'data'    => $showtimes,
        ]);
    }

    /**
     * GET /api/cinema/studios
     * Mengambil daftar bioskop dan studio di Medan.
     */
    public function studios()
    {
        $studios = $this->cinema->getStudios();

        return $this->respond([
            'status'  => 200,
            'message' => 'Daftar bioskop dan studio di Medan',
            'total'   => count($studios),
            'data'    => $studios,
        ]);
    }
}
