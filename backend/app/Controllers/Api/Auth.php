<?php

namespace App\Controllers\Api;

use App\Services\JwtService;
use App\Services\RlsContextService;
use Config\Database;
use Throwable;

/**
 * Controller API Auth (`/api/v1/auth/*`)
 * Menangani registrasi akun baru, login JWT, profil pengguna aktif (`fn_hitung_umur`),
 * dan pembaruan profil / ganti password.
 */
class Auth extends BaseApiController
{
    /**
     * POST /api/v1/auth/register
     * Mendaftarkan akun member baru (`role: user`) dan langsung menerbitkan JWT.
     */
    public function register()
    {
        $input = $this->getJsonInput();
        if ($input === null) {
            return $this->respondError('Body request harus berupa JSON dan tidak boleh kosong.', 400);
        }

        $username  = trim((string) ($input['username'] ?? ''));
        $email     = strtolower(trim((string) ($input['email'] ?? '')));
        $password  = (string) ($input['password'] ?? '');
        $gender    = isset($input['gender']) && $input['gender'] !== '' ? trim((string) $input['gender']) : null;
        $location  = isset($input['location']) && $input['location'] !== '' ? trim((string) $input['location']) : null;
        $birthDate = isset($input['birth_date']) && $input['birth_date'] !== '' ? trim((string) $input['birth_date']) : null;

        $errors = [];

        if ($username === '' || strlen($username) < 3 || strlen($username) > 50 || ! preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
            $errors['username'] = 'Username wajib 3–50 karakter dan hanya boleh huruf, angka, titik, garis bawah, atau tanda hubung.';
        }

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
            $errors['email'] = 'Format alamat email tidak valid.';
        }

        if (strlen($password) < 8) {
            $errors['password'] = 'Kata sandi minimal 8 karakter.';
        }

        if ($gender !== null && ! in_array($gender, ['Male', 'Female', 'Non-Binary'], true)) {
            $errors['gender'] = 'Gender harus salah satu dari: Male, Female, atau Non-Binary.';
        }

        if ($birthDate !== null) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate) || strtotime($birthDate) === false) {
                $errors['birth_date'] = 'Format tanggal lahir harus YYYY-MM-DD.';
            } elseif ($birthDate > date('Y-m-d')) {
                $errors['birth_date'] = 'Tanggal lahir tidak boleh melebihi hari ini.';
            } elseif ($birthDate < '1900-01-01') {
                $errors['birth_date'] = 'Tanggal lahir tidak valid (minimal tahun 1900).';
            }
        }

        if ($errors !== []) {
            return $this->respondValidationError($errors);
        }

        $db = Database::connect('default');

        // Cek duplikasi username atau email secara case-insensitive
        $dupUsername = $db->query(
            'SELECT 1 FROM anitrack.app_user WHERE lower(username) = lower(?) LIMIT 1',
            [$username]
        )->getRow();

        if ($dupUsername !== null) {
            return $this->respondValidationError(['username' => 'Username sudah digunakan oleh akun lain.']);
        }

        $dupEmail = $db->query(
            'SELECT 1 FROM anitrack.app_user WHERE lower(email) = lower(?) LIMIT 1',
            [$email]
        )->getRow();

        if ($dupEmail !== null) {
            return $this->respondValidationError(['email' => 'Email sudah terdaftar. Silakan masuk menggunakan akun Anda.']);
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        try {
            $db->transBegin();
            $db->query("SELECT set_config('app.user_id', ?, true)", ['register:' . $username]);

            // Role selalu ditetapkan 'user' dari server untuk mencegah privilege escalation
            $created = $db->query(
                <<<'SQL'
                    INSERT INTO anitrack.app_user (
                        username, email, password_hash, role, gender, location, birth_date, is_active, last_online
                    )
                    VALUES (?, ?, ?, 'user', ?, ?, ?, true, now())
                    RETURNING user_id, username, email, role, gender, location, birth_date, join_date, is_active,
                              anitrack.fn_hitung_umur(birth_date) AS age
                SQL,
                [$username, $email, $passwordHash, $gender, $location, $birthDate]
            )->getRowArray();

            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', '[Auth::register] Gagal mendaftarkan user: ' . $e->getMessage());

            return $this->respondError('Gagal mendaftarkan akun baru. Silakan coba lagi.', 500);
        }

        $tokenData = (new JwtService())->issueToken($created);

        return $this->respondSuccess([
            'token' => $tokenData,
            'user'  => $this->formatUserProfile($created),
        ], 'Registrasi berhasil. Selamat datang di AniCine!', 201);
    }

    /**
     * POST /api/v1/auth/login
     * Masuk menggunakan email atau username + password, mengembalikan JWT.
     */
    public function login()
    {
        $input = $this->getJsonInput();
        if ($input === null) {
            return $this->respondError('Body request harus berupa JSON dan tidak boleh kosong.', 400);
        }

        $identifier = trim((string) ($input['identifier'] ?? $input['email'] ?? $input['username'] ?? ''));
        $password   = (string) ($input['password'] ?? '');

        $errors = [];
        if ($identifier === '') {
            $errors['identifier'] = 'Email atau username wajib diisi.';
        }
        if ($password === '') {
            $errors['password'] = 'Kata sandi wajib diisi.';
        }
        if ($errors !== []) {
            return $this->respondValidationError($errors);
        }

        $db   = Database::connect('default');
        $user = $db->query(
            <<<'SQL'
                SELECT user_id, username, email, password_hash, role, gender, location,
                       birth_date, join_date, is_active, anitrack.fn_hitung_umur(birth_date) AS age
                FROM anitrack.app_user
                WHERE email IS NOT NULL
                  AND (lower(email) = lower(?) OR lower(username) = lower(?))
                LIMIT 1
            SQL,
            [$identifier, $identifier]
        )->getRowArray();

        if ($user === null || empty($user['password_hash']) || ! password_verify($password, (string) $user['password_hash'])) {
            return $this->respondError('Email/username atau kata sandi tidak sesuai.', 401);
        }

        if ($user['is_active'] !== true && $user['is_active'] !== 't') {
            return $this->respondError('Akun Anda sedang dinonaktifkan. Hubungi administrator.', 403);
        }

        // Perbarui waktu login terakhir
        try {
            $db->transBegin();
            $db->query("SELECT set_config('app.user_id', ?, true)", [(string) $user['user_id']]);
            $db->query('UPDATE anitrack.app_user SET last_online = now() WHERE user_id = ?', [(int) $user['user_id']]);
            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
        }

        $tokenData = (new JwtService())->issueToken($user);

        return $this->respondSuccess([
            'token' => $tokenData,
            'user'  => $this->formatUserProfile($user),
        ], 'Berhasil masuk ke akun AniCine.');
    }

    /**
     * GET /api/v1/auth/me
     * Mengambil profil pengguna aktif beserta umur (fn_hitung_umur) & ringkasan tontonan (v_user_stats).
     */
    public function me()
    {
        $authUser = $this->getAuthUser();
        if ($authUser === null) {
            return $this->respondError('Tidak terautentikasi.', 401);
        }

        $db = Database::connect('default');

        $db->transBegin();
        RlsContextService::applyTransactionContext($db, $authUser);

        $user = $db->query(
            <<<'SQL'
                SELECT user_id, username, email, role, gender, location,
                       birth_date, join_date, last_online, is_active, version,
                       anitrack.fn_hitung_umur(birth_date) AS age
                FROM anitrack.app_user
                WHERE user_id = ?
                LIMIT 1
            SQL,
            [$authUser['user_id']]
        )->getRowArray();

        $stats = $db->query(
            'SELECT * FROM anitrack.v_user_stats WHERE user_id = ? LIMIT 1',
            [$authUser['user_id']]
        )->getRowArray();

        $bookingCount = $db->query(
            'SELECT COUNT(*) AS total_bookings FROM anitrack.cinema_bookings WHERE user_id = ?',
            [$authUser['user_id']]
        )->getRowArray();

        $db->transCommit();

        if ($user === null) {
            return $this->respondError('Akun pengguna tidak ditemukan.', 404);
        }

        return $this->respondSuccess([
            'user'           => $this->formatUserProfile($user),
            'watch_stats'    => $stats,
            'total_bookings' => (int) ($bookingCount['total_bookings'] ?? 0),
        ], 'Profil pengguna aktif.');
    }

    /**
     * PUT /api/v1/auth/me
     * Memperbarui profil pribadi (gender, location, birth_date) atau mengganti kata sandi.
     */
    public function updateMe()
    {
        $authUser = $this->getAuthUser();
        if ($authUser === null) {
            return $this->respondError('Tidak terautentikasi.', 401);
        }

        $input = $this->getJsonInput();
        if ($input === null) {
            return $this->respondError('Body request harus berupa JSON dan tidak boleh kosong.', 400);
        }

        $db      = Database::connect('default');
        $current = $db->query(
            'SELECT user_id, username, email, password_hash, role, gender, location, birth_date FROM anitrack.app_user WHERE user_id = ? LIMIT 1',
            [$authUser['user_id']]
        )->getRowArray();

        if ($current === null) {
            return $this->respondError('Akun pengguna tidak ditemukan.', 404);
        }

        $gender    = array_key_exists('gender', $input) ? ($input['gender'] === '' ? null : trim((string) $input['gender'])) : $current['gender'];
        $location  = array_key_exists('location', $input) ? ($input['location'] === '' ? null : trim((string) $input['location'])) : $current['location'];
        $birthDate = array_key_exists('birth_date', $input) ? ($input['birth_date'] === '' ? null : trim((string) $input['birth_date'])) : $current['birth_date'];

        $errors = [];
        if ($gender !== null && ! in_array($gender, ['Male', 'Female', 'Non-Binary'], true)) {
            $errors['gender'] = 'Gender harus salah satu dari: Male, Female, atau Non-Binary.';
        }

        if ($birthDate !== null) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate) || strtotime($birthDate) === false) {
                $errors['birth_date'] = 'Format tanggal lahir harus YYYY-MM-DD.';
            } elseif ($birthDate > date('Y-m-d')) {
                $errors['birth_date'] = 'Tanggal lahir tidak boleh melebihi hari ini.';
            }
        }

        $newPasswordHash = null;
        if (! empty($input['new_password'])) {
            $currentPassword = (string) ($input['current_password'] ?? '');
            $newPassword     = (string) $input['new_password'];

            if ($currentPassword === '' || ! password_verify($currentPassword, (string) $current['password_hash'])) {
                $errors['current_password'] = 'Kata sandi saat ini tidak sesuai.';
            }
            if (strlen($newPassword) < 8) {
                $errors['new_password'] = 'Kata sandi baru minimal 8 karakter.';
            }

            if ($errors === []) {
                $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT);
            }
        }

        if ($errors !== []) {
            return $this->respondValidationError($errors);
        }

        try {
            $db->transBegin();
            RlsContextService::applyTransactionContext($db, $authUser);

            if ($newPasswordHash !== null) {
                $updated = $db->query(
                    <<<'SQL'
                        UPDATE anitrack.app_user
                        SET gender = ?, location = ?, birth_date = ?, password_hash = ?,
                            version = version + 1, updated_at = now()
                        WHERE user_id = ?
                        RETURNING user_id, username, email, role, gender, location, birth_date,
                                  join_date, is_active, version, anitrack.fn_hitung_umur(birth_date) AS age
                    SQL,
                    [$gender, $location, $birthDate, $newPasswordHash, $authUser['user_id']]
                )->getRowArray();
            } else {
                $updated = $db->query(
                    <<<'SQL'
                        UPDATE anitrack.app_user
                        SET gender = ?, location = ?, birth_date = ?,
                            version = version + 1, updated_at = now()
                        WHERE user_id = ?
                        RETURNING user_id, username, email, role, gender, location, birth_date,
                                  join_date, is_active, version, anitrack.fn_hitung_umur(birth_date) AS age
                    SQL,
                    [$gender, $location, $birthDate, $authUser['user_id']]
                )->getRowArray();
            }

            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', '[Auth::updateMe] Gagal memperbarui profil: ' . $e->getMessage());

            return $this->respondError('Gagal memperbarui profil pengguna.', 500);
        }

        return $this->respondSuccess($this->formatUserProfile($updated), 'Profil berhasil diperbarui.');
    }

    /**
     * Memformat data profil user agar tidak pernah menyertakan password_hash.
     */
    private function formatUserProfile(array $row): array
    {
        return [
            'user_id'    => (int) $row['user_id'],
            'username'   => (string) $row['username'],
            'email'      => (string) ($row['email'] ?? ''),
            'role'       => (string) ($row['role'] ?? 'user'),
            'gender'     => $row['gender'] ?? null,
            'location'   => $row['location'] ?? null,
            'birth_date' => $row['birth_date'] ?? null,
            'age'        => isset($row['age']) ? (int) $row['age'] : null,
            'join_date'  => $row['join_date'] ?? null,
            'is_active'  => ($row['is_active'] === true || $row['is_active'] === 't'),
        ];
    }
}
