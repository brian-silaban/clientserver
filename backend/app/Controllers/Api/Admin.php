<?php

namespace App\Controllers\Api;

use App\Libraries\CinemaService;
use App\Services\RlsContextService;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Config\Database;
use Throwable;

/**
 * Controller API Admin (`/api/v1/admin/*`)
 * Seluruh endpoint pada controller ini dilindungi oleh filter `jwt:admin`.
 *
 * Mencakup:
 * - Dashboard metrik sistem
 * - Manajemen pemetaan dinamis anime <-> film bioskop (`anitrack.anime_cinema_map`)
 * - Monitoring & pembaruan status seluruh pemesanan tiket (`anitrack.cinema_bookings`)
 * - Manajemen akun pengguna & role (`anitrack.app_user`)
 * - Audit Log Viewer (`anitrack.audit_log`)
 */
class Admin extends BaseApiController
{
    /**
     * GET /api/v1/admin/dashboard
     * Ringkasan statistik katalog, penayangan bioskop, transaksi tiket, akun, dan audit.
     */
    public function dashboard()
    {
        $authUser = $this->getAuthUser();
        $db       = Database::connect('default');

        $db->transBegin();
        RlsContextService::applyTransactionContext($db, $authUser);

        $animeCount     = (int) ($db->query('SELECT COUNT(*) AS cnt FROM anitrack.anime')->getRowArray()['cnt'] ?? 0);
        $nowShowingCnt  = (int) ($db->query("SELECT COUNT(*) AS cnt FROM anitrack.anime_cinema_map WHERE screening_status = 'NOW_SHOWING'")->getRowArray()['cnt'] ?? 0);
        $upcomingCnt    = (int) ($db->query("SELECT COUNT(*) AS cnt FROM anitrack.anime_cinema_map WHERE screening_status = 'UPCOMING'")->getRowArray()['cnt'] ?? 0);
        $registeredUser = (int) ($db->query('SELECT COUNT(*) AS cnt FROM anitrack.app_user WHERE email IS NOT NULL')->getRowArray()['cnt'] ?? 0);
        $bookingStats   = $db->query(<<<'SQL'
            SELECT
                COUNT(*) AS total_bookings,
                COUNT(*) FILTER (WHERE payment_status = 'PAID') AS paid_bookings,
                COUNT(*) FILTER (WHERE payment_status = 'PENDING') AS pending_bookings,
                COUNT(*) FILTER (WHERE payment_status = 'CANCELLED') AS cancelled_bookings,
                COALESCE(SUM(total_price) FILTER (WHERE payment_status = 'PAID'), 0) AS total_revenue
            FROM anitrack.cinema_bookings
        SQL)->getRowArray();

        $audit24h = (int) ($db->query(
            "SELECT COUNT(*) AS cnt FROM anitrack.audit_log WHERE changed_at >= now() - interval '24 hours'"
        )->getRowArray()['cnt'] ?? 0);

        $db->transCommit();

        return $this->respondSuccess([
            'total_anime'       => $animeCount,
            'now_showing_count' => $nowShowingCnt,
            'upcoming_count'    => $upcomingCnt,
            'registered_users'  => $registeredUser,
            'bookings'          => [
                'total'     => (int) ($bookingStats['total_bookings'] ?? 0),
                'paid'      => (int) ($bookingStats['paid_bookings'] ?? 0),
                'pending'   => (int) ($bookingStats['pending_bookings'] ?? 0),
                'cancelled' => (int) ($bookingStats['cancelled_bookings'] ?? 0),
                'revenue'   => (float) ($bookingStats['total_revenue'] ?? 0),
            ],
            'audit_events_24h'  => $audit24h,
            'cinema_connection' => (new CinemaService())->getStatus(),
        ], 'Ringkasan dashboard administrator AniCine.');
    }

    /**
     * GET /api/v1/admin/theatrical
     * Daftar seluruh pemetaan anime <-> film bioskop (termasuk status ENDED) + daftar film partner.
     */
    public function theatricalList()
    {
        $db   = Database::connect('default');
        $rows = $db->query(<<<'SQL'
            SELECT
                m.*,
                a.title AS anime_title,
                a.image_url AS anime_image_url,
                a.score AS anime_score
            FROM anitrack.anime_cinema_map m
            JOIN anitrack.anime a ON a.anime_id = m.anime_id
            ORDER BY m.is_featured DESC, m.map_id ASC
        SQL)->getResultArray();

        foreach ($rows as &$row) {
            $row['map_id']          = (int) $row['map_id'];
            $row['anime_id']        = (int) $row['anime_id'];
            $row['cinema_movie_id'] = (int) $row['cinema_movie_id'];
            $row['is_featured']     = ($row['is_featured'] === true || $row['is_featured'] === 't');
            $row['version']         = (int) ($row['version'] ?? 1);
        }
        unset($row);

        return $this->respondSuccess($rows, 'Daftar pemetaan anime bioskop.', 200, [
            'total' => count($rows),
        ]);
    }

    /**
     * POST /api/v1/admin/theatrical
     * Menambahkan pemetaan baru antara anime (`db_anitrack`) dan film bioskop partner.
     */
    public function createTheatrical()
    {
        $input = $this->getJsonInput();
        if ($input === null) {
            return $this->respondError('Body request harus berupa JSON dan tidak boleh kosong.', 400);
        }

        $animeId         = isset($input['anime_id']) && ctype_digit((string) $input['anime_id']) ? (int) $input['anime_id'] : 0;
        $cinemaMovieId   = isset($input['cinema_movie_id']) && ctype_digit((string) $input['cinema_movie_id']) ? (int) $input['cinema_movie_id'] : 0;
        $screeningStatus = strtoupper(trim((string) ($input['screening_status'] ?? 'NOW_SHOWING')));
        $isFeatured      = ! empty($input['is_featured']);
        $releaseNote     = isset($input['release_note']) && $input['release_note'] !== '' ? trim((string) $input['release_note']) : null;

        $errors = [];
        if ($animeId <= 0) {
            $errors['anime_id'] = 'ID anime wajib berupa angka positif.';
        }
        if ($cinemaMovieId <= 0) {
            $errors['cinema_movie_id'] = 'ID film bioskop partner (cinema_movie_id) wajib berupa angka positif.';
        }
        if (! in_array($screeningStatus, ['NOW_SHOWING', 'UPCOMING', 'ENDED'], true)) {
            $errors['screening_status'] = 'Status tayang harus salah satu dari: NOW_SHOWING, UPCOMING, atau ENDED.';
        }

        if ($errors !== []) {
            return $this->respondValidationError($errors);
        }

        $db = Database::connect('default');

        try {
            $db->transBegin();
            RlsContextService::applyTransactionContext($db, $this->getAuthUser());

            $insertResult = $db->query(
                <<<'SQL'
                    INSERT INTO anitrack.anime_cinema_map (
                        anime_id, cinema_movie_id, screening_status, is_featured, release_note
                    )
                    VALUES (?, ?, ?, ?, ?)
                    RETURNING *
                SQL,
                [$animeId, $cinemaMovieId, $screeningStatus, $isFeatured ? 'true' : 'false', $releaseNote]
            );

            if ($insertResult === false) {
                $dbError = (string) ($db->error()['message'] ?? '');
                $db->transRollback();

                if (str_contains($dbError, '23505') || str_contains($dbError, 'unique')) {
                    return $this->respondError('Anime atau ID film bioskop tersebut sudah dipetakan sebelumnya.', 409);
                }
                if (str_contains($dbError, '23503') || str_contains($dbError, 'foreign key')) {
                    return $this->respondValidationError(['anime_id' => 'ID anime tidak ditemukan di katalog db_anitrack.']);
                }

                return $this->respondError('Gagal menambahkan pemetaan anime bioskop.', 500);
            }

            $inserted = $insertResult->getRowArray();
            $db->transCommit();
        } catch (DatabaseException $e) {
            $db->transRollback();

            if (str_contains($e->getMessage(), '23505') || str_contains($e->getMessage(), 'unique')) {
                return $this->respondError('Anime atau ID film bioskop tersebut sudah dipetakan sebelumnya.', 409);
            }
            if (str_contains($e->getMessage(), '23503') || str_contains($e->getMessage(), 'foreign key')) {
                return $this->respondValidationError(['anime_id' => 'ID anime tidak ditemukan di katalog db_anitrack.']);
            }

            return $this->respondError('Gagal menambahkan pemetaan anime bioskop.', 500);
        }

        return $this->respondSuccess($inserted, 'Pemetaan anime bioskop berhasil ditambahkan.', 201);
    }

    /**
     * PUT /api/v1/admin/theatrical/{id}
     * Memperbarui status tayang / featured / catatan pemetaan (dengan optimistic locking `version`).
     */
    public function updateTheatrical($id = null)
    {
        if (! ctype_digit((string) $id)) {
            return $this->respondError('Data pemetaan tidak ditemukan.', 404);
        }

        $mapId = (int) $id;
        $input = $this->getJsonInput();
        if ($input === null) {
            return $this->respondError('Body request harus berupa JSON dan tidak boleh kosong.', 400);
        }

        $db       = Database::connect('default');
        $existing = $db->query('SELECT * FROM anitrack.anime_cinema_map WHERE map_id = ? LIMIT 1', [$mapId])->getRowArray();
        if ($existing === null) {
            return $this->respondError("Pemetaan ID {$mapId} tidak ditemukan.", 404);
        }

        $currentVersion = (int) ($existing['version'] ?? 1);
        if (isset($input['version']) && (int) $input['version'] !== $currentVersion) {
            return $this->respondError('Konflik versi data pemetaan. Muat ulang halaman sebelum menyimpan.', 409);
        }

        $screeningStatus = isset($input['screening_status'])
            ? strtoupper(trim((string) $input['screening_status']))
            : (string) $existing['screening_status'];
        $isFeatured = array_key_exists('is_featured', $input)
            ? (bool) $input['is_featured']
            : ($existing['is_featured'] === true || $existing['is_featured'] === 't');
        $releaseNote = array_key_exists('release_note', $input)
            ? ($input['release_note'] === '' ? null : trim((string) $input['release_note']))
            : $existing['release_note'];

        if (! in_array($screeningStatus, ['NOW_SHOWING', 'UPCOMING', 'ENDED'], true)) {
            return $this->respondValidationError([
                'screening_status' => 'Status tayang harus NOW_SHOWING, UPCOMING, atau ENDED.',
            ]);
        }

        try {
            $db->transBegin();
            RlsContextService::applyTransactionContext($db, $this->getAuthUser());

            $updated = $db->query(
                <<<'SQL'
                    UPDATE anitrack.anime_cinema_map
                    SET screening_status = ?, is_featured = ?, release_note = ?,
                        version = version + 1, updated_at = now()
                    WHERE map_id = ?
                    RETURNING *
                SQL,
                [$screeningStatus, $isFeatured ? 'true' : 'false', $releaseNote, $mapId]
            )->getRowArray();

            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', '[Admin::updateTheatrical] ' . $e->getMessage());

            return $this->respondError('Gagal memperbarui pemetaan anime bioskop.', 500);
        }

        return $this->respondSuccess($updated, 'Pemetaan anime bioskop berhasil diperbarui.');
    }

    /**
     * DELETE /api/v1/admin/theatrical/{id}
     * Menghapus pemetaan anime dari daftar tayang bioskop.
     */
    public function deleteTheatrical($id = null)
    {
        if (! ctype_digit((string) $id)) {
            return $this->respondError('Data pemetaan tidak ditemukan.', 404);
        }

        $mapId = (int) $id;
        $db    = Database::connect('default');

        $db->transBegin();
        RlsContextService::applyTransactionContext($db, $this->getAuthUser());

        $deleted = $db->query(
            'DELETE FROM anitrack.anime_cinema_map WHERE map_id = ? RETURNING map_id',
            [$mapId]
        )->getRowArray();

        $db->transCommit();

        if ($deleted === null) {
            return $this->respondError("Pemetaan ID {$mapId} tidak ditemukan.", 404);
        }

        return $this->respondSuccess(['map_id' => $mapId], 'Pemetaan anime bioskop berhasil dihapus.');
    }

    /**
     * GET /api/v1/admin/bookings
     * Monitoring seluruh transaksi pemesanan tiket pengguna (melalui konteks RLS admin).
     */
    public function bookings()
    {
        $status  = strtoupper(trim((string) ($this->request->getGet('status') ?? '')));
        $page    = $this->getPage();
        $perPage = $this->getPerPage(20, 100);
        $offset  = ($page - 1) * $perPage;
        $db      = Database::connect('default');

        $whereSql = '';
        $params   = [];
        if (in_array($status, ['PENDING', 'PAID', 'CANCELLED', 'EXPIRED'], true)) {
            $whereSql = 'WHERE b.payment_status = ?';
            $params[] = $status;
        }

        $db->transBegin();
        RlsContextService::applyTransactionContext($db, $this->getAuthUser());

        $totalData = (int) ($db->query("SELECT COUNT(*) AS cnt FROM anitrack.cinema_bookings b {$whereSql}", $params)->getRowArray()['cnt'] ?? 0);
        $rows      = $db->query(
            <<<SQL
                SELECT
                    b.*,
                    u.username,
                    u.email,
                    a.title AS anime_title
                FROM anitrack.cinema_bookings b
                JOIN anitrack.app_user u ON u.user_id = b.user_id
                JOIN anitrack.anime a ON a.anime_id = b.anime_id
                {$whereSql}
                ORDER BY b.created_at DESC
                LIMIT ? OFFSET ?
            SQL,
            array_merge($params, [$perPage, $offset])
        )->getResultArray();

        $db->transCommit();

        return $this->respondSuccess($rows, 'Daftar seluruh pemesanan tiket bioskop.', 200, [
            'pager' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total_data'   => $totalData,
                'total_pages'  => max(1, (int) ceil($totalData / $perPage)),
            ],
        ]);
    }

    /**
     * PATCH /api/v1/admin/bookings/{id}/status
     * Admin memperbarui status pembayaran booking (jika CANCELLED/EXPIRED, kursi dilepas otomatis).
     */
    public function updateBookingStatus($id = null)
    {
        if (! ctype_digit((string) $id)) {
            return $this->respondError('Pesanan tiket tidak ditemukan.', 404);
        }

        $input = $this->getJsonInput();
        if ($input === null) {
            return $this->respondError('Body request harus berupa JSON dan tidak boleh kosong.', 400);
        }

        $newStatus = strtoupper(trim((string) ($input['payment_status'] ?? '')));
        if (! in_array($newStatus, ['PENDING', 'PAID', 'CANCELLED', 'EXPIRED'], true)) {
            return $this->respondValidationError([
                'payment_status' => 'Status pembayaran harus PENDING, PAID, CANCELLED, atau EXPIRED.',
            ]);
        }

        $bookingId = (int) $id;
        $db        = Database::connect('default');

        try {
            $db->transBegin();
            RlsContextService::applyTransactionContext($db, $this->getAuthUser());

            $existing = $db->query(
                'SELECT * FROM anitrack.cinema_bookings WHERE booking_id = ? FOR UPDATE',
                [$bookingId]
            )->getRowArray();

            if ($existing === null) {
                $db->transRollback();

                return $this->respondError('Pesanan tiket tidak ditemukan.', 404);
            }

            if (in_array($newStatus, ['CANCELLED', 'EXPIRED'], true)) {
                $db->query('DELETE FROM anitrack.cinema_booking_seats WHERE booking_id = ?', [$bookingId]);
            }

            $paidAtSql      = $newStatus === 'PAID' ? 'COALESCE(paid_at, now())' : 'paid_at';
            $cancelledAtSql = in_array($newStatus, ['CANCELLED', 'EXPIRED'], true) ? 'COALESCE(cancelled_at, now())' : 'cancelled_at';

            $updated = $db->query(
                <<<SQL
                    UPDATE anitrack.cinema_bookings
                    SET payment_status = ?, paid_at = {$paidAtSql}, cancelled_at = {$cancelledAtSql},
                        version = version + 1, updated_at = now()
                    WHERE booking_id = ?
                    RETURNING *
                SQL,
                [$newStatus, $bookingId]
            )->getRowArray();

            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', '[Admin::updateBookingStatus] ' . $e->getMessage());

            return $this->respondError('Gagal memperbarui status pembayaran booking.', 500);
        }

        if (in_array($newStatus, ['CANCELLED', 'EXPIRED'], true)) {
            (new CinemaService())->releasePartnerBooking($updated['partner_booking_id'] ?? null);
        }

        return $this->respondSuccess($updated, "Status booking berhasil diubah menjadi {$newStatus}.");
    }

    /**
     * GET /api/v1/admin/users
     * Daftar akun pengguna terdaftar pada platform AniCine (email IS NOT NULL).
     */
    public function users()
    {
        $db   = Database::connect('default');
        $rows = $db->query(<<<'SQL'
            SELECT
                user_id, username, email, role, gender, location, birth_date,
                anitrack.fn_hitung_umur(birth_date) AS age,
                join_date, last_online, is_active, created_at
            FROM anitrack.app_user
            WHERE email IS NOT NULL
            ORDER BY created_at DESC, user_id DESC
        SQL)->getResultArray();

        foreach ($rows as &$row) {
            $row['user_id']   = (int) $row['user_id'];
            $row['age']       = $row['age'] !== null ? (int) $row['age'] : null;
            $row['is_active'] = ($row['is_active'] === true || $row['is_active'] === 't');
        }
        unset($row);

        return $this->respondSuccess($rows, 'Daftar akun pengguna terdaftar.', 200, [
            'total' => count($rows),
        ]);
    }

    /**
     * PATCH /api/v1/admin/users/{id}
     * Admin mengubah role (`user`/`admin`) atau status aktif akun pengguna lain.
     */
    public function updateUser($id = null)
    {
        if (! ctype_digit((string) $id)) {
            return $this->respondError('Pengguna tidak ditemukan.', 404);
        }

        $targetUserId = (int) $id;
        $authUser     = $this->getAuthUser();
        $input        = $this->getJsonInput();

        if ($input === null) {
            return $this->respondError('Body request harus berupa JSON dan tidak boleh kosong.', 400);
        }

        if ($targetUserId === $authUser['user_id']) {
            return $this->respondError('Admin tidak dapat mengubah peran atau menonaktifkan akun sendiri.', 422);
        }

        $db       = Database::connect('default');
        $existing = $db->query(
            'SELECT user_id, role, is_active FROM anitrack.app_user WHERE user_id = ? AND email IS NOT NULL LIMIT 1',
            [$targetUserId]
        )->getRowArray();

        if ($existing === null) {
            return $this->respondError('Akun pengguna tidak ditemukan.', 404);
        }

        $role     = isset($input['role']) ? trim((string) $input['role']) : (string) $existing['role'];
        $isActive = array_key_exists('is_active', $input)
            ? (bool) $input['is_active']
            : ($existing['is_active'] === true || $existing['is_active'] === 't');

        if (! in_array($role, ['user', 'admin'], true)) {
            return $this->respondValidationError(['role' => 'Peran akun harus user atau admin.']);
        }

        $db->transBegin();
        RlsContextService::applyTransactionContext($db, $authUser);

        $updated = $db->query(
            <<<'SQL'
                UPDATE anitrack.app_user
                SET role = ?, is_active = ?, version = version + 1, updated_at = now()
                WHERE user_id = ?
                RETURNING user_id, username, email, role, is_active, updated_at
            SQL,
            [$role, $isActive ? 'true' : 'false', $targetUserId]
        )->getRowArray();

        $db->transCommit();

        $updated['user_id']   = (int) $updated['user_id'];
        $updated['is_active'] = ($updated['is_active'] === true || $updated['is_active'] === 't');

        return $this->respondSuccess($updated, 'Data akun pengguna berhasil diperbarui.');
    }

    /**
     * GET /api/v1/admin/audit-logs
     * Audit Log Viewer dengan filter tabel, operasi (INSERT/UPDATE/DELETE), dan app_user.
     */
    public function auditLogs()
    {
        $tableName = trim((string) ($this->request->getGet('table_name') ?? ''));
        $operation = strtoupper(trim((string) ($this->request->getGet('operation') ?? '')));
        $appUser   = trim((string) ($this->request->getGet('app_user') ?? ''));
        $page      = $this->getPage();
        $perPage   = $this->getPerPage(25, 100);
        $offset    = ($page - 1) * $perPage;

        $where  = [];
        $params = [];

        if ($tableName !== '') {
            $where[]  = 'table_name = ?';
            $params[] = $tableName;
        }
        if (in_array($operation, ['INSERT', 'UPDATE', 'DELETE'], true)) {
            $where[]  = 'operation = ?';
            $params[] = $operation;
        }
        if ($appUser !== '') {
            $where[]  = 'app_user ILIKE ?';
            $params[] = '%' . $appUser . '%';
        }

        $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
        $db       = Database::connect('default');

        $totalData = (int) ($db->query("SELECT COUNT(*) AS cnt FROM anitrack.audit_log {$whereSql}", $params)->getRowArray()['cnt'] ?? 0);
        $rows      = $db->query(
            <<<SQL
                SELECT id, table_name, operation, row_pk, old_data, new_data,
                       changed_by, app_user, client_ip, changed_at
                FROM anitrack.audit_log
                {$whereSql}
                ORDER BY id DESC
                LIMIT ? OFFSET ?
            SQL,
            array_merge($params, [$perPage, $offset])
        )->getResultArray();

        foreach ($rows as &$row) {
            $row['id']       = (int) $row['id'];
            $row['old_data'] = is_string($row['old_data']) ? json_decode($row['old_data'], true) : $row['old_data'];
            $row['new_data'] = is_string($row['new_data']) ? json_decode($row['new_data'], true) : $row['new_data'];
        }
        unset($row);

        return $this->respondSuccess($rows, 'Riwayat audit perubahan data.', 200, [
            'pager' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total_data'   => $totalData,
                'total_pages'  => max(1, (int) ceil($totalData / $perPage)),
            ],
        ]);
    }
}
