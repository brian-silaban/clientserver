<?php

namespace App\Controllers\Api;

use App\Libraries\CinemaService;
use App\Services\RlsContextService;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Config\Database;
use RuntimeException;
use Throwable;

/**
 * Controller API Booking (`/api/v1/bookings/*`)
 * Membutuhkan autentikasi JWT (`jwt:user,admin`).
 *
 * Seluruh operasi transaksi pemesanan tiket berjalan secara ACID di `db_anitrack` dengan:
 * - Row Level Security (RLS) berbasis `app.user_id` & `app.user_role`.
 * - Pessimistic Locking (`SELECT ... FOR UPDATE`) + constraint `UNIQUE (showtime_id, seat_id)`
 *   di `anitrack.cinema_booking_seats` untuk mencegah double-booking tanpa memodifikasi `db_bioskop`.
 * - Kompensasi rollback otomatis jika reservasi ke External Partner API gagal atau bentrok.
 */
class Booking extends BaseApiController
{
    private CinemaService $cinema;

    public function __construct()
    {
        $this->cinema = new CinemaService();
    }

    /**
     * GET /api/v1/bookings
     * Daftar riwayat pemesanan tiket milik pengguna aktif (dilindungi RLS PostgreSQL).
     */
    public function index()
    {
        $authUser = $this->getAuthUser();
        $db       = Database::connect('default');

        $db->transBegin();
        RlsContextService::applyTransactionContext($db, $authUser);

        // RLS pada anitrack.cinema_bookings otomatis membatasi baris untuk role user;
        // untuk konsistensi halaman "Tiket Saya" milik sendiri, filter berdasarkan user_id aktif.
        $rows = $db->query(
            <<<'SQL'
                SELECT
                    b.*,
                    a.title AS anime_title,
                    a.image_url AS anime_image_url,
                    a.duration_min
                FROM anitrack.cinema_bookings b
                JOIN anitrack.anime a ON a.anime_id = b.anime_id
                WHERE b.user_id = ?
                ORDER BY b.created_at DESC
            SQL,
            [$authUser['user_id']]
        )->getResultArray();

        $db->transCommit();

        foreach ($rows as &$row) {
            $row = $this->formatBookingRow($row);
        }
        unset($row);

        return $this->respondSuccess($rows, 'Daftar riwayat pemesanan tiket Anda.', 200, [
            'total' => count($rows),
        ]);
    }

    /**
     * GET /api/v1/bookings/{id}
     * Detail e-ticket berdasarkan booking_id (dilindungi RLS: user lain tidak bisa mengintip).
     */
    public function show($id = null)
    {
        if (! ctype_digit((string) $id)) {
            return $this->respondError('Pesanan tiket tidak ditemukan.', 404);
        }

        $authUser = $this->getAuthUser();
        $db       = Database::connect('default');

        $db->transBegin();
        RlsContextService::applyTransactionContext($db, $authUser);

        $row = $db->query(
            <<<'SQL'
                SELECT
                    b.*,
                    a.title AS anime_title,
                    a.title_japanese AS anime_title_japanese,
                    a.image_url AS anime_image_url,
                    a.duration_min
                FROM anitrack.cinema_bookings b
                JOIN anitrack.anime a ON a.anime_id = b.anime_id
                WHERE b.booking_id = ?
                LIMIT 1
            SQL,
            [(int) $id]
        )->getRowArray();

        $db->transCommit();

        if ($row === null) {
            return $this->respondError('Pesanan tiket tidak ditemukan atau Anda tidak memiliki akses.', 404);
        }

        return $this->respondSuccess($this->formatBookingRow($row), 'Detail e-ticket.');
    }

    /**
     * POST /api/v1/bookings
     * Membuat pesanan tiket bioskop anime secara ACID dengan locking anti double-booking.
     * Payload: `{ "showtime_id": 21, "seat_ids": [1, 2] }`
     */
    public function create()
    {
        $authUser = $this->getAuthUser();
        $input    = $this->getJsonInput();

        if ($input === null) {
            return $this->respondError('Body request harus berupa JSON dan tidak boleh kosong.', 400);
        }

        $showtimeId = isset($input['showtime_id']) && ctype_digit((string) $input['showtime_id'])
            ? (int) $input['showtime_id']
            : 0;
        $rawSeatIds = $input['seat_ids'] ?? null;

        $errors = [];
        if ($showtimeId <= 0) {
            $errors['showtime_id'] = 'Jadwal tayang (showtime_id) wajib diisi dengan angka valid.';
        }

        if (! is_array($rawSeatIds) || $rawSeatIds === []) {
            $errors['seat_ids'] = 'Pilih minimal 1 kursi untuk memesan tiket.';
        } elseif (count($rawSeatIds) > 6) {
            $errors['seat_ids'] = 'Maksimal 6 kursi per transaksi pemesanan.';
        }

        $seatIds = [];
        if (is_array($rawSeatIds)) {
            foreach ($rawSeatIds as $sid) {
                if (! ctype_digit((string) $sid) || (int) $sid <= 0) {
                    $errors['seat_ids'] = 'Seluruh ID kursi (seat_ids) harus berupa angka positif.';
                    break;
                }
                $seatIds[] = (int) $sid;
            }
            $seatIds = array_values(array_unique($seatIds));
            sort($seatIds);
        }

        if ($errors !== []) {
            return $this->respondValidationError($errors);
        }

        // 1. Validasi jadwal tayang & denah kursi dari CinemaService (khusus anime aktif)
        $seatMapData = $this->cinema->getShowtimeSeats($showtimeId);
        if ($seatMapData === null) {
            return $this->respondError('Jadwal tayang anime tidak ditemukan atau sudah tidak aktif.', 404);
        }

        $showtime = $seatMapData['showtime'];
        if (($showtime['screening_status'] ?? '') === 'UPCOMING') {
            return $this->respondError('Film anime ini berstatus Akan Tayang (UPCOMING) dan belum membuka loket pemesanan.', 422);
        }

        // Petakan kursi valid milik studio jadwal ini
        $seatsById = [];
        foreach ($seatMapData['seats'] as $seat) {
            $seatsById[(int) $seat['seat_id']] = $seat;
        }

        $selectedSeats = [];
        foreach ($seatIds as $seatId) {
            if (! isset($seatsById[$seatId])) {
                return $this->respondValidationError([
                    'seat_ids' => "Kursi ID {$seatId} tidak terdaftar pada studio jadwal ini.",
                ]);
            }

            if ($seatsById[$seatId]['is_booked'] === true) {
                $code = $seatsById[$seatId]['seat_code'];

                return $this->respondError("Kursi {$code} sudah dipesan oleh pengguna lain. Silakan pilih kursi lain.", 409);
            }

            $selectedSeats[] = $seatsById[$seatId];
        }

        // 2. Reservasi ke External REST API Partner jika mode EXTERNAL_API aktif (no-op di LOCAL_FALLBACK)
        $partnerBookingId = null;
        try {
            $partnerBookingId = $this->cinema->reserveSeatsAtPartner($showtimeId, $seatIds, $authUser);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'SEAT_CONFLICT') {
                return $this->respondError('Salah satu kursi yang dipilih baru saja dipesan pada sistem bioskop partner.', 409);
            }

            return $this->respondError('Gagal menghubungi layanan reservasi bioskop partner.', 502);
        }

        // 3. Transaksi ACID + Pessimistic Row Locking di db_anitrack
        $db           = Database::connect('default');
        $animeId      = (int) $showtime['anime_id'];
        $ticketPrice  = (float) ($showtime['ticket_price'] ?? 0);
        $seatCount    = count($selectedSeats);
        $totalPrice   = $ticketPrice * $seatCount;
        $seatLabels   = implode(', ', array_map(static fn (array $s): string => $s['seat_code'], $selectedSeats));
        $bookingCode  = 'ACN-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        try {
            $db->transBegin();
            RlsContextService::applyTransactionContext($db, $authUser);

            // Kunci baris pemetaan film untuk menyerialisasi transaksi konkuren pada jadwal yang sama
            $db->query(
                'SELECT map_id FROM anitrack.anime_cinema_map WHERE anime_id = ? FOR UPDATE',
                [$animeId]
            );

            // Periksa ulang bentrokan kursi di dalam transaksi terkunci
            $placeholders  = implode(',', array_fill(0, count($seatIds), '?'));
            $conflictRows  = $db->query(
                "SELECT seat_code FROM anitrack.cinema_booking_seats WHERE showtime_id = ? AND seat_id IN ({$placeholders}) FOR UPDATE",
                array_merge([$showtimeId], $seatIds)
            )->getResultArray();

            if ($conflictRows !== []) {
                $db->transRollback();
                $this->cinema->releasePartnerBooking($partnerBookingId);

                $takenCodes = implode(', ', array_column($conflictRows, 'seat_code'));

                return $this->respondError("Kursi {$takenCodes} baru saja dipesan pengguna lain. Silakan pilih kursi lain.", 409);
            }

            // Simpan header transaksi booking (batas pembayaran 15 menit)
            $bookingResult = $db->query(
                <<<'SQL'
                    INSERT INTO anitrack.cinema_bookings (
                        booking_code, user_id, anime_id, cinema_movie_id, showtime_id,
                        partner_booking_id, cinema_name, kecamatan, studio_name, studio_type,
                        show_date, start_time, seat_ids_json, seat_labels, seat_count,
                        ticket_price, total_price, payment_status, expires_at
                    )
                    VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?::jsonb, ?, ?,
                        ?, ?, 'PENDING', now() + interval '15 minutes'
                    )
                    RETURNING *
                SQL,
                [
                    $bookingCode,
                    $authUser['user_id'],
                    $animeId,
                    (int) $showtime['movie_id'],
                    $showtimeId,
                    $partnerBookingId,
                    (string) $showtime['cinema_name'],
                    (string) ($showtime['kecamatan'] ?? 'Medan'),
                    (string) $showtime['studio_name'],
                    (string) ($showtime['studio_type'] ?? 'Regular 2D'),
                    (string) $showtime['show_date'],
                    (string) $showtime['start_time'],
                    json_encode($seatIds),
                    $seatLabels,
                    $seatCount,
                    $ticketPrice,
                    $totalPrice,
                ]
            );

            if ($bookingResult === false) {
                $db->transRollback();
                $this->cinema->releasePartnerBooking($partnerBookingId);

                return $this->respondError('Transaksi pemesanan tiket gagal disimpan.', 500);
            }

            $insertedBooking = $bookingResult->getRowArray();
            $bookingId       = (int) $insertedBooking['booking_id'];

            // Kunci setiap kursi di anitrack.cinema_booking_seats (dilindungi UNIQUE showtime_id + seat_id)
            foreach ($selectedSeats as $seat) {
                $seatInsert = $db->query(
                    <<<'SQL'
                        INSERT INTO anitrack.cinema_booking_seats (booking_id, showtime_id, seat_id, seat_code)
                        VALUES (?, ?, ?, ?)
                    SQL,
                    [$bookingId, $showtimeId, (int) $seat['seat_id'], (string) $seat['seat_code']]
                );

                if ($seatInsert === false) {
                    $dbError = (string) ($db->error()['message'] ?? '');
                    $db->transRollback();
                    $this->cinema->releasePartnerBooking($partnerBookingId);

                    if (str_contains($dbError, 'uq_cbs_showtime_seat') || str_contains($dbError, '23505')) {
                        return $this->respondError('Salah satu kursi yang Anda pilih baru saja diambil oleh transaksi lain.', 409);
                    }

                    return $this->respondError('Gagal mengunci kursi yang dipilih.', 500);
                }
            }

            $db->transCommit();
        } catch (DatabaseException $e) {
            $db->transRollback();
            $this->cinema->releasePartnerBooking($partnerBookingId);

            // Kode PostgreSQL 23505 = unique_violation pada uq_cbs_showtime_seat
            if (str_contains($e->getMessage(), 'uq_cbs_showtime_seat') || str_contains($e->getMessage(), '23505')) {
                return $this->respondError('Salah satu kursi yang Anda pilih baru saja diambil oleh transaksi lain.', 409);
            }

            log_message('error', '[Booking::create] DatabaseException: ' . $e->getMessage());

            return $this->respondError('Transaksi pemesanan tiket gagal diproses.', 500);
        } catch (Throwable $e) {
            $db->transRollback();
            $this->cinema->releasePartnerBooking($partnerBookingId);
            log_message('error', '[Booking::create] ' . $e->getMessage());

            return $this->respondError('Terjadi kesalahan saat membuat pesanan tiket.', 500);
        }

        $insertedBooking['anime_title']     = $showtime['anime_title'] ?? $showtime['movie_title'] ?? null;
        $insertedBooking['anime_image_url'] = $showtime['anime_image_url'] ?? null;

        return $this->respondSuccess(
            $this->formatBookingRow($insertedBooking),
            'Pemesanan kursi berhasil dibuat. Silakan selesaikan pembayaran dalam 15 menit.',
            201
        );
    }

    /**
     * POST /api/v1/bookings/{id}/pay
     * Simulasi konfirmasi pembayaran tiket (`PENDING` -> `PAID`).
     */
    public function pay($id = null)
    {
        if (! ctype_digit((string) $id)) {
            return $this->respondError('Pesanan tiket tidak ditemukan.', 404);
        }

        $authUser  = $this->getAuthUser();
        $bookingId = (int) $id;
        $db        = Database::connect('default');

        try {
            $db->transBegin();
            RlsContextService::applyTransactionContext($db, $authUser);

            $booking = $db->query(
                'SELECT * FROM anitrack.cinema_bookings WHERE booking_id = ? FOR UPDATE',
                [$bookingId]
            )->getRowArray();

            if ($booking === null) {
                $db->transRollback();

                return $this->respondError('Pesanan tiket tidak ditemukan.', 404);
            }

            if ($booking['payment_status'] === 'PAID') {
                $db->transRollback();

                return $this->respondError('Pesanan tiket ini sudah dibayar sebelumnya.', 409);
            }

            if ($booking['payment_status'] !== 'PENDING') {
                $db->transRollback();

                return $this->respondError("Pesanan dengan status {$booking['payment_status']} tidak dapat dibayar.", 422);
            }

            $updated = $db->query(
                <<<'SQL'
                    UPDATE anitrack.cinema_bookings
                    SET payment_status = 'PAID', paid_at = now(), version = version + 1, updated_at = now()
                    WHERE booking_id = ?
                    RETURNING *
                SQL,
                [$bookingId]
            )->getRowArray();

            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', '[Booking::pay] ' . $e->getMessage());

            return $this->respondError('Gagal memproses pembayaran tiket.', 500);
        }

        return $this->respondSuccess($this->formatBookingRow($updated), 'Pembayaran berhasil dikonfirmasi. E-ticket Anda kini aktif!');
    }

    /**
     * POST /api/v1/bookings/{id}/cancel
     * Membatalkan pesanan tiket (`PENDING` -> `CANCELLED`) dan langsung melepaskan kursi terkunci.
     */
    public function cancel($id = null)
    {
        if (! ctype_digit((string) $id)) {
            return $this->respondError('Pesanan tiket tidak ditemukan.', 404);
        }

        $authUser  = $this->getAuthUser();
        $bookingId = (int) $id;
        $db        = Database::connect('default');

        try {
            $db->transBegin();
            RlsContextService::applyTransactionContext($db, $authUser);

            $booking = $db->query(
                'SELECT * FROM anitrack.cinema_bookings WHERE booking_id = ? FOR UPDATE',
                [$bookingId]
            )->getRowArray();

            if ($booking === null) {
                $db->transRollback();

                return $this->respondError('Pesanan tiket tidak ditemukan.', 404);
            }

            if ($booking['payment_status'] === 'CANCELLED') {
                $db->transRollback();

                return $this->respondError('Pesanan tiket ini sudah dibatalkan sebelumnya.', 409);
            }

            if ($booking['payment_status'] === 'PAID' && $authUser['role'] !== 'admin') {
                $db->transRollback();

                return $this->respondError('Tiket yang sudah lunas tidak dapat dibatalkan mandiri. Hubungi admin bioskop.', 422);
            }

            // Lepaskan kursi agar bisa langsung dipesan oleh pengguna lain
            $db->query('DELETE FROM anitrack.cinema_booking_seats WHERE booking_id = ?', [$bookingId]);

            $updated = $db->query(
                <<<'SQL'
                    UPDATE anitrack.cinema_bookings
                    SET payment_status = 'CANCELLED', cancelled_at = now(), version = version + 1, updated_at = now()
                    WHERE booking_id = ?
                    RETURNING *
                SQL,
                [$bookingId]
            )->getRowArray();

            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', '[Booking::cancel] ' . $e->getMessage());

            return $this->respondError('Gagal membatalkan pesanan tiket.', 500);
        }

        // Lepaskan reservasi pada API partner bila mode EXTERNAL_API digunakan
        $this->cinema->releasePartnerBooking($updated['partner_booking_id'] ?? null);

        return $this->respondSuccess($this->formatBookingRow($updated), 'Pesanan berhasil dibatalkan dan kursi telah dilepaskan.');
    }

    /**
     * Merapikan tipe data field JSON booking untuk respons API yang konsisten.
     */
    private function formatBookingRow(array $row): array
    {
        $seatIds = $row['seat_ids_json'] ?? [];
        if (is_string($seatIds)) {
            $decoded = json_decode($seatIds, true);
            $seatIds = is_array($decoded) ? $decoded : [];
        }

        $row['booking_id']      = (int) $row['booking_id'];
        $row['user_id']         = (int) $row['user_id'];
        $row['anime_id']        = (int) $row['anime_id'];
        $row['cinema_movie_id'] = (int) $row['cinema_movie_id'];
        $row['showtime_id']     = (int) $row['showtime_id'];
        $row['seat_count']      = (int) $row['seat_count'];
        $row['ticket_price']    = (float) $row['ticket_price'];
        $row['total_price']     = (float) $row['total_price'];
        $row['version']         = (int) ($row['version'] ?? 1);
        $row['seat_ids']        = $seatIds;
        unset($row['seat_ids_json']);

        return $row;
    }
}
