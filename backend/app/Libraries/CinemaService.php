<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Config\Services;
use Exception;
use RuntimeException;

/**
 * CinemaService
 * Adapter integrasi ke layanan bioskop Medan milik partner (Satria).
 *
 * Mendukung 2 mode (Hybrid & Fail-Safe):
 * 1. EXTERNAL_API   : Target produksi — konsumsi REST API buatan Satria via HTTP (`cinema.api.baseURL`).
 * 2. LOCAL_FALLBACK : Mode sementara — READ-ONLY ke `db_bioskop` (TIDAK pernah menulis/mengubah
 *                     database milik Satria). Penguncian kursi AniCine disimpan mandiri di
 *                     `db_anitrack.anitrack.cinema_booking_seats`.
 */
class CinemaService
{
    protected string $externalBaseUrl;
    protected ?BaseConnection $bioskopDb  = null;
    protected ?BaseConnection $anitrackDb = null;

    public function __construct()
    {
        $this->externalBaseUrl = rtrim((string) env('cinema.api.baseURL', ''), '/');
    }

    /**
     * Koneksi READ-ONLY ke database bioskop partner (hanya untuk mode LOCAL_FALLBACK).
     */
    protected function getBioskopDb(): BaseConnection
    {
        if ($this->bioskopDb === null) {
            $this->bioskopDb = Database::connect('bioskop');
        }

        return $this->bioskopDb;
    }

    /**
     * Koneksi ke database utama AniCine (`db_anitrack`).
     */
    protected function getAnitrackDb(): BaseConnection
    {
        if ($this->anitrackDb === null) {
            $this->anitrackDb = Database::connect('default');
        }

        return $this->anitrackDb;
    }

    /**
     * Mengambil pemetaan dinamis anime <-> movie bioskop dari `anitrack.anime_cinema_map`.
     *
     * @param bool $activeOnly Jika true, hanya mengambil status NOW_SHOWING & UPCOMING.
     * @return array<int, array<string, mixed>> Diindeks berdasarkan cinema_movie_id.
     */
    public function getMappedMoviesByCinemaId(bool $activeOnly = true): array
    {
        $sql = 'SELECT m.map_id, m.anime_id, m.cinema_movie_id, m.screening_status, m.is_featured, m.release_note, a.title AS anime_title, a.image_url AS anime_image_url '
            . 'FROM anitrack.anime_cinema_map m '
            . 'JOIN anitrack.anime a ON a.anime_id = m.anime_id ';

        if ($activeOnly) {
            $sql .= "WHERE m.screening_status IN ('NOW_SHOWING', 'UPCOMING') ";
        }

        $sql .= 'ORDER BY m.is_featured DESC, m.map_id ASC';

        $rows = $this->getAnitrackDb()->query($sql)->getResultArray();
        $map  = [];
        foreach ($rows as $row) {
            $map[(int) $row['cinema_movie_id']] = $row;
        }

        return $map;
    }

    /**
     * Memeriksa status koneksi layanan bioskop (External REST API Satria atau Fallback Read-Only).
     */
    public function getStatus(): array
    {
        $hasExternal    = $this->externalBaseUrl !== '';
        $externalOnline = false;

        if ($hasExternal) {
            try {
                $client         = Services::curlrequest(['timeout' => 2]);
                $response       = $client->get($this->externalBaseUrl . '/movies');
                $externalOnline = ($response->getStatusCode() === 200);
            } catch (Exception $e) {
                $externalOnline = false;
            }
        }

        $localOnline = false;
        try {
            $row         = $this->getBioskopDb()->query('SELECT 1')->getRow();
            $localOnline = ($row !== null);
        } catch (Exception $e) {
            $localOnline = false;
        }

        $activeMode = ($hasExternal && $externalOnline) ? 'EXTERNAL_API' : 'LOCAL_FALLBACK';

        return [
            'mode'            => $activeMode,
            'external_url'    => $hasExternal ? $this->externalBaseUrl : '(Belum diatur — menunggu REST API Partner Satria)',
            'external_online' => $externalOnline,
            'local_db_online' => $localOnline,
            'partner_write'   => $activeMode === 'EXTERNAL_API' ? 'HTTP_PARTNER_API' : 'READ_ONLY_FALLBACK',
            'city_target'     => 'Medan (Sumatera Utara)',
        ];
    }

    /**
     * Mengambil daftar film bioskop. Untuk client publik, `animeOnly = true` menyaring
     * agar hanya menampilkan anime yang dipetakan aktif di `anitrack.anime_cinema_map`.
     */
    public function getMovies(?string $search = null, bool $animeOnly = true): array
    {
        $movies = null;

        if ($this->externalBaseUrl !== '') {
            try {
                $client = Services::curlrequest(['timeout' => 3]);
                $url    = $this->externalBaseUrl . '/movies';
                if ($search !== null && $search !== '') {
                    $url .= '?q=' . urlencode($search);
                }

                $response = $client->get($url);
                if ($response->getStatusCode() === 200) {
                    $json = json_decode($response->getBody(), true);
                    $data = $json['data'] ?? $json;
                    if (is_array($data)) {
                        $movies = $data;
                    }
                }
            } catch (Exception $e) {
                // Lanjut ke fallback read-only
            }
        }

        if ($movies === null) {
            $builder = $this->getBioskopDb()->table('public.v_movies_lengkap_3nf');
            if ($search !== null && $search !== '') {
                $builder->groupStart()
                    ->like('LOWER(title)', strtolower($search))
                    ->orLike('LOWER(original_title)', strtolower($search))
                    ->groupEnd();
            }

            $movies = $builder->orderBy('popularity', 'DESC')->get()->getResultArray();
        }

        return $this->enrichMoviesList($movies, $animeOnly);
    }

    /**
     * Memperkaya daftar film bioskop dengan metadata pemetaan dari `anitrack.anime_cinema_map`.
     */
    protected function enrichMoviesList(array $movies, bool $animeOnly = true): array
    {
        $mappedByCinemaId = $this->getMappedMoviesByCinemaId(true);
        $result           = [];

        foreach ($movies as $movie) {
            $movieId = (int) ($movie['movie_id'] ?? $movie['id'] ?? 0);
            $mapping = $mappedByCinemaId[$movieId] ?? null;
            $isAnime = ($mapping !== null);

            if ($animeOnly && ! $isAnime) {
                continue;
            }

            $movie['is_anime']         = $isAnime;
            $movie['matched_anime_id'] = $mapping ? (int) $mapping['anime_id'] : null;
            $movie['screening_status'] = $mapping['screening_status'] ?? null;
            $movie['is_featured']      = $mapping ? (bool) $mapping['is_featured'] : false;
            $result[]                  = $movie;
        }

        return $result;
    }

    /**
     * Mengambil jadwal tayang bioskop Medan dan menggabungkan hitungan kursi yang terkunci
     * di `anitrack.cinema_booking_seats` secara real-time.
     */
    public function getShowtimes(
        ?int $movieId = null,
        ?string $date = null,
        ?string $cinemaName = null,
        bool $animeOnly = true
    ): array {
        $mappedByCinemaId = $this->getMappedMoviesByCinemaId(true);

        // Jika animeOnly aktif tetapi tidak ada anime yang dipetakan tayang, langsung kembalikan kosong
        if ($animeOnly && $mappedByCinemaId === []) {
            return [];
        }

        $showtimes = null;

        // 1. Prioritas Utama: External REST API Partner
        if ($this->externalBaseUrl !== '') {
            try {
                $client = Services::curlrequest(['timeout' => 3]);
                $query  = [];
                if ($movieId !== null) {
                    $query['movie_id'] = $movieId;
                }
                if ($date !== null && $date !== '') {
                    $query['date'] = $date;
                }

                $url = $this->externalBaseUrl . '/showtimes';
                if ($query !== []) {
                    $url .= '?' . http_build_query($query);
                }

                $response = $client->get($url);
                if ($response->getStatusCode() === 200) {
                    $json = json_decode($response->getBody(), true);
                    $data = $json['data'] ?? $json;
                    if (is_array($data)) {
                        $showtimes = $data;
                    }
                }
            } catch (Exception $e) {
                // Fallback ke read-only db_bioskop
            }
        }

        // 2. Fallback Read-Only ke db_bioskop (JOIN langsung ke tabel asli milik Satria tanpa modifikasi)
        if ($showtimes === null) {
            $builder = $this->getBioskopDb()->table('public.showtimes sh')
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

            if ($movieId !== null) {
                $builder->where('sh.movie_id', $movieId);
            } elseif ($animeOnly) {
                $builder->whereIn('sh.movie_id', array_keys($mappedByCinemaId));
            }

            if ($date !== null && $date !== '') {
                $builder->where('sh.show_date', $date);
            }

            if ($cinemaName !== null && $cinemaName !== '') {
                $builder->like('LOWER(st.cinema_name)', strtolower($cinemaName));
            }

            $showtimes = $builder
                ->orderBy('sh.show_date', 'ASC')
                ->orderBy('sh.start_time', 'ASC')
                ->get()
                ->getResultArray();
        }

        // Ambil jumlah kursi aktif yang sudah dipesan melalui AniCine per showtime
        $anicineBookedCounts = $this->getAnicineBookedCountsByShowtime();

        $filtered = [];
        foreach ($showtimes as $row) {
            $rowMovieId = (int) ($row['movie_id'] ?? 0);
            $mapping    = $mappedByCinemaId[$rowMovieId] ?? null;

            if ($animeOnly && $mapping === null) {
                continue;
            }

            if ($cinemaName !== null && $cinemaName !== '' && stripos((string) ($row['cinema_name'] ?? ''), $cinemaName) === false) {
                continue;
            }

            $showtimeId     = (int) ($row['showtime_id'] ?? 0);
            $partnerBooked  = (int) ($row['seats_booked'] ?? 0);
            $anicineBooked  = $anicineBookedCounts[$showtimeId] ?? 0;
            $totalCapacity  = (int) ($row['total_capacity'] ?? 0);
            $combinedBooked = min($totalCapacity, $partnerBooked + $anicineBooked);

            $row['anime_id']         = $mapping ? (int) $mapping['anime_id'] : null;
            $row['anime_title']      = $mapping['anime_title'] ?? ($row['movie_title'] ?? null);
            $row['anime_image_url']  = $mapping['anime_image_url'] ?? null;
            $row['screening_status'] = $mapping['screening_status'] ?? null;
            $row['seats_booked']     = $combinedBooked;
            $row['seats_available']  = max(0, $totalCapacity - $combinedBooked);

            $filtered[] = $row;
        }

        return $filtered;
    }

    /**
     * Mengambil detail satu jadwal tayang tertentu (hanya jika filmnya merupakan anime aktif).
     */
    public function getShowtimeDetail(int $showtimeId): ?array
    {
        $all = $this->getShowtimes(null, null, null, true);

        foreach ($all as $showtime) {
            if ((int) ($showtime['showtime_id'] ?? 0) === $showtimeId) {
                return $showtime;
            }
        }

        return null;
    }

    /**
     * Mengambil peta kursi lengkap untuk satu jadwal tayang:
     * menggabungkan kursi terisi dari partner (read-only) + kursi terkunci di `anitrack.cinema_booking_seats`.
     */
    public function getShowtimeSeats(int $showtimeId): ?array
    {
        $showtime = $this->getShowtimeDetail($showtimeId);
        if ($showtime === null) {
            return null;
        }

        $seats            = null;
        $partnerBookedIds = [];

        // 1. Prioritas Utama: External API Partner jika tersedia
        if ($this->externalBaseUrl !== '') {
            try {
                $client   = Services::curlrequest(['timeout' => 3]);
                $response = $client->get($this->externalBaseUrl . "/showtimes/{$showtimeId}/seats");
                if ($response->getStatusCode() === 200) {
                    $json = json_decode($response->getBody(), true);
                    $data = $json['data'] ?? $json;
                    if (is_array($data)) {
                        $seats = $data;
                    }
                }
            } catch (Exception $e) {
                // Fallback ke read-only db_bioskop
            }
        }

        // 2. Fallback Read-Only: Baca kursi studio + booking_seats partner dari db_bioskop
        if ($seats === null) {
            $studioId = (int) ($showtime['studio_id'] ?? 0);

            $seatRows = $this->getBioskopDb()->table('public.seats')
                ->select('seat_id, studio_id, seat_code, row_label, seat_number')
                ->where('studio_id', $studioId)
                ->orderBy('row_label', 'ASC')
                ->orderBy('seat_number', 'ASC')
                ->get()
                ->getResultArray();

            $partnerBookedRows = $this->getBioskopDb()->table('public.booking_seats')
                ->select('seat_id')
                ->where('showtime_id', $showtimeId)
                ->get()
                ->getResultArray();

            foreach ($partnerBookedRows as $r) {
                $partnerBookedIds[(int) $r['seat_id']] = true;
            }

            $seats = $seatRows;
        }

        // 3. Gabungkan dengan kursi aktif milik transaksi AniCine di db_anitrack
        $anicineBookedRows = $this->getAnitrackDb()->query(
            'SELECT seat_id FROM anitrack.cinema_booking_seats WHERE showtime_id = ?',
            [$showtimeId]
        )->getResultArray();

        $anicineBookedIds = [];
        foreach ($anicineBookedRows as $r) {
            $anicineBookedIds[(int) $r['seat_id']] = true;
        }

        $formattedSeats = [];
        $bookedCount    = 0;

        foreach ($seats as $seat) {
            $seatId   = (int) ($seat['seat_id'] ?? 0);
            $isBooked = (bool) ($seat['is_booked'] ?? false)
                || isset($partnerBookedIds[$seatId])
                || isset($anicineBookedIds[$seatId]);

            if ($isBooked) {
                $bookedCount++;
            }

            $formattedSeats[] = [
                'seat_id'     => $seatId,
                'seat_code'   => (string) $seat['seat_code'],
                'row_label'   => trim((string) $seat['row_label']),
                'seat_number' => (int) $seat['seat_number'],
                'is_booked'   => $isBooked,
            ];
        }

        return [
            'showtime' => $showtime,
            'summary'  => [
                'total_seats'     => count($formattedSeats),
                'booked_count'    => $bookedCount,
                'available_count' => max(0, count($formattedSeats) - $bookedCount),
            ],
            'seats' => $formattedSeats,
        ];
    }

    /**
     * Meneruskan reservasi kursi ke External REST API Partner bila mode EXTERNAL_API aktif.
     * Pada mode LOCAL_FALLBACK, TIDAK menulis apa pun ke db_bioskop milik Satria.
     *
     * @param list<int>            $seatIds
     * @param array<string, mixed> $user
     * @return string|null Partner booking ID jika reservasi dilakukan via External API.
     */
    public function reserveSeatsAtPartner(int $showtimeId, array $seatIds, array $user): ?string
    {
        if ($this->externalBaseUrl === '') {
            return null;
        }

        try {
            $client   = Services::curlrequest(['timeout' => 5, 'http_errors' => false]);
            $response = $client->post($this->externalBaseUrl . '/bookings', [
                'json' => [
                    'showtime_id'    => $showtimeId,
                    'seat_ids'       => $seatIds,
                    'customer_name'  => $user['username'] ?? 'AniCine Member',
                    'customer_email' => $user['email'] ?? '',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode === 409) {
                throw new RuntimeException('SEAT_CONFLICT');
            }

            if ($statusCode >= 200 && $statusCode < 300) {
                $json = json_decode($response->getBody(), true);
                $id   = $json['data']['booking_id'] ?? $json['booking_id'] ?? null;

                return $id !== null ? (string) $id : null;
            }
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Exception $e) {
            // Jika partner API tidak dapat dihubungi, tetap izinkan fallback lokal AniCine
        }

        return null;
    }

    /**
     * Melepaskan reservasi kursi pada External REST API Partner (kompensasi rollback / pembatalan).
     */
    public function releasePartnerBooking(?string $partnerBookingId): void
    {
        if ($this->externalBaseUrl === '' || $partnerBookingId === null || $partnerBookingId === '') {
            return;
        }

        try {
            $client = Services::curlrequest(['timeout' => 4, 'http_errors' => false]);
            $client->post($this->externalBaseUrl . '/bookings/' . rawurlencode($partnerBookingId) . '/cancel');
        } catch (Exception $e) {
            log_message('warning', "[CinemaService] Gagal melepaskan booking partner {$partnerBookingId}: " . $e->getMessage());
        }
    }

    /**
     * Mengambil daftar bioskop dan studio di Medan (READ-ONLY).
     */
    public function getStudios(): array
    {
        if ($this->externalBaseUrl !== '') {
            try {
                $client   = Services::curlrequest(['timeout' => 3]);
                $response = $client->get($this->externalBaseUrl . '/studios');
                if ($response->getStatusCode() === 200) {
                    $json = json_decode($response->getBody(), true);
                    $data = $json['data'] ?? $json;
                    if (is_array($data)) {
                        return $data;
                    }
                }
            } catch (Exception $e) {
                // Fallback ke read-only db_bioskop
            }
        }

        return $this->getBioskopDb()->table('public.studios')
            ->orderBy('cinema_name', 'ASC')
            ->orderBy('studio_id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Menghitung jumlah kursi yang saat ini terkunci di `anitrack.cinema_booking_seats` per showtime.
     *
     * @return array<int, int>
     */
    protected function getAnicineBookedCountsByShowtime(): array
    {
        $rows = $this->getAnitrackDb()->query(
            'SELECT showtime_id, COUNT(*) AS cnt FROM anitrack.cinema_booking_seats GROUP BY showtime_id'
        )->getResultArray();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['showtime_id']] = (int) $row['cnt'];
        }

        return $counts;
    }
}
