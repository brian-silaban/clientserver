<?php

namespace App\Commands;

use App\Services\BackupService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * BackupRun
 * Perintah Spark CLI `php spark backup:run` untuk menjalankan logical backup
 * `db_anitrack` via `BackupService` — dirancang untuk penjadwalan otomatis via cron.
 */
class BackupRun extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'backup:run';
    protected $description = 'Menjalankan logical backup pg_dump (-Fc) untuk database db_anitrack beserta checksum SHA-256.';

    public function run(array $params)
    {
        try {
            $result = (new BackupService())->run();
            CLI::write(
                sprintf('OK %s (%s | SHA-256: %s)', $result['file'], $result['size_human'], substr($result['sha256'], 0, 16) . '...'),
                'green'
            );
        } catch (Throwable $e) {
            CLI::error('GAGAL menjalankan backup: ' . $e->getMessage());

            return EXIT_ERROR;
        }

        return EXIT_SUCCESS;
    }
}
