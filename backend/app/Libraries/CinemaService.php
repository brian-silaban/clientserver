<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Config\Services;
use Exception;

/**
 * CinemaService
 * Layanan integrasi ke API Bioskop (partner Sat).
 * 
 * Mendukung mode Hybrid:
 * 1. External API: Mengonsumsi REST API Sat via HTTP (saat beda laptop / remote).
 * 2. Local Fallback: Mengambil data langsung dari PostgreSQL 'db_bioskop' lokal (saat offline / 1 laptop).
 */
class CinemaService
{
    protected ?string $externalBaseUrl;
    protected ?BaseConnection $db = null;

    // Pemetaan ID Anime (db_anitrack) ke ID Movie Bioskop (db_bioskop)
    public const ANIME_MOVIE_MAP = [
        32281 => 372058, // Kimi no Na wa.
        28851 => 378064, // Koe no Katachi
        199   => 129,    // Sen to Chihiro no Kamikakushi (Spirited Away)
        21469 => 266396, // Stand By Me Doraemon
        31490 => 376044, // One Piece Film: Gold
    ];

    public function __construct()
    {
        $this->externalBaseUrl = rtrim(env('cinema.api.baseURL', ''), '/');
    }

    /**
     * Mendapatkan koneksi database bioskop lokal.
     */
    protected function getDb(): BaseConnection
    {
        if ($this->db === null) {
            $this->db = Database::connect('bioskop');
        }

        return $this->db;
    }

    /**
     * Memeriksa status koneksi layanan bioskop.
     */
    public function getStatus(): array
    {
        $hasExternal = ! empty($this->externalBaseUrl);
        $externalOnline = false;

        if ($hasExternal) {
            try {
                $client = Services::curlrequest(['timeout' => 2]);
                $response = $client->get($this->externalBaseUrl . '/movies');
                $externalOnline = ($response->getStatusCode() === 200);
            } catch (Exception $e) {
                $externalOnline = false;
            }
        }

        $localOnline = false;
        try {
            $res = $this->getDb()->query('SELECT 1')->getRow();
            $localOnline = ($res !== null);
        } catch (Exception $e) {
            $localOnline = false;
        }

        return [
            'mode'            => ($hasExternal && $externalOnline) ? 'EXTERNAL_API' : 'LOCAL_FALLBACK',
            'external_url'    => $this->externalBaseUrl ?: '(Belum diatur / menggunakan db lokal)',
            'external_online' => $externalOnline,
            'local_db_online' => $localOnline,
            'database_target' => 'db_bioskop',
            'city_target'     => 'Medan (Sumatera Utara)',
        ];
    }

    /**
     * Mengambil daftar film bioskop (opsional: filter pencarian & hanya anime).
     */
    public function getMovies(?string $search = null, bool $animeOnly = false): array
    {
        // 1. Coba lewat External API jika aktif
        if (! empty($this->externalBaseUrl)) {
            try {
                $client = Services::curlrequest(['timeout' => 3]);
                $url = $this->externalBaseUrl . '/movies' . ($search ? '?q=' . urlencode($search) : '');
                $response = $client->get($url);
                if ($response->getStatusCode() === 200) {
                    $json = json_decode($response->getBody(), true);
                    $data = $json['data'] ?? $json;
                    if (is_array($data)) {
                        return $this->enrichMoviesList($data, $animeOnly);
                    }
                }
            } catch (Exception $e) {
                // Fallback ke database lokal
            }
        }

        // 2. Fallback: Query ke View db_bioskop lokal
        $builder = $this->getDb()->table('public.v_movies_lengkap_3nf');
        if ($search) {
            $builder->groupStart()
                ->like('LOWER(title)', strtolower($search))
                ->orLike('LOWER(original_title)', strtolower($search))
                ->groupEnd();
        }

        $movies = $builder->orderBy('popularity', 'DESC')->get()->getResultArray();

        return $this->enrichMoviesList($movies, $animeOnly);
    }

    /**
     * Memperkaya data film dengan metadata anime jika film tersebut adalah anime.
     */
    protected function enrichMoviesList(array $movies, bool $animeOnly = false): array
    {
        $cinemaMovieIds = array_values(self::ANIME_MOVIE_MAP);
        $result = [];

        foreach ($movies as $movie) {
            $movieId = (int) ($movie['movie_id'] ?? $movie['id'] ?? 0);
            $isAnime = in_array($movieId, $cinemaMovieIds, true) || (($movie['original_language'] ?? '') === 'ja');

            if ($animeOnly && ! $isAnime) {
                continue;
            }

            // Cari anime_id yang sesuai
            $animeId = array_search($movieId, self::ANIME_MOVIE_MAP, true) ?: null;

            $movie['is_anime'] = $isAnime;
            $movie['matched_anime_id'] = $animeId;
            $result[] = $movie;
        }

        return $result;
    }

    /**
     * Mengambil detail satu film bioskop.
     */
    public function getMovieDetail(int $movieId): ?array
    {
        // 1. External API
        if (! empty($this->externalBaseUrl)) {
            try {
                $client = Services::curlrequest(['timeout' => 3]);
                $response = $client->get($this->externalBaseUrl . "/movies/{$movieId}");
                if ($response->getStatusCode() === 200) {
                    $json = json_decode($response->getBody(), true);
                    return $json['data'] ?? $json;
                }
            } catch (Exception $e) {
                // Fallback ke local DB
            }
        }

        // 2. Local DB
        $movie = $this->getDb()->table('public.v_movies_lengkap_3nf')
            ->where('movie_id', $movieId)
            ->get()
            ->getRowArray();

        if (! $movie) {
            return null;
        }

        $movie['is_anime'] = in_array($movieId, array_values(self::ANIME_MOVIE_MAP), true) || (($movie['original_language'] ?? '') === 'ja');
        $movie['matched_anime_id'] = array_search($movieId, self::ANIME_MOVIE_MAP, true) ?: null;

        return $movie;
    }

    /**
     * Mengambil jadwal tayang bioskop (bisa difilter berdasarkan movie_id).
     */
    public function getShowtimes(?int $movieId = null, ?string $date = null): array
    {
        // 1. External API
        if (! empty($this->externalBaseUrl)) {
            try {
                $client = Services::curlrequest(['timeout' => 3]);
                $query = [];
                if ($movieId) $query['movie_id'] = $movieId;
                if ($date) $query['date'] = $date;
                $url = $this->externalBaseUrl . '/showtimes' . (! empty($query) ? '?' . http_build_query($query) : '');
                $response = $client->get($url);
                if ($response->getStatusCode() === 200) {
                    $json = json_decode($response->getBody(), true);
                    return $json['data'] ?? $json;
                }
            } catch (Exception $e) {
                // Fallback ke local DB
            }
        }

        // 2. Local DB
        $builder = $this->getDb()->table('public.showtimes sh')
            ->select('
                sh.showtime_id,
                sh.show_date,
                sh.start_time,
                sh.ticket_price,
                sh.movie_id,
                m.title AS movie_title,
                m.age_rating,
                m.duration_minutes,
                m.poster_path,
                st.studio_id,
                st.cinema_name,
                st.kecamatan,
                st.address,
                st.studio_name,
                st.studio_type,
                st.capacity AS total_capacity,
                COUNT(bs.booking_seat_id) AS seats_booked,
                (st.capacity - COUNT(bs.booking_seat_id)) AS seats_available
            ')
            ->join('public.movies m', 'sh.movie_id = m.id')
            ->join('public.studios st', 'sh.studio_id = st.studio_id')
            ->join('public.booking_seats bs', 'sh.showtime_id = bs.showtime_id', 'left')
            ->groupBy('sh.showtime_id, m.id, st.studio_id');

        if ($movieId) {
            $builder->where('sh.movie_id', $movieId);
        }

        if ($date) {
            $builder->where('sh.show_date', $date);
        }

        return $builder->orderBy('sh.show_date', 'ASC')
            ->orderBy('sh.start_time', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Mengambil daftar studio & bioskop di Medan.
     */
    public function getStudios(): array
    {
        return $this->getDb()->table('public.studios')
            ->orderBy('cinema_name', 'ASC')
            ->orderBy('studio_id', 'ASC')
            ->get()
            ->getResultArray();
    }
}
