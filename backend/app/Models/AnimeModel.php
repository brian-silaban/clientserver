<?php

namespace App\Models;

use CodeIgniter\Model;

class AnimeModel extends Model
{
    // Schema "anitrack" diatur lewat database.default.schema di .env (search_path)
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
    ];

    // Tabel anime tidak memiliki kolom created_at & updated_at
    protected $useTimestamps = false;

    // String kosong "" diubah menjadi NULL agar tidak error di kolom integer/date PostgreSQL
    protected $beforeInsert = ['emptyStringToNull'];
    protected $beforeUpdate = ['emptyStringToNull'];

    // Validasi dijalankan otomatis saat insert() dan update()
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
    ];

    protected $validationMessages = [
        'title' => [
            'required'   => 'Judul (title) wajib diisi.',
            'max_length' => 'Judul maksimal 255 karakter.',
            'is_unique'  => 'Judul anime sudah terdaftar.',
        ],
        'image_url' => ['valid_url_strict' => 'Format image_url tidak valid (harus diawali http:// atau https://).'],
        'type_id'   => [
            'required'           => 'Tipe (type_id) wajib diisi.',
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
            'decimal'               => 'Score harus berupa angka.',
            'greater_than_equal_to' => 'Score minimal 0.',
            'less_than_equal_to'    => 'Score maksimal 10.',
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

    public const THEATRICAL_MAPPING = [
        32281 => 372058, // Kimi no Na wa.
        28851 => 378064, // Koe no Katachi
        199   => 129,    // Sen to Chihiro no Kamikakushi (Spirited Away)
        21469 => 266396, // Stand By Me Doraemon
        31490 => 376044, // One Piece Film: Gold
    ];

    /**
     * Mengambil daftar anime movie yang sedang tayang di bioskop.
     */
    public function getTheatricalList(): array
    {
        $theatricalIds = array_keys(self::THEATRICAL_MAPPING);

        $builder = $this->builder()
            ->select('
                anime.anime_id,
                anime.title,
                anime.title_english,
                anime.title_japanese,
                anime.image_url,
                anime.duration_min,
                anime.score,
                anime.score_rank,
                anime.popularity,
                anime.background,
                mt.type_name,
                ar.rating_code,
                STRING_AGG(DISTINCT g.genre_name, \', \' ORDER BY g.genre_name) AS genres,
                STRING_AGG(DISTINCT c.company_name, \', \' ORDER BY c.company_name) AS studios
            ')
            ->join('anitrack.media_type mt', 'anime.type_id = mt.type_id', 'left')
            ->join('anitrack.age_rating ar', 'anime.rating_id = ar.rating_id', 'left')
            ->join('anitrack.anime_genre ag', 'anime.anime_id = ag.anime_id', 'left')
            ->join('anitrack.genre g', 'ag.genre_id = g.genre_id', 'left')
            ->join('anitrack.anime_company ac', 'anime.anime_id = ac.anime_id', 'left')
            ->join('anitrack.company c', 'ac.company_id = c.company_id', 'left')
            ->whereIn('anime.anime_id', $theatricalIds)
            ->groupBy('anime.anime_id, mt.type_name, ar.rating_code');

        $rows = $builder->get()->getResultArray();

        foreach ($rows as &$row) {
            $row['cinema_movie_id'] = self::THEATRICAL_MAPPING[(int) $row['anime_id']] ?? null;
            $row['is_theatrical']   = true;
        }
        unset($row);

        return $rows;
    }

    /**
     * Mengambil data detail lengkap satu anime beserta relasi type, rating usia, genre, dan studio.
     */
    public function getRichDetail(int $animeId): ?array
    {
        $builder = $this->builder()
            ->select('
                anime.*,
                mt.type_name,
                ar.rating_code,
                ar.description AS rating_description,
                STRING_AGG(DISTINCT g.genre_name, \', \' ORDER BY g.genre_name) AS genres,
                STRING_AGG(DISTINCT c.company_name, \', \' ORDER BY c.company_name) AS studios
            ')
            ->join('anitrack.media_type mt', 'anime.type_id = mt.type_id', 'left')
            ->join('anitrack.age_rating ar', 'anime.rating_id = ar.rating_id', 'left')
            ->join('anitrack.anime_genre ag', 'anime.anime_id = ag.anime_id', 'left')
            ->join('anitrack.genre g', 'ag.genre_id = g.genre_id', 'left')
            ->join('anitrack.anime_company ac', 'anime.anime_id = ac.anime_id', 'left')
            ->join('anitrack.company c', 'ac.company_id = c.company_id', 'left')
            ->where('anime.anime_id', $animeId)
            ->groupBy('anime.anime_id, mt.type_name, ar.rating_code, ar.description');

        $row = $builder->get()->getRowArray();

        if (! $row) {
            return null;
        }

        $row['cinema_movie_id'] = self::THEATRICAL_MAPPING[$animeId] ?? null;
        $row['is_theatrical']   = isset(self::THEATRICAL_MAPPING[$animeId]);

        return $row;
    }
}
