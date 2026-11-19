<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;

/**
 * Seeder Database AniTrack (db_anitrack - schema anitrack)
 *
 * Mengisi tabel referensi (`media_type`, `age_rating`, `watch_status`, `genre`, `company`)
 * serta 100 anime populer beserta relasi `anime_genre` dan `anime_company` dari dataset ringan.
 * Aman dijalankan berulang kali (`ON CONFLICT DO NOTHING`) dan otomatis menyelaraskan sequence ID.
 */
class AnitrackSeeder extends BasePostgresSeeder
{
    /**
     * Grup koneksi database target pada app/Config/Database.php.
     *
     * @var non-empty-string
     */
    protected $DBGroup = 'default';

    /**
     * Menjalankan proses pengisian data anitrack secara berurutan sesuai relasi Foreign Key.
     */
    public function run(): void
    {
        $dataset = require __DIR__ . '/Data/anitrack_data.php';

        if (is_cli() && ! $this->silent) {
            CLI::write('[AniTrackSeeder] Mengisi database db_anitrack (schema anitrack)...', 'yellow');
        }

        $this->db->transException(true)->transStart();

        // 1. Tabel referensi utama (harus diisi sebelum tabel anak)
        $insertedMediaType = $this->insertBatchIgnoreConflict(
            'anitrack.media_type',
            ['type_id', 'type_name'],
            $dataset['media_type']
        );
        $this->logSeedSummary('anitrack.media_type', count($dataset['media_type']), $insertedMediaType);

        $insertedAgeRating = $this->insertBatchIgnoreConflict(
            'anitrack.age_rating',
            ['rating_id', 'rating_code', 'description'],
            $dataset['age_rating']
        );
        $this->logSeedSummary('anitrack.age_rating', count($dataset['age_rating']), $insertedAgeRating);

        $insertedWatchStatus = $this->insertBatchIgnoreConflict(
            'anitrack.watch_status',
            ['status_id', 'status_name'],
            $dataset['watch_status']
        );
        $this->logSeedSummary('anitrack.watch_status', count($dataset['watch_status']), $insertedWatchStatus);

        $insertedGenre = $this->insertBatchIgnoreConflict(
            'anitrack.genre',
            ['genre_id', 'genre_name'],
            $dataset['genre']
        );
        $this->logSeedSummary('anitrack.genre', count($dataset['genre']), $insertedGenre);

        $insertedCompany = $this->insertBatchIgnoreConflict(
            'anitrack.company',
            ['company_id', 'company_name'],
            $dataset['company']
        );
        $this->logSeedSummary('anitrack.company', count($dataset['company']), $insertedCompany);

        // 2. Tabel inti katalog anime (100 judul pilihan termasuk 5 movie bioskop + Naruto)
        $animeColumns = [
            'anime_id', 'title', 'title_english', 'title_japanese', 'image_url',
            'type_id', 'source', 'episodes', 'status', 'aired_from', 'aired_to',
            'duration_min', 'rating_id', 'score', 'scored_by', 'score_rank',
            'popularity', 'members', 'favorites', 'background',
            'premiered_season', 'premiered_year', 'broadcast',
        ];

        $insertedAnime = $this->insertBatchIgnoreConflict(
            'anitrack.anime',
            $animeColumns,
            $dataset['anime'],
            [],
            25
        );
        $this->logSeedSummary('anitrack.anime', count($dataset['anime']), $insertedAnime);

        // 3. Tabel relasi many-to-many
        $insertedAnimeGenre = $this->insertBatchIgnoreConflict(
            'anitrack.anime_genre',
            ['anime_id', 'genre_id'],
            $dataset['anime_genre']
        );
        $this->logSeedSummary('anitrack.anime_genre', count($dataset['anime_genre']), $insertedAnimeGenre);

        $insertedAnimeCompany = $this->insertBatchIgnoreConflict(
            'anitrack.anime_company',
            ['anime_id', 'company_id', 'role'],
            $dataset['anime_company']
        );
        $this->logSeedSummary('anitrack.anime_company', count($dataset['anime_company']), $insertedAnimeCompany);

        // 4. Sinkronisasi sequence agar operasi POST /api/anime tidak bentrok ID
        $this->syncSequence('anitrack.age_rating_rating_id_seq', 'anitrack.age_rating', 'rating_id', 6);
        $this->syncSequence('anitrack.anime_anime_id_seq', 'anitrack.anime', 'anime_id', 37919);
        $this->syncSequence('anitrack.company_company_id_seq', 'anitrack.company', 'company_id', 1328);
        $this->syncSequence('anitrack.genre_genre_id_seq', 'anitrack.genre', 'genre_id', 43);
        $this->syncSequence('anitrack.media_type_type_id_seq', 'anitrack.media_type', 'type_id', 6);

        $this->db->transComplete();
    }
}
