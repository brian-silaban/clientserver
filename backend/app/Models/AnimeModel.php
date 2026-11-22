<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * AnimeModel
 * Mengelola katalog anime pada `db_anitrack` (schema `anitrack`) serta membaca
 * pemetaan tayang dinamis dari view `anitrack.v_anime_theatrical` dan tabel `anitrack.anime_cinema_map`.
 */
class AnimeModel extends Model
{
    protected $table         = 'anime';
    protected $primaryKey    = 'anime_id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'title', 'title_english', 'title_japanese', 'image_url',
        'type_id', 'source', 'episodes', 'status',
        'aired_from', 'aired_to', 'duration_min', 'rating_id',
        'score', 'scored_by', 'score_rank', 'popularity',
        'members', 'favorites', 'background',
        'premiered_season', 'premiered_year', 'broadcast',
        'version',
    ];

    protected $useTimestamps = false;

    protected $beforeInsert = ['emptyStringToNull'];
    protected $beforeUpdate = ['emptyStringToNull'];

    protected $validationRules = [
        'anime_id'         => 'permit_empty|is_natural_no_zero',
        'title'            => 'required|max_length[255]|is_unique[anime.title,anime_id,{anime_id}]',
        'title_english'    => 'permit_empty|max_length[255]',
        'title_japanese'   => 'permit_empty|max_length[255]',
        'image_url'        => 'permit_empty|valid_url_strict|max_length[255]',
        'type_id'          => 'required|is_natural_no_zero|is_not_unique[media_type.type_id]',
        'source'           => 'permit_empty|max_length[30]',
        'episodes'         => 'permit_empty|is_natural',
        'status'           => 'permit_empty|in_list[Finished Airing,Currently Airing,Not yet aired]',
        'aired_from'       => 'permit_empty|valid_date[Y-m-d]',
        'aired_to'         => 'permit_empty|valid_date[Y-m-d]',
        'duration_min'     => 'permit_empty|is_natural_no_zero',
        'rating_id'        => 'permit_empty|is_natural_no_zero|is_not_unique[age_rating.rating_id]',
        'score'            => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[10]',
        'scored_by'        => 'permit_empty|is_natural',
        'score_rank'       => 'permit_empty|is_natural_no_zero',
        'popularity'       => 'permit_empty|is_natural_no_zero',
        'members'          => 'permit_empty|is_natural',
        'favorites'        => 'permit_empty|is_natural',
        'background'       => 'permit_empty|string',
        'premiered_season' => 'permit_empty|in_list[Winter,Spring,Summer,Fall]',
        'premiered_year'   => 'permit_empty|integer|greater_than_equal_to[1900]|less_than_equal_to[2100]',
        'broadcast'        => 'permit_empty|max_length[50]',
        'version'          => 'permit_empty|is_natural_no_zero',
    ];

    protected $validationMessages = [
        'title' => [
            'required'   => 'Judul (title) wajib diisi.',
            'max_length' => 'Judul maksimal 255 karakter.',
            'is_unique'  => 'Judul anime sudah terdaftar.',
        ],
        'image_url' => ['valid_url_strict' => 'Format image_url tidak valid (harus diawali http:// atau https://).'],
        'type_id'   => [
            'required'           => 'Tipe media (type_id) wajib diisi.',
            'is_natural_no_zero' => 'type_id harus berupa angka positif.',
            'is_not_unique'      => 'type_id tidak terdaftar di tabel media_type.',
        ],
        'episodes'  => ['is_natural' => 'Jumlah episode harus angka >= 0.'],
        'status'    => ['in_list' => 'Status harus salah satu dari: Finished Airing, Currently Airing, Not yet aired.'],
        'aired_from' => ['valid_date' => 'Format aired_from harus YYYY-MM-DD.'],
        'aired_to'   => ['valid_date' => 'Format aired_to harus YYYY-MM-DD.'],
        'rating_id' => [
            'is_natural_no_zero' => 'rating_id harus berupa angka positif.',
            'is_not_unique'      => 'rating_id tidak terdaftar di tabel age_rating.',
        ],
        'score' => [
            'decimal'               => 'Skor harus berupa angka.',
            'greater_than_equal_to' => 'Skor minimal 0.',
            'less_than_equal_to'    => 'Skor maksimal 10.',
        ],
        'premiered_season' => ['in_list' => 'Season harus salah satu dari: Winter, Spring, Summer, Fall.'],
        'premiered_year'   => [
            'integer'               => 'Tahun tayang harus berupa angka.',
            'greater_than_equal_to' => 'Tahun tayang minimal 1900.',
            'less_than_equal_to'    => 'Tahun tayang maksimal 2100.',
        ],
    ];

    protected function emptyStringToNull(array $data): array
    {
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $key => $value) {
                if ($value === '') {
                    $data['data'][$key] = null;
                }
            }
        }

        return $data;
    }

    /**
     * Mengambil daftar anime yang sedang tayang (NOW_SHOWING) atau akan tayang (UPCOMING)
     * secara dinamis dari view `anitrack.v_anime_theatrical`.
     */
    public function getTheatricalList(?string $statusFilter = null): array
    {
        $sql    = 'SELECT * FROM anitrack.v_anime_theatrical ';
        $params = [];

        if ($statusFilter !== null && in_array($statusFilter, ['NOW_SHOWING', 'UPCOMING'], true)) {
            $sql .= 'WHERE screening_status = ? ';
            $params[] = $statusFilter;
        }

        $sql .= 'ORDER BY is_featured DESC, CASE WHEN screening_status = \'NOW_SHOWING\' THEN 0 ELSE 1 END, score DESC NULLS LAST';

        $rows = $this->db->query($sql, $params)->getResultArray();

        foreach ($rows as &$row) {
            $row['anime_id']        = (int) $row['anime_id'];
            $row['cinema_movie_id'] = (int) $row['cinema_movie_id'];
            $row['is_featured']     = ($row['is_featured'] === true || $row['is_featured'] === 't');
            $row['is_theatrical']   = true;
        }
        unset($row);

        return $rows;
    }

    /**
     * Mengambil detail lengkap satu anime beserta relasi tipe, rating usia, genre, studio,
     * dan status pemetaan bioskop dari `anitrack.anime_cinema_map`.
     */
    public function getRichDetail(int $animeId): ?array
    {
        $sql = <<<'SQL'
            SELECT
                a.*,
                mt.type_name,
                ar.rating_code,
                ar.description AS rating_description,
                m.map_id,
                m.cinema_movie_id,
                m.screening_status,
                m.is_featured,
                m.release_note,
                (
                    SELECT string_agg(g.genre_name::text, ', ' ORDER BY g.genre_name::text)
                    FROM anitrack.anime_genre ag
                    JOIN anitrack.genre g ON g.genre_id = ag.genre_id
                    WHERE ag.anime_id = a.anime_id
                ) AS genres,
                (
                    SELECT string_agg(c.company_name::text, ', ' ORDER BY c.company_name::text)
                    FROM anitrack.anime_company ac
                    JOIN anitrack.company c ON c.company_id = ac.company_id
                    WHERE ac.anime_id = a.anime_id AND ac.role = 'studio'
                ) AS studios
            FROM anitrack.anime a
            LEFT JOIN anitrack.media_type mt ON mt.type_id = a.type_id
            LEFT JOIN anitrack.age_rating ar ON ar.rating_id = a.rating_id
            LEFT JOIN anitrack.anime_cinema_map m ON m.anime_id = a.anime_id
            WHERE a.anime_id = ?
            LIMIT 1
        SQL;

        $row = $this->db->query($sql, [$animeId])->getRowArray();

        if (! $row) {
            return null;
        }

        $screeningStatus        = $row['screening_status'] ?? null;
        $isActiveTheatrical     = in_array($screeningStatus, ['NOW_SHOWING', 'UPCOMING'], true);
        $row['anime_id']        = (int) $row['anime_id'];
        $row['version']         = (int) ($row['version'] ?? 1);
        $row['cinema_movie_id'] = $row['cinema_movie_id'] !== null ? (int) $row['cinema_movie_id'] : null;
        $row['is_theatrical']   = $isActiveTheatrical;
        $row['is_featured']     = ($row['is_featured'] === true || $row['is_featured'] === 't');

        return $row;
    }

    /**
     * Mengambil metadata dropdown untuk filter katalog (media_types, genres, age_ratings, watch_statuses).
     */
    public function getCatalogMeta(): array
    {
        return [
            'media_types' => $this->db->query(
                'SELECT type_id, type_name FROM anitrack.media_type ORDER BY type_id ASC'
            )->getResultArray(),
            'genres' => $this->db->query(
                'SELECT genre_id, genre_name FROM anitrack.genre ORDER BY genre_name ASC'
            )->getResultArray(),
            'age_ratings' => $this->db->query(
                'SELECT rating_id, rating_code, description FROM anitrack.age_rating ORDER BY rating_id ASC'
            )->getResultArray(),
            'watch_statuses' => $this->db->query(
                'SELECT status_id, status_name FROM anitrack.watch_status ORDER BY status_id ASC'
            )->getResultArray(),
        ];
    }
}
