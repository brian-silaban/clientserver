<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;

/**
 * Kelas dasar Seeder PostgreSQL untuk projek AniCine.
 *
 * Menyediakan helper idempoten (`INSERT ... ON CONFLICT DO NOTHING` secara batch)
 * dan sinkronisasi sequence PostgreSQL (`setval`) agar proses seeding aman dijalankan
 * berulang kali tanpa merusak data yang sudah ada maupun menimbulkan konflik PK.
 */
abstract class BasePostgresSeeder extends Seeder
{
    /**
     * Memasukkan banyak baris ke tabel PostgreSQL dengan penanganan duplikat (`ON CONFLICT DO NOTHING`).
     *
     * @param string              $table          Nama tabel lengkap dengan schema (contoh: 'anitrack.anime').
     * @param array<int, string>  $columns        Daftar nama kolom berurutan sesuai isi setiap baris.
     * @param array<int, array>   $rows           Daftar baris data (indexed array sejajar dengan $columns).
     * @param array<int, string>  $conflictTarget Kolom kunci konflik (kosong = ON CONFLICT DO NOTHING umum).
     * @param int                 $chunkSize      Jumlah baris per batch query agar hemat memori.
     */
    protected function insertBatchIgnoreConflict(
        string $table,
        array $columns,
        array $rows,
        array $conflictTarget = [],
        int $chunkSize = 100
    ): int {
        if ($rows === []) {
            return 0;
        }

        $columnList   = implode(', ', $columns);
        $conflictSql  = $conflictTarget === []
            ? 'ON CONFLICT DO NOTHING'
            : 'ON CONFLICT (' . implode(', ', $conflictTarget) . ') DO NOTHING';

        $totalInserted = 0;

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $valueGroups = [];

            foreach ($chunk as $row) {
                $escapedValues = [];

                foreach ($row as $value) {
                    $escapedValues[] = $this->db->escape($value);
                }

                $valueGroups[] = '(' . implode(', ', $escapedValues) . ')';
            }

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES %s %s;',
                $table,
                $columnList,
                implode(', ', $valueGroups),
                $conflictSql
            );

            $this->db->query($sql);
            $totalInserted += $this->db->affectedRows();
        }

        return $totalInserted;
    }

    /**
     * Menyelaraskan nilai sequence PostgreSQL dengan nilai minimal yang aman agar INSERT baru tidak bentrok.
     *
     * @param string $sequenceName Nama sequence lengkap dengan schema.
     * @param string $tableName    Nama tabel pemilik kolom primary key.
     * @param string $columnName   Nama kolom primary key.
     * @param int    $minValue     Batas minimum sequence (berguna menjaga offset ID asli seperti 37919).
     */
    protected function syncSequence(
        string $sequenceName,
        string $tableName,
        string $columnName,
        int $minValue = 1
    ): void {
        $safeMinValue = max(1, $minValue);
        $row = $this->db->query(
            "SELECT GREATEST(COALESCE(MAX({$columnName}), 0), {$safeMinValue}) AS next_val FROM {$tableName};"
        )->getRow();

        $nextVal = $row ? max(1, (int) $row->next_val) : $safeMinValue;
        $escapedSeq = $this->db->escape($sequenceName);

        $this->db->query(
            "SELECT pg_catalog.setval({$escapedSeq}, {$nextVal}, true);"
        );
    }

    /**
     * Menyelaraskan sequence otomatis dari kolom SERIAL milik tabel via `pg_get_serial_sequence`.
     */
    protected function syncSerialSequence(string $tableName, string $columnName, int $minValue = 1): void
    {
        $escapedTable = $this->db->escape($tableName);
        $escapedColumn = $this->db->escape($columnName);
        $seqRow = $this->db->query(
            "SELECT pg_get_serial_sequence({$escapedTable}, {$escapedColumn}) AS seq_name;"
        )->getRow();

        if ($seqRow && ! empty($seqRow->seq_name)) {
            $this->syncSequence($seqRow->seq_name, $tableName, $columnName, $minValue);
        }
    }

    /**
     * Mencetak ringkasan hasil seeding ke terminal apabila dijalankan melalui CLI.
     */
    protected function logSeedSummary(string $label, int $totalRows, int $insertedRows): void
    {
        if (is_cli() && ! $this->silent) {
            CLI::write(
                sprintf('  - %-28s : %d baru disisipkan (dari %d baris data)', $label, $insertedRows, $totalRows),
                'light_gray'
            );
        }
    }
}
