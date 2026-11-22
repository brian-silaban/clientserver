<?php

namespace App\Services;

use RuntimeException;

/**
 * BackupService
 * Layanan Logical Backup & Verifikasi PostgreSQL (`db_anitrack`) menggunakan `pg_dump -Fc`,
 * checksum SHA-256, dan `pg_restore -l` sesuai standar modul Praktikum Backup & Restore CI4.
 *
 * Keamanan wajib:
 * - Disimpan di `WRITEPATH . 'backups/'` (di luar folder `public/`).
 * - Seluruh argumen shell dibungkus `escapeshellarg()`.
 * - Kredensial akun read-only `r_backup` dibaca lewat `PGPASSFILE` (tidak muncul di argumen proses).
 * - Nama file untuk verifikasi divalidasi dengan regex whitelist ketat (anti path traversal).
 */
class BackupService
{
    public const FILENAME_PATTERN = '/^anitrack_\d{8}_\d{6}\.dump$/';

    private string $backupDir;

    public function __construct()
    {
        $this->backupDir = rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR;

        if (! is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0750, true);
        }
    }

    /**
     * Menjalankan logical backup database `db_anitrack` format custom (-Fc) dan menghasilkan hash SHA-256.
     *
     * @return array{file: string, size: int, size_human: string, sha256: string, created_at: string}
     */
    public function run(): array
    {
        $host   = (string) env('backup.host', 'localhost');
        $port   = (string) env('backup.port', '5432');
        $user   = (string) env('backup.user', 'r_backup');
        $dbName = (string) env('backup.db', 'db_anitrack');
        $pgpass = (string) env('backup.pgpass', WRITEPATH . '.pgpass');

        $fileName = 'anitrack_' . date('Ymd_His') . '.dump';
        $filePath = $this->backupDir . $fileName;

        // Pastikan PGPASSFILE tersedia untuk libpq agar password tidak tertulis di baris perintah
        if ($pgpass !== '' && is_file($pgpass)) {
            putenv('PGPASSFILE=' . $pgpass);
        }

        $command = sprintf(
            'pg_dump -h %s -p %s -U %s -Fc -f %s %s 2>&1',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            escapeshellarg($filePath),
            escapeshellarg($dbName)
        );

        $output   = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || ! is_file($filePath) || filesize($filePath) === 0) {
            if (is_file($filePath)) {
                @unlink($filePath);
            }

            $detail = implode("\n", $output);
            log_message('error', '[BackupService] Gagal menjalankan pg_dump: ' . $detail);
            throw new RuntimeException('Gagal membuat backup database. Periksa log server dan kredensial r_backup.');
        }

        // Hitung dan simpan checksum SHA-256 berdampingan dengan file dump
        $sha256 = hash_file('sha256', $filePath);
        file_put_contents($filePath . '.sha256', $sha256 . '  ' . $fileName . PHP_EOL);

        // Cek cepat struktur arsip (tingkat 3: pg_restore -l)
        $checkOutput = [];
        $checkCode   = 0;
        exec('pg_restore -l ' . escapeshellarg($filePath) . ' > /dev/null 2>&1', $checkOutput, $checkCode);

        if ($checkCode !== 0) {
            log_message('error', '[BackupService] File backup gagal verifikasi pg_restore -l: ' . $fileName);
            throw new RuntimeException('File backup terbentuk tetapi gagal diverifikasi dengan pg_restore -l.');
        }

        $size = (int) filesize($filePath);
        log_message('info', "[BackupService] Backup berhasil dibuat: {$fileName} ({$size} byte)");

        return [
            'file'       => $fileName,
            'size'       => $size,
            'size_human' => $this->formatBytes($size),
            'sha256'     => $sha256,
            'created_at' => date('c', filemtime($filePath)),
        ];
    }

    /**
     * Mengembalikan daftar metadata file backup yang tersimpan di writable/backups/.
     *
     * @return list<array{file: string, size: int, size_human: string, time: string, has_checksum: bool}>
     */
    public function listBackups(): array
    {
        $files = glob($this->backupDir . 'anitrack_*.dump') ?: [];

        // Urutkan dari yang terbaru ke terlama
        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        $result = [];
        foreach ($files as $filePath) {
            $name = basename($filePath);
            if (! preg_match(self::FILENAME_PATTERN, $name)) {
                continue;
            }

            $size     = (int) filesize($filePath);
            $result[] = [
                'file'         => $name,
                'size'         => $size,
                'size_human'   => $this->formatBytes($size),
                'time'         => date('c', filemtime($filePath)),
                'has_checksum' => is_file($filePath . '.sha256'),
            ];
        }

        return $result;
    }

    /**
     * Memvalidasi pola nama file untuk mencegah path traversal seperti ../../.env.
     */
    public function isValidFileName(string $name): bool
    {
        return preg_match(self::FILENAME_PATTERN, $name) === 1;
    }

    /**
     * Memverifikasi integritas file backup: ukuran > 0, checksum SHA-256 (tingkat 2),
     * dan keterbacaan arsip `pg_restore -l` (tingkat 3).
     *
     * @return array{file: string, size: int, checksum: bool, readable: bool, toc_entries: int}|null
     */
    public function verify(string $name): ?array
    {
        if (! $this->isValidFileName($name)) {
            return null;
        }

        $filePath = $this->backupDir . $name;
        if (! is_file($filePath)) {
            return null;
        }

        // Verifikasi tingkat 2: SHA-256
        $checksumOk   = false;
        $checksumFile = $filePath . '.sha256';
        if (is_file($checksumFile)) {
            $storedLine   = trim((string) file_get_contents($checksumFile));
            $storedHash   = preg_split('/\s+/', $storedLine)[0] ?? '';
            $computedHash = (string) hash_file('sha256', $filePath);
            $checksumOk   = ($storedHash !== '' && hash_equals($storedHash, $computedHash));
        }

        // Verifikasi tingkat 3: pg_restore -l
        $output   = [];
        $exitCode = 0;
        exec('pg_restore -l ' . escapeshellarg($filePath) . ' 2>&1', $output, $exitCode);

        $tocEntries = 0;
        if ($exitCode === 0) {
            foreach ($output as $line) {
                $trimmed = trim($line);
                if ($trimmed !== '' && ! str_starts_with($trimmed, ';')) {
                    $tocEntries++;
                }
            }
        }

        log_message('info', "[BackupService] Verifikasi {$name}: checksum=" . ($checksumOk ? 'OK' : 'FAIL') . ', readable=' . ($exitCode === 0 ? 'OK' : 'FAIL'));

        return [
            'file'        => $name,
            'size'        => (int) filesize($filePath),
            'checksum'    => $checksumOk,
            'readable'    => $exitCode === 0,
            'toc_entries' => $tocEntries,
        ];
    }

    /**
     * Mengubah ukuran byte menjadi format yang mudah dibaca manusia.
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }
}
