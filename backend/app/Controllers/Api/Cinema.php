<?php

namespace App\Controllers\Api;

use App\Libraries\CinemaService;

/**
 * Controller API Cinema (`/api/v1/cinema/*`)
 * Menyediakan layanan baca informasi jadwal tayang & peta kursi bioskop Medan
 * khusus film anime melalui adapter `CinemaService` (Hybrid EXTERNAL_API / LOCAL_FALLBACK Read-Only).
 */
class Cinema extends BaseApiController
{
    private CinemaService $cinema;

    public function __construct()
    {
        $this->cinema = new CinemaService();
    }

    /**
     * GET /api/v1/cinema/status
     * Status koneksi integrasi bioskop (EXTERNAL_API vs LOCAL_FALLBACK Read-Only).
     */
    public function status()
    {
        return $this->respondSuccess($this->cinema->getStatus(), 'Status layanan integrasi bioskop Medan.');
    }

    /**
     * GET /api/v1/cinema/movies
     * Daftar film bioskop. Default `anime_only=1` agar client hanya melihat film anime tayang.
     */
    public function movies()
    {
        $q             = $this->request->getGet('q');
        $animeOnlyRaw  = $this->request->getGet('anime_only');
        $animeOnly     = ($animeOnlyRaw === null) ? true : filter_var($animeOnlyRaw, FILTER_VALIDATE_BOOLEAN);
        $movies        = $this->cinema->getMovies($q !== null ? (string) $q : null, $animeOnly);

        return $this->respondSuccess($movies, 'Daftar film anime di bioskop Medan.', 200, [
            'total' => count($movies),
        ]);
    }

    /**
     * GET /api/v1/cinema/showtimes
     * Jadwal penayangan KHUSUS film anime di bioskop Medan.
     * Query params: `?anime_id=`, `?movie_id=`, `?date=YYYY-MM-DD`, `?cinema=`
     */
    public function showtimes()
    {
        $animeId = $this->request->getGet('anime_id');
        $movieId = $this->request->getGet('movie_id');
        $date    = $this->request->getGet('date');
        $cinema  = $this->request->getGet('cinema');

        $resolvedMovieId = null;

        if ($movieId !== null && ctype_digit((string) $movieId)) {
            $resolvedMovieId = (int) $movieId;
        } elseif ($animeId !== null && ctype_digit((string) $animeId)) {
            // Cari cinema_movie_id dari anitrack.anime_cinema_map untuk anime_id yang diminta
            $mapped = $this->cinema->getMappedMoviesByCinemaId(true);
            foreach ($mapped as $cMovieId => $row) {
                if ((int) $row['anime_id'] === (int) $animeId) {
                    $resolvedMovieId = (int) $cMovieId;
                    break;
                }
            }

            if ($resolvedMovieId === null) {
                return $this->respondSuccess([], 'Anime ini belum memiliki jadwal tayang aktif di bioskop Medan.', 200, [
                    'total' => 0,
                ]);
            }
        }

        $showtimes = $this->cinema->getShowtimes(
            $resolvedMovieId,
            $date ? (string) $date : null,
            $cinema ? (string) $cinema : null,
            true
        );

        return $this->respondSuccess($showtimes, 'Jadwal tayang anime di bioskop Medan.', 200, [
            'total' => count($showtimes),
        ]);
    }

    /**
     * GET /api/v1/cinema/showtimes/{id}/seats
     * Pratinjau denah kursi studio & ketersediaan kursi secara real-time (terbuka untuk Guest & Member).
     */
    public function showtimeSeats($id = null)
    {
        if (! ctype_digit((string) $id)) {
            return $this->respondValidationError(['showtime_id' => 'ID jadwal tayang harus berupa angka valid.']);
        }

        $seatMap = $this->cinema->getShowtimeSeats((int) $id);
        if ($seatMap === null) {
            return $this->respondError("Jadwal tayang anime dengan ID {$id} tidak ditemukan.", 404);
        }

        return $this->respondSuccess($seatMap, 'Denah kursi studio untuk jadwal tayang anime.');
    }

    /**
     * GET /api/v1/cinema/studios
     * Daftar bioskop & studio di Kota Medan.
     */
    public function studios()
    {
        $studios = $this->cinema->getStudios();

        return $this->respondSuccess($studios, 'Daftar jaringan bioskop dan studio di Kota Medan.', 200, [
            'total' => count($studios),
        ]);
    }
}
