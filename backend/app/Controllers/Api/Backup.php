<?php

namespace App\Controllers\Api;

use App\Services\BackupService;
use Throwable;

/**
 * Controller API Backup (`/api/v1/backups/*`)
 * Hanya dapat diakses oleh Administrator (`filter: jwt:admin`).
 *
 * Sesuai standar Praktikum Backup & Restore CI4 (PLMKB502):
 * - Menyediakan daftar metadata backup (`index`), pemicu dump (`create`), dan verifikasi (`verify`).
 * - TIDAK membuka endpoint restore maupun unduhan publik demi keamanan data.
 */
class Backup extends BaseApiController
{
    private BackupService $backupService;

    public function __construct()
    {
        $this->backupService = new BackupService();
    }

    /**
     * GET /api/v1/backups
     * Menampilkan daftar metadata file backup `.dump` di `writable/backups/`.
     */
    public function index()
    {
        $files = $this->backupService->listBackups();

        return $this->respondSuccess($files, 'Daftar arsip backup database db_anitrack.', 200, [
            'total' => count($files),
        ]);
    }

    /**
     * POST /api/v1/backups
     * Memicu logical backup `pg_dump -Fc` + checksum SHA-256 secara aman.
     */
    public function create()
    {
        $authUser = $this->getAuthUser();
        log_message('info', '[Api\Backup::create] Backup dipicu oleh admin user_id=' . ($authUser['user_id'] ?? 'unknown'));

        try {
            $result = $this->backupService->run();
        } catch (Throwable $e) {
            return $this->respondError($e->getMessage(), 500);
        }

        return $this->respondSuccess($result, 'Backup database db_anitrack berhasil dibuat.', 201);
    }

    /**
     * GET /api/v1/backups/{name}/verify
     * Memverifikasi integritas file backup (Tingkat 2: SHA-256 & Tingkat 3: `pg_restore -l`).
     */
    public function verify(?string $name = null)
    {
        $fileName = (string) ($name ?? '');

        if (! $this->backupService->isValidFileName($fileName)) {
            return $this->respondValidationError([
                'name' => 'Nama file backup tidak valid. Format yang diizinkan: anitrack_YYYYMMDD_HHMMSS.dump',
            ], 'Nama file backup ditolak oleh validasi keamanan.');
        }

        $result = $this->backupService->verify($fileName);
        if ($result === null) {
            return $this->respondError("File backup {$fileName} tidak ditemukan.", 404);
        }

        $isHealthy = $result['checksum'] && $result['readable'];

        return $this->respondSuccess(
            $result,
            $isHealthy ? 'Verifikasi backup lulus: checksum SHA-256 cocok dan arsip terbaca.' : 'Peringatan: hasil verifikasi backup tidak konsisten.'
        );
    }
}
