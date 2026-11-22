<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * RlsContextService
 * Menyimpan identitas pengguna terautentikasi pada request aktif dan meneruskannya
 * ke sesi transaksi PostgreSQL (`app.user_id` & `app.user_role`) agar Row Level Security (RLS)
 * dan trigger audit `anitrack.fn_audit()` bekerja akurat.
 */
class RlsContextService
{
    /**
     * Data pengguna dari token JWT pada request HTTP yang sedang berjalan.
     *
     * @var array{user_id: int, username: string, email: string, role: string}|null
     */
    private static ?array $authUser = null;

    /**
     * Menyimpan klaim user dari JwtFilter untuk diakses controller.
     *
     * @param array{user_id: int, username: string, email: string, role: string}|null $user
     */
    public static function setAuthUser(?array $user): void
    {
        self::$authUser = $user;
    }

    /**
     * Mengambil data user terautentikasi saat ini (null jika Guest).
     *
     * @return array{user_id: int, username: string, email: string, role: string}|null
     */
    public static function getAuthUser(): ?array
    {
        return self::$authUser;
    }

    /**
     * Menerapkan konteks sesi RLS secara lokal pada transaksi aktif PostgreSQL.
     * Parameter ketiga `true` (`is_local`) memastikan nilai otomatis direset saat transaksi berakhir.
     */
    public static function applyTransactionContext(BaseConnection $db, ?array $user = null): void
    {
        $activeUser = $user ?? self::$authUser;
        if ($activeUser === null) {
            return;
        }

        $db->query("SELECT set_config('app.user_id', ?, true)", [(string) $activeUser['user_id']]);
        $db->query("SELECT set_config('app.user_role', ?, true)", [(string) $activeUser['role']]);
    }
}
