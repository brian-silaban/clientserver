<?php

namespace App\Controllers\Api;

use App\Services\RlsContextService;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Config\Database;
use Throwable;

/**
 * Controller API Watchlist / My Anime List (`/api/v1/watchlist/*`)
 * Membutuhkan autentikasi JWT (`jwt:user,admin`).
 *
 * Memanfaatkan objek database PostgreSQL `db_anitrack`:
 * - RLS pada `anitrack.user_anime_list`
 * - View `anitrack.v_user_stats`
 * - Function `anitrack.fn_user_status_summary(integer)`
 * - Trigger `anitrack.trg_user_anime_validate` & `anitrack.trg_audit_user_anime_list`
 */
class Watchlist extends BaseApiController
{
    /**
     * GET /api/v1/watchlist
     * Mengambil daftar tontonan pribadi, statistik `v_user_stats`, dan rekap `fn_user_status_summary`.
     */
    public function index()
    {
        $authUser = $this->getAuthUser();
        $statusId = $this->request->getGet('status_id');
        $db       = Database::connect('default');

        $db->transBegin();
        RlsContextService::applyTransactionContext($db, $authUser);

        $whereSql = 'WHERE ual.user_id = ?';
        $params   = [$authUser['user_id']];

        if ($statusId !== null && ctype_digit((string) $statusId)) {
            $whereSql .= ' AND ual.status_id = ?';
            $params[]  = (int) $statusId;
        }

        $items = $db->query(
            <<<SQL
                SELECT
                    ual.user_id,
                    ual.anime_id,
                    ual.status_id,
                    ws.status_name,
                    ual.watched_episodes,
                    ual.score,
                    ual.start_date,
                    ual.finish_date,
                    ual.is_rewatching,
                    ual.last_updated,
                    a.title,
                    a.title_english,
                    a.image_url,
                    a.episodes AS total_episodes,
                    a.score AS anime_score,
                    mt.type_name
                FROM anitrack.user_anime_list ual
                JOIN anitrack.anime a ON a.anime_id = ual.anime_id
                JOIN anitrack.watch_status ws ON ws.status_id = ual.status_id
                LEFT JOIN anitrack.media_type mt ON mt.type_id = a.type_id
                {$whereSql}
                ORDER BY ual.last_updated DESC
            SQL,
            $params
        )->getResultArray();

        $stats = $db->query(
            'SELECT * FROM anitrack.v_user_stats WHERE user_id = ? LIMIT 1',
            [$authUser['user_id']]
        )->getRowArray();

        $summary = $db->query(
            'SELECT r_status AS status_name, r_total_anime AS total_anime, r_total_episodes AS total_episodes FROM anitrack.fn_user_status_summary(?)',
            [$authUser['user_id']]
        )->getResultArray();

        $db->transCommit();

        foreach ($items as &$item) {
            $item['anime_id']         = (int) $item['anime_id'];
            $item['status_id']        = (int) $item['status_id'];
            $item['watched_episodes'] = (int) $item['watched_episodes'];
            $item['total_episodes']   = (int) ($item['total_episodes'] ?? 0);
            $item['score']            = (int) $item['score'];
            $item['is_rewatching']    = ($item['is_rewatching'] === true || $item['is_rewatching'] === 't');
        }
        unset($item);

        return $this->respondSuccess([
            'items'   => $items,
            'stats'   => $stats,
            'summary' => $summary,
        ], 'Daftar tontonan anime pribadi Anda.');
    }

    /**
     * PUT /api/v1/watchlist/{anime_id}
     * Menambah atau memperbarui entri anime pada daftar tontonan pribadi (upsert + trigger validasi).
     */
    public function upsert($animeIdParam = null)
    {
        if (! ctype_digit((string) $animeIdParam)) {
            return $this->respondValidationError(['anime_id' => 'ID anime harus berupa angka valid.']);
        }

        $animeId = (int) $animeIdParam;
        $input   = $this->getJsonInput();
        if ($input === null) {
            return $this->respondError('Body request harus berupa JSON dan tidak boleh kosong.', 400);
        }

        $statusId        = isset($input['status_id']) && ctype_digit((string) $input['status_id']) ? (int) $input['status_id'] : 0;
        $watchedEpisodes = isset($input['watched_episodes']) ? (int) $input['watched_episodes'] : 0;
        $score           = isset($input['score']) ? (int) $input['score'] : 0;
        $isRewatching    = ! empty($input['is_rewatching']);

        $errors = [];
        if (! in_array($statusId, [1, 2, 3, 4, 6], true)) {
            $errors['status_id'] = 'Status tontonan harus salah satu dari: 1 (Watching), 2 (Completed), 3 (On Hold), 4 (Dropped), atau 6 (Plan to Watch).';
        }
        if ($watchedEpisodes < 0) {
            $errors['watched_episodes'] = 'Jumlah episode yang ditonton tidak boleh negatif.';
        }
        if ($score < 0 || $score > 10) {
            $errors['score'] = 'Skor pribadi harus berada di rentang 0 sampai 10.';
        }

        if ($errors !== []) {
            return $this->respondValidationError($errors);
        }

        $authUser = $this->getAuthUser();
        $db       = Database::connect('default');

        // Pastikan anime tersedia
        $anime = $db->query('SELECT anime_id, title FROM anitrack.anime WHERE anime_id = ? LIMIT 1', [$animeId])->getRowArray();
        if ($anime === null) {
            return $this->respondError("Anime ID {$animeId} tidak ditemukan.", 404);
        }

        try {
            $db->transBegin();
            RlsContextService::applyTransactionContext($db, $authUser);

            // Trigger trg_user_anime_validate otomatis memeriksa batas episode & melengkapi episode bila status Completed
            $queryResult = $db->query(
                <<<'SQL'
                    INSERT INTO anitrack.user_anime_list (
                        user_id, anime_id, status_id, watched_episodes, score, is_rewatching
                    )
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON CONFLICT (user_id, anime_id) DO UPDATE
                    SET status_id        = EXCLUDED.status_id,
                        watched_episodes = EXCLUDED.watched_episodes,
                        score            = EXCLUDED.score,
                        is_rewatching    = EXCLUDED.is_rewatching,
                        last_updated     = now()
                    RETURNING user_id, anime_id, status_id, watched_episodes, score, is_rewatching, last_updated
                SQL,
                [
                    $authUser['user_id'],
                    $animeId,
                    $statusId,
                    $watchedEpisodes,
                    $score,
                    $isRewatching ? 'true' : 'false',
                ]
            );

            if ($queryResult === false) {
                $dbError = (string) ($db->error()['message'] ?? '');
                $db->transRollback();

                if (str_contains($dbError, 'Episode ditonton')) {
                    return $this->respondValidationError([
                        'watched_episodes' => 'Jumlah episode yang ditonton melebihi total episode anime ini.',
                    ]);
                }

                return $this->respondError('Gagal menyimpan daftar tontonan.', 500);
            }

            $saved = $queryResult->getRowArray();
            $db->transCommit();
        } catch (DatabaseException $e) {
            $db->transRollback();

            // Pesan validasi langsung dari trigger PL/pgSQL fn_validate_user_anime()
            if (str_contains($e->getMessage(), 'Episode ditonton')) {
                return $this->respondValidationError([
                    'watched_episodes' => 'Jumlah episode yang ditonton melebihi total episode anime ini.',
                ]);
            }

            log_message('error', '[Watchlist::upsert] ' . $e->getMessage());

            return $this->respondError('Gagal menyimpan daftar tontonan.', 500);
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', '[Watchlist::upsert] ' . $e->getMessage());

            return $this->respondError('Terjadi kesalahan saat memperbarui watchlist.', 500);
        }

        return $this->respondSuccess($saved, 'Daftar tontonan berhasil disimpan.');
    }

    /**
     * DELETE /api/v1/watchlist/{anime_id}
     * Menghapus entri anime dari daftar tontonan pribadi (RLS enforced).
     */
    public function delete($animeIdParam = null)
    {
        if (! ctype_digit((string) $animeIdParam)) {
            return $this->respondError('Entri watchlist tidak ditemukan.', 404);
        }

        $animeId  = (int) $animeIdParam;
        $authUser = $this->getAuthUser();
        $db       = Database::connect('default');

        $db->transBegin();
        RlsContextService::applyTransactionContext($db, $authUser);

        $deleted = $db->query(
            'DELETE FROM anitrack.user_anime_list WHERE user_id = ? AND anime_id = ? RETURNING anime_id',
            [$authUser['user_id'], $animeId]
        )->getRowArray();

        $db->transCommit();

        if ($deleted === null) {
            return $this->respondError('Anime tersebut tidak ada di daftar tontonan Anda.', 404);
        }

        return $this->respondSuccess(['anime_id' => $animeId], 'Anime berhasil dihapus dari daftar tontonan Anda.');
    }
}
