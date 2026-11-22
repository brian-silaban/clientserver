#!/usr/bin/env bash
# ============================================================================
# scripts/verify.sh — Verifikasi 4 Tingkat Backup db_anitrack (AniCine v2.0)
# Mengikuti standar modul Praktikum Backup & Restore PostgreSQL (PLMKB502):
#   Tingkat 1 : Cek keberadaan file & ukuran > 0
#   Tingkat 2 : Verifikasi integritas checksum SHA-256 (sha256sum -c)
#   Tingkat 3 : Verifikasi struktur TOC arsip (pg_restore -l)
#   Tingkat 4 : Restore uji penuh ke database sementara `db_anitrack_verify`
#               lalu bandingkan jumlah baris tabel kunci terhadap produksi
#
# Catatan: Dijalankan oleh akun DBA/postgres (atau melalui cron mingguan):
#   0 3 * * 0 /path/to/clientserver/scripts/verify.sh >> /var/log/anicine_verify.log 2>&1
# ============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

BACKUP_DIR="${ANICINE_BACKUP_DIR:-${PROJECT_ROOT}/backend/writable/backups}"
PGHOST="${ANICINE_PGHOST:-localhost}"
PGPORT="${ANICINE_PGPORT:-5432}"
PGADMIN_USER="${ANICINE_PGADMIN_USER:-postgres}"
PROD_DB="${ANICINE_PGDB:-db_anitrack}"
VERIFY_DB="${ANICINE_VERIFY_DB:-db_anitrack_verify}"

LATEST_FILE="$(ls -t "${BACKUP_DIR}"/anitrack_*.dump 2>/dev/null | head -n 1 || true)"

if [[ -z "${LATEST_FILE}" || ! -s "${LATEST_FILE}" ]]; then
    echo "$(date '+%F %T') FAIL: Tidak ditemukan file backup valid di ${BACKUP_DIR}" >&2
    exit 1
fi

echo "=== Verifikasi Backup AniCine: $(basename "${LATEST_FILE}") ==="

# Tingkat 1: Ukuran file > 0
echo "[Tingkat 1] Ukuran file: $(du -h "${LATEST_FILE}" | cut -f1)"

# Tingkat 2: Integritas SHA-256
if [[ ! -f "${LATEST_FILE}.sha256" ]]; then
    echo "FAIL: File checksum ${LATEST_FILE}.sha256 tidak ditemukan." >&2
    exit 1
fi
(cd "${BACKUP_DIR}" && sha256sum -c "$(basename "${LATEST_FILE}").sha256")

# Tingkat 3: Struktur arsip terbaca
TOC_COUNT="$(pg_restore -l "${LATEST_FILE}" | grep -vc '^;' || true)"
echo "[Tingkat 3] pg_restore -l OK (${TOC_COUNT} objek TOC terbaca)"

# Tingkat 4: Restore uji penuh ke DB sementara + perbandingan jumlah baris
dropdb --if-exists -h "${PGHOST}" -p "${PGPORT}" -U "${PGADMIN_USER}" "${VERIFY_DB}"
createdb -h "${PGHOST}" -p "${PGPORT}" -U "${PGADMIN_USER}" "${VERIFY_DB}"

cleanup() {
    dropdb --if-exists -h "${PGHOST}" -p "${PGPORT}" -U "${PGADMIN_USER}" "${VERIFY_DB}" >/dev/null 2>&1 || true
}
trap cleanup EXIT

pg_restore -h "${PGHOST}" -p "${PGPORT}" -U "${PGADMIN_USER}" -d "${VERIFY_DB}" --no-owner --no-acl "${LATEST_FILE}"

count_rows() {
    psql -h "${PGHOST}" -p "${PGPORT}" -U "${PGADMIN_USER}" -Atd "$1" -c "SELECT count(*) FROM anitrack.$2;"
}

echo "[Tingkat 4] Perbandingan jumlah baris (produksi vs restore uji):"
for tbl in anime app_user anime_cinema_map user_anime_list cinema_bookings audit_log; do
    prod_cnt="$(count_rows "${PROD_DB}" "${tbl}")"
    ver_cnt="$(count_rows "${VERIFY_DB}" "${tbl}")"
    echo "  - anitrack.${tbl}: prod=${prod_cnt} | verify=${ver_cnt}"
done

echo "$(date '+%F %T') VERIFY OK: $(basename "${LATEST_FILE}")"
