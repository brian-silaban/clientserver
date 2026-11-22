<?php

namespace App\Controllers\Api;

use App\Libraries\CinemaService;
use App\Models\AnimeModel;
use App\Services\RlsContextService;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Config\Database;
use Exception;
use Throwable;

/**
 * Controller API Anime (`/api/v1/anime/*`)
 * - Endpoint baca (`index`, `meta`, `theatrical`, `byYear`, `show`) terbuka untuk publik (Guest).
 * - Endpoint tulis (`create`, `update`, `delete`) dilindungi `jwt:admin` dengan optimistic locking (`version`).
 */
class Anime extends BaseApiController
{
    private AnimeModel $animeModel;

    public function __construct()
    {
        $this->animeModel = new AnimeModel();
    }

    /**
     * GET /api/v1/anime
     * Daftar seluruh katalog anime dengan paginasi, pencarian, filter tipe/genre/tahun, & pengurutan.
     */
    public function index()
    {
        $q       = trim((string) ($this->request->getGet('q') ?? ''));
        $typeId  = $this->request->getGet('type_id');
        $genreId = $this->request->getGet('genre_id');
        $year    = $this->request->getGet('year');
        $sort    = (string) ($this->request->getGet('sort') ?? 'score');
        $page    = $this->getPage();
        $perPage = $this->getPerPage(12, 100);
        $offset  = ($page - 1) * $perPage;

        $db     = Database::connect('default');
        $where  = [];
        $params = [];

        if ($q !== '') {
            $where[]  = '(a.title ILIKE ? OR a.title_english ILIKE ? OR a.title_japanese ILIKE ?)';
            $pattern  = '%' . $q . '%';
            $params[] = $pattern;
            $params[] = $pattern;
            $params[] = $pattern;
        }

        if ($typeId !== null && ctype_digit((string) $typeId)) {
            $where[]  = 'a.type_id = ?';
            $params[] = (int) $typeId;
        }

        if ($genreId !== null && ctype_digit((string) $genreId)) {
            $where[]  = 'EXISTS (SELECT 1 FROM anitrack.anime_genre ag WHERE ag.anime_id = a.anime_id AND ag.genre_id = ?)';
            $params[] = (int) $genreId;
        }

        if ($year !== null && ctype_digit((string) $year)) {
            $where[]  = 'a.premiered_year = ?';
            $params[] = (int) $year;
        }

        $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        // Whitelist kolom pengurutan untuk mencegah SQL Injection pada ORDER BY
        $orderBySql = match ($sort) {
            'popularity' => 'a.popularity ASC NULLS LAST, a.anime_id ASC',
            'newest'     => 'a.premiered_year DESC NULLS LAST, a.score DESC NULLS LAST',
            'title'      => 'lower(a.title) ASC, a.anime_id ASC',
            'id'         => 'a.anime_id ASC',
            default      => 'a.score DESC NULLS LAST, a.popularity ASC NULLS LAST',
        };

        $countRow  = $db->query("SELECT COUNT(*) AS total FROM anitrack.anime a {$whereSql}", $params)->getRowArray();
        $totalData = (int) ($countRow['total'] ?? 0);
        $totalPage = max(1, (int) ceil($totalData / $perPage));

        $listParams   = array_merge($params, [$perPage, $offset]);
        $listSql      = <<<SQL
            SELECT
                a.anime_id,
                a.title,
                a.title_english,
                a.title_japanese,
                a.image_url,
                a.type_id,
                mt.type_name,
                a.episodes,
                a.status,
                a.duration_min,
                ar.rating_code,
                a.score,
                a.score_rank,
                a.popularity,
                a.premiered_season,
                a.premiered_year,
                a.version,
                m.cinema_movie_id,
                m.screening_status,
                (m.screening_status IN ('NOW_SHOWING', 'UPCOMING')) AS is_theatrical
            FROM anitrack.anime a
            LEFT JOIN anitrack.media_type mt ON mt.type_id = a.type_id
            LEFT JOIN anitrack.age_rating ar ON ar.rating_id = a.rating_id
            LEFT JOIN anitrack.anime_cinema_map m ON m.anime_id = a.anime_id
            {$whereSql}
            ORDER BY {$orderBySql}
            LIMIT ? OFFSET ?
        SQL;

        $rows = $db->query($listSql, $listParams)->getResultArray();
        foreach ($rows as &$row) {
            $row['anime_id']        = (int) $row['anime_id'];
            $row['version']         = (int) ($row['version'] ?? 1);
            $row['cinema_movie_id'] = $row['cinema_movie_id'] !== null ? (int) $row['cinema_movie_id'] : null;
            $row['is_theatrical']   = ($row['is_theatrical'] === true || $row['is_theatrical'] === 't');
        }
        unset($row);

        return $this->respondSuccess($rows, 'Daftar katalog anime.', 200, [
            'pager' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total_data'   => $totalData,
                'total_pages'  => $totalPage,
            ],
        ]);
    }

    /**
     * GET /api/v1/anime/meta
     * Referensi dropdown filter katalog (media_types, genres, age_ratings, watch_statuses).
     */
    public function meta()
    {
        return $this->respondSuccess($this->animeModel->getCatalogMeta(), 'Metadata referensi katalog anime.');
    }

    /**
     * GET /api/v1/anime/tahun/{tahun}
     * Daftar anime berdasarkan tahun rilis.
     */
    public function byYear($tahun = null)
    {
        if (! ctype_digit((string) $tahun)) {
            return $this->respondValidationError(['tahun' => 'Parameter tahun harus berupa angka valid.']);
        }

        $this->request->setGlobal('get', array_merge($this->request->getGet(), ['year' => (string) $tahun]));

        return $this->index();
    }

    /**
     * GET /api/v1/anime/theatrical
     * Daftar KHUSUS anime yang sedang tayang (NOW_SHOWING) atau akan tayang (UPCOMING) di bioskop Medan.
     */
    public function theatrical()
    {
        $statusParam  = strtoupper(trim((string) ($this->request->getGet('status') ?? '')));
        $statusFilter = in_array($statusParam, ['NOW_SHOWING', 'UPCOMING'], true) ? $statusParam : null;

        $data   = $this->animeModel->getTheatricalList($statusFilter);
        $cinema = new CinemaService();

        foreach ($data as &$anime) {
            $movieId = isset($anime['cinema_movie_id']) ? (int) $anime['cinema_movie_id'] : null;
            try {
                $anime['cinema_showtimes'] = $movieId ? $cinema->getShowtimes($movieId, null, null, true) : [];
            } catch (Exception $e) {
                $anime['cinema_showtimes'] = [];
            }
        }
        unset($anime);

        return $this->respondSuccess(
            $data,
            'Daftar anime yang sedang atau akan tayang di bioskop Medan beserta jadwal penayangan.',
            200,
            ['total' => count($data)]
        );
    }

    /**
     * GET /api/v1/anime/{id}
     * Detail lengkap anime + otomatis menyematkan `cinema_showtimes` bila berstatus NOW_SHOWING / UPCOMING.
     */
    public function show($id = null)
    {
        if (! ctype_digit((string) $id)) {
            return $this->respondError("Anime dengan ID {$id} tidak ditemukan.", 404);
        }

        $data = $this->animeModel->getRichDetail((int) $id);
        if ($data === null) {
            return $this->respondError("Anime dengan ID {$id} tidak ditemukan.", 404);
        }

        // Jika anime sedang/akan tayang di bioskop, sematkan jadwal dari CinemaService
        if (! empty($data['is_theatrical']) && ! empty($data['cinema_movie_id'])) {
            try {
                $cinema                   = new CinemaService();
                $data['cinema_showtimes'] = $cinema->getShowtimes((int) $data['cinema_movie_id'], null, null, true);
            } catch (Exception $e) {
                $data['cinema_showtimes'] = [];
            }
        } else {
            $data['cinema_showtimes'] = [];
        }

        return $this->respondSuccess($data, 'Detail anime.');
    }

    /**
     * POST /api/v1/anime (Admin Only)
     * Menambahkan judul anime baru ke `db_anitrack` dengan pencatatan audit otomatis.
     */
    public function create()
    {
        $input = $this->getJsonInput();
        if ($input === null) {
            return $this->respondError('Body request harus berupa JSON dan tidak boleh kosong.', 400);
        }

        unset($input['anime_id'], $input['version']);

        $db = Database::connect('default');

        try {
            $db->transBegin();
            RlsContextService::applyTransactionContext($db, $this->getAuthUser());

            if (! $this->animeModel->insert($input)) {
                $db->transRollback();

                return $this->respondValidationError($this->animeModel->errors());
            }

            $insertedId = (int) $this->animeModel->getInsertID();
            $db->transCommit();
        } catch (DatabaseException $e) {
            $db->transRollback();

            return $this->respondValidationError(['database' => 'Data tidak memenuhi aturan integritas basis data.']);
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', '[Anime::create] ' . $e->getMessage());

            return $this->respondError('Terjadi kesalahan saat menyimpan anime baru.', 500);
        }

        return $this->respondSuccess($this->animeModel->getRichDetail($insertedId), 'Anime berhasil ditambahkan.', 201);
    }

    /**
     * PUT /api/v1/anime/{id} (Admin Only)
     * Memperbarui data anime dengan Optimistic Locking (kolom `version`).
     */
    public function update($id = null)
    {
        if (! ctype_digit((string) $id)) {
            return $this->respondError("Anime dengan ID {$id} tidak ditemukan.", 404);
        }

        $animeId  = (int) $id;
        $existing = $this->animeModel->find($animeId);
        if ($existing === null) {
            return $this->respondError("Anime dengan ID {$animeId} tidak ditemukan.", 404);
        }

        $input = $this->getJsonInput();
        if ($input === null) {
            return $this->respondError('Body request harus berupa JSON dan tidak boleh kosong.', 400);
        }

        // Pemeriksaan Optimistic Locking jika client mengirim versi baris
        $currentVersion = (int) ($existing['version'] ?? 1);
        if (isset($input['version']) && (int) $input['version'] !== $currentVersion) {
            return $this->respondError(
                'Konflik pembaruan: data anime ini telah diubah oleh admin lain. Muat ulang data terbaru sebelum menyimpan.',
                409
            );
        }

        $input['anime_id'] = $animeId;
        $input['version']  = $currentVersion + 1;

        $db = Database::connect('default');

        try {
            $db->transBegin();
            RlsContextService::applyTransactionContext($db, $this->getAuthUser());

            if (! $this->animeModel->update($animeId, $input)) {
                $db->transRollback();

                return $this->respondValidationError($this->animeModel->errors());
            }

            $db->transCommit();
        } catch (DatabaseException $e) {
            $db->transRollback();

            return $this->respondValidationError(['database' => 'Pembaruan ditolak karena melanggar batasan integritas data.']);
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', '[Anime::update] ' . $e->getMessage());

            return $this->respondError('Terjadi kesalahan saat memperbarui data anime.', 500);
        }

        return $this->respondSuccess($this->animeModel->getRichDetail($animeId), 'Data anime berhasil diperbarui.');
    }

    /**
     * DELETE /api/v1/anime/{id} (Admin Only)
     * Menghapus data anime dan menangani constraint ON DELETE RESTRICT dengan HTTP 409 Conflict.
     */
    public function delete($id = null)
    {
        if (! ctype_digit((string) $id)) {
            return $this->respondError("Anime dengan ID {$id} tidak ditemukan.", 404);
        }

        $animeId  = (int) $id;
        $existing = $this->animeModel->find($animeId);
        if ($existing === null) {
            return $this->respondError("Anime dengan ID {$animeId} tidak ditemukan.", 404);
        }

        $db = Database::connect('default');

        try {
            $db->transBegin();
            RlsContextService::applyTransactionContext($db, $this->getAuthUser());
            $this->animeModel->delete($animeId);
            $db->transCommit();
        } catch (DatabaseException $e) {
            $db->transRollback();

            return $this->respondError(
                "Anime ID {$animeId} tidak dapat dihapus karena masih memiliki relasi pada daftar tontonan atau riwayat pemesanan tiket.",
                409
            );
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', '[Anime::delete] ' . $e->getMessage());

            return $this->respondError('Terjadi kesalahan saat menghapus anime.', 500);
        }

        return $this->respondSuccess(['anime_id' => $animeId], "Anime ID {$animeId} berhasil dihapus.");
    }
}
