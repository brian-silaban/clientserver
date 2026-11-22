<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;

/**
 * Seeder Utama AniCine v2.0 (Master Seeder — Khusus db_anitrack)
 *
 * Menjalankan `AnitrackSeeder` (katalog & tabel referensi) dan `AniCineV2Seeder`
 * (akun demo Admin/Member, pemetaan dinamis anime_cinema_map, dan sample watchlist)
 * hanya pada database milik sendiri (`db_anitrack`).
 *
 * Catatan: Database `db_bioskop` milik partner (Satria) TIDAK diubah oleh seeder ini.
 */
class AniCineSeeder extends Seeder
{
    /**
     * Memanggil seluruh seeder milik AniCine pada db_anitrack.
     */
    public function run(): void
    {
        if (is_cli() && ! $this->silent) {
            CLI::write('=== Memulai Seeding AniCine v2.0 (Khusus db_anitrack) ===', 'cyan');
        }

        $this->call('AnitrackSeeder');
        $this->call('AniCineV2Seeder');

        if (is_cli() && ! $this->silent) {
            CLI::write('=== Selesai! Database db_anitrack siap digunakan. ===', 'green');
        }
    }
}
