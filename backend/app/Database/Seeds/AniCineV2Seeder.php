<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;

/**
 * Seeder Data Awal AniCine v2.0 (khusus db_anitrack — schema anitrack)
 *
 * Mengisi data operasional v2.0 secara idempoten tanpa menyentuh db_bioskop milik partner:
 * 1. Akun demo Admin (`admin@anicine.local`) dan Member (`user@anicine.local`) dengan hash BCRYPT.
 * 2. Pemetaan dinamis awal 5 anime layar lebar pada `anitrack.anime_cinema_map`
 *    (menggantikan array hardcode PHP, dengan variasi status `NOW_SHOWING` & `UPCOMING`).
 * 3. Contoh watchlist awal untuk akun Member demo agar view `v_user_stats` & `fn_user_status_summary`
 *    langsung memiliki data uji.
 */
class AniCineV2Seeder extends Seeder
{
    /**
     * Grup koneksi target (default -> db_anitrack).
     *
     * @var non-empty-string
     */
    protected $DBGroup = 'default';

    /**
     * Menjalankan pengisian akun demo, pemetaan anime tayang, dan sample watchlist.
     */
    public function run(): void
    {
        if (is_cli() && ! $this->silent) {
            CLI::write('[AniCineV2Seeder] Mengisi data awal v2.0 pada db_anitrack...', 'yellow');
        }

        $this->db->transException(true)->transStart();

        // Set konteks audit agar perubahan dari seeder tercatat rapi sebagai 'seeder'
        $this->db->query("SELECT set_config('app.user_id', 'seeder', true)");
        $this->db->query("SELECT set_config('app.user_role', 'admin', true)");

        $adminUserId  = $this->seedDemoAccount(
            'admin_anicine',
            'admin@anicine.local',
            'Admin#AniCine2026',
            'admin',
            'Male',
            'Medan, Sumatera Utara',
            '2002-05-15'
        );

        $memberUserId = $this->seedDemoAccount(
            'brian_member',
            'user@anicine.local',
            'User#AniCine2026',
            'user',
            'Male',
            'Medan, Sumatera Utara',
            '2004-08-20'
        );

        $this->seedAnimeCinemaMappings();
        $this->seedDemoWatchlist($memberUserId);

        $this->db->transComplete();

        if (is_cli() && ! $this->silent) {
            CLI::write("  - Akun Admin demo  : admin@anicine.local / Admin#AniCine2026 (user_id: {$adminUserId})", 'green');
            CLI::write("  - Akun Member demo : user@anicine.local / User#AniCine2026 (user_id: {$memberUserId})", 'green');
            CLI::write('  - Pemetaan anime_cinema_map & sample watchlist selesai disinkronkan.', 'green');
        }
    }

    /**
     * Membuat atau memperbarui akun demo secara idempoten berdasarkan email/username.
     */
    private function seedDemoAccount(
        string $username,
        string $email,
        string $plainPassword,
        string $role,
        string $gender,
        string $location,
        string $birthDate
    ): int {
        $existing = $this->db->query(
            'SELECT user_id FROM anitrack.app_user WHERE lower(email) = lower(?) OR lower(username) = lower(?) LIMIT 1',
            [$email, $username]
        )->getRowArray();

        if ($existing !== null) {
            return (int) $existing['user_id'];
        }

        $passwordHash = password_hash($plainPassword, PASSWORD_BCRYPT);

        $inserted = $this->db->query(
            <<<'SQL'
                INSERT INTO anitrack.app_user (
                    username, email, password_hash, role, gender, location, birth_date, is_active
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, true)
                RETURNING user_id
            SQL,
            [$username, $email, $passwordHash, $role, $gender, $location, $birthDate]
        )->getRowArray();

        return (int) $inserted['user_id'];
    }

    /**
     * Mengisi 5 pemetaan awal anime <-> movie bioskop Medan secara dinamis.
     */
    private function seedAnimeCinemaMappings(): void
    {
        $mappings = [
            [
                'anime_id'         => 32281,
                'cinema_movie_id'  => 372058,
                'screening_status' => 'NOW_SHOWING',
                'is_featured'      => true,
                'release_note'     => 'Tayang spesial di Delipark XXI & IMAX Medan.',
            ],
            [
                'anime_id'         => 28851,
                'cinema_movie_id'  => 378064,
                'screening_status' => 'NOW_SHOWING',
                'is_featured'      => true,
                'release_note'     => 'Penayangan reguler di Centre Point XXI Medan.',
            ],
            [
                'anime_id'         => 199,
                'cinema_movie_id'  => 129,
                'screening_status' => 'NOW_SHOWING',
                'is_featured'      => true,
                'release_note'     => 'Festival Studio Ghibli di Sun Plaza Cinepolis VIP Medan.',
            ],
            [
                'anime_id'         => 21469,
                'cinema_movie_id'  => 266396,
                'screening_status' => 'NOW_SHOWING',
                'is_featured'      => false,
                'release_note'     => 'Tayang akhir pekan keluarga di Manhattan XXI Medan.',
            ],
            [
                'anime_id'         => 31490,
                'cinema_movie_id'  => 376044,
                'screening_status' => 'UPCOMING',
                'is_featured'      => false,
                'release_note'     => 'Segera tayang di Ringroad City Walks XXI Medan.',
            ],
        ];

        foreach ($mappings as $item) {
            // Pastikan anime_id tersedia di anitrack.anime sebelum memetakan
            $animeExists = $this->db->query(
                'SELECT 1 FROM anitrack.anime WHERE anime_id = ? LIMIT 1',
                [$item['anime_id']]
            )->getRow();

            if ($animeExists === null) {
                continue;
            }

            $this->db->query(
                <<<'SQL'
                    INSERT INTO anitrack.anime_cinema_map (
                        anime_id, cinema_movie_id, screening_status, is_featured, release_note
                    )
                    VALUES (?, ?, ?, ?, ?)
                    ON CONFLICT (anime_id) DO NOTHING
                SQL,
                [
                    $item['anime_id'],
                    $item['cinema_movie_id'],
                    $item['screening_status'],
                    $item['is_featured'] ? 'true' : 'false',
                    $item['release_note'],
                ]
            );
        }
    }

    /**
     * Mengisi contoh watchlist awal untuk akun member demo.
     */
    private function seedDemoWatchlist(int $memberUserId): void
    {
        $entries = [
            ['anime_id' => 32281, 'status_id' => 2, 'watched_episodes' => 1, 'score' => 10], // Kimi no Na wa. (Completed)
            ['anime_id' => 28851, 'status_id' => 2, 'watched_episodes' => 1, 'score' => 9],  // Koe no Katachi (Completed)
            ['anime_id' => 20,    'status_id' => 1, 'watched_episodes' => 45, 'score' => 8], // Naruto (Watching)
            ['anime_id' => 31490, 'status_id' => 6, 'watched_episodes' => 0, 'score' => 0],  // One Piece Film: Gold (Plan to Watch)
        ];

        foreach ($entries as $entry) {
            $animeExists = $this->db->query(
                'SELECT 1 FROM anitrack.anime WHERE anime_id = ? LIMIT 1',
                [$entry['anime_id']]
            )->getRow();

            if ($animeExists === null) {
                continue;
            }

            $this->db->query(
                <<<'SQL'
                    INSERT INTO anitrack.user_anime_list (
                        user_id, anime_id, status_id, watched_episodes, score
                    )
                    VALUES (?, ?, ?, ?, ?)
                    ON CONFLICT (user_id, anime_id) DO NOTHING
                SQL,
                [
                    $memberUserId,
                    $entry['anime_id'],
                    $entry['status_id'],
                    $entry['watched_episodes'],
                    $entry['score'],
                ]
            );
        }
    }
}
