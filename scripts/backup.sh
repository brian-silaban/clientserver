#!/usr/bin/env bash
# ============================================================================
# scripts/backup.sh — Backup Otomatis Harian db_anitrack (AniCine v2.0)
# Mengikuti standar modul Praktikum Backup & Restore PostgreSQL (PLMKB502):
# - pg_dump -Fc (format custom terkompresi) menggunakan akun read-only r_backup
# - Menyimpan checksum SHA-256 berdampingan
# - Verifikasi cepat struktur arsip dengan pg_restore -l
# - Retensi otomatis 7 hari
#
# Contoh crontab (harian pukul 01:30):
#   30 1 * * * /path/to/clientserver/scripts/backup.sh >> /var/log/anicine_backup.log 2>&1
# ============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

BACKUP_DIR="${ANICINE_BACKUP_DIR:-${PROJECT_ROOT}/backend/writable/backups}"
PGHOST="${ANICINE_PGHOST:-localhost}"
PGPORT="${ANICINE_PGPORT:-5432}"
PGUSER="${ANICINE_PGUSER:-r_backup}"
PGDB="${ANICINE_PGDB:-db_anitrack}"
RETENTION_DAYS="${ANICINE_RETENTION_DAYS:-7}"

if [[ -z "${PGPASSFILE:-}" && -f "${PROJECT_ROOT}/backend/writable/.pgpass" ]]; then
    export PGPASSFILE="${PROJECT_ROOT}/backend/writable/.pgpass"
fi

mkdir -p "${BACKUP_DIR}"
chmod 750 "${BACKUP_DIR}" || true

TS="$(date +%Y%m%d_%H%M%S)"
FILE="${BACKUP_DIR}/anitrack_${TS}.dump"

# 1. Dump database format custom (-Fc)
pg_dump -h "${PGHOST}" -p "${PGPORT}" -U "${PGUSER}" -Fc -f "${FILE}" "${PGDB}"

# 2. Buat checksum SHA-256 (format kompatibel dengan sha256sum -c di dalam direktori backup)
(cd "${BACKUP_DIR}" && sha256sum "$(basename "${FILE}")" > "$(basename "${FILE}").sha256")

# 3. Verifikasi cepat struktur arsip (Tingkat 3)
pg_restore -l "${FILE}" > /dev/null

# 4. Retensi otomatis: hapus file backup lebih dari RETENTION_DAYS hari
find "${BACKUP_DIR}" -maxdepth 1 -type f -name 'anitrack_*' -mtime "+${RETENTION_DAYS}" -delete

echo "$(date '+%F %T') OK ${FILE} ($(du -h "${FILE}" | cut -f1))"
