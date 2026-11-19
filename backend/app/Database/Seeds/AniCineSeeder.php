<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;

/**
 * Seeder Utama AniCine (Master Seeder)
 *
 * Menjalankan `AnitrackSeeder` (db_anitrack) dan `BioskopSeeder` (db_bioskop)
 * secara berurutan dalam satu perintah CLI: `php spark db:seed AniCineSeeder`.
 */
class AniCineSeeder extends Seeder
{
    /**
     * Memanggil seluruh seeder database yang dibutuhkan oleh platform AniCine.
     */
    public function run(): void
    {
        if (is_cli() && ! $this->silent) {
            CLI::write('=== Memulai Seeding Platform AniCine (db_anitrack + db_bioskop) ===', 'cyan');
        }

        $this->call('AnitrackSeeder');
        $this->call('BioskopSeeder');

        if (is_cli() && ! $this->silent) {
            CLI::write('=== Selesai! Database db_anitrack & db_bioskop siap digunakan. ===', 'green');
        }
    }
}
