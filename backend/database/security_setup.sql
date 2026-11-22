-- ============================================================================
-- AniCine v2.0 — Skrip Keamanan Basis Data PostgreSQL (Sub-CPMK 3.2 & PLMKB502)
-- Target Database : db_anitrack (Schema: anitrack) — Milik Brian
-- Catatan         : TIDAK menyentuh db_bioskop milik partner (Satria).
-- Jalankan sebagai superuser postgres:
--   psql -U postgres -d db_anitrack -f backend/database/security_setup.sql
-- ============================================================================

\set ON_ERROR_STOP on

BEGIN;

-- ----------------------------------------------------------------------------
-- 1. Buat Group Roles (NOLOGIN) sesuai prinsip Least Privilege & Separation of Duties
-- ----------------------------------------------------------------------------
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'r_anicine_app') THEN
        CREATE ROLE r_anicine_app NOLOGIN;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'r_anicine_auditor') THEN
        CREATE ROLE r_anicine_auditor NOLOGIN;
    END IF;
END $$;

-- ----------------------------------------------------------------------------
-- 2. Buat User Login (Akun Khusus API, Auditor, dan Backup) — Tanpa SUPERUSER
--    PENTING: Ganti password default di bawah sebelum deploy ke VM Azure produksi!
-- ----------------------------------------------------------------------------
DO $$
BEGIN
    -- Akun runtime REST API CodeIgniter 4 (terkena RLS, dibatasi 30 koneksi)
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'api_anicine') THEN
        CREATE ROLE api_anicine
            LOGIN
            NOSUPERUSER
            NOCREATEDB
            NOCREATEROLE
            NOBYPASSRLS
            INHERIT
            CONNECTION LIMIT 30
            PASSWORD 'AniCine#Api2026';
    ELSE
        ALTER ROLE api_anicine
            NOSUPERUSER
            NOCREATEDB
            NOCREATEROLE
            NOBYPASSRLS
            INHERIT
            CONNECTION LIMIT 30;
    END IF;

    -- Akun auditor khusus baca log & pelaporan (tanpa akses kolom password_hash)
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'auditor_anicine') THEN
        CREATE ROLE auditor_anicine
            LOGIN
            NOSUPERUSER
            NOCREATEDB
            NOCREATEROLE
            NOBYPASSRLS
            INHERIT
            CONNECTION LIMIT 5
            PASSWORD 'AniCine#Audit2026';
    ELSE
        ALTER ROLE auditor_anicine
            NOSUPERUSER
            NOCREATEDB
            NOCREATEROLE
            NOBYPASSRLS
            INHERIT
            CONNECTION LIMIT 5;
    END IF;

    -- Akun read-only khusus pg_dump (BYPASSRLS agar snapshot dump konsisten utuh)
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'r_backup') THEN
        CREATE ROLE r_backup
            LOGIN
            NOSUPERUSER
            NOCREATEDB
            NOCREATEROLE
            BYPASSRLS
            CONNECTION LIMIT 5
            PASSWORD 'Backup#AniCine2026';
    ELSE
        ALTER ROLE r_backup
            NOSUPERUSER
            NOCREATEDB
            NOCREATEROLE
            BYPASSRLS
            CONNECTION LIMIT 5;
    END IF;
END $$;

-- Tetapkan keanggotaan role kelompok
GRANT r_anicine_app TO api_anicine;
GRANT r_anicine_auditor TO auditor_anicine;
GRANT pg_read_all_data TO r_backup;

-- ----------------------------------------------------------------------------
-- 3. Hardening Hak Bawaan PUBLIC (Fail-safe Default)
-- ----------------------------------------------------------------------------
REVOKE ALL ON DATABASE db_anitrack FROM PUBLIC;
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA anitrack FROM PUBLIC;
REVOKE ALL ON ALL TABLES IN SCHEMA anitrack FROM PUBLIC;
REVOKE ALL ON ALL SEQUENCES IN SCHEMA anitrack FROM PUBLIC;
REVOKE EXECUTE ON ALL FUNCTIONS IN SCHEMA anitrack FROM PUBLIC;

-- Pastikan tabel audit_log tidak dapat diubah atau dihapus oleh siapa pun selain owner
REVOKE UPDATE, DELETE, TRUNCATE ON anitrack.audit_log FROM PUBLIC;

-- ----------------------------------------------------------------------------
-- 4. GRANT Berjenjang untuk Role Aplikasi (r_anicine_app -> api_anicine)
--    Rantai hak: CONNECT (DB) -> USAGE (Schema) -> Privilege spesifik per tabel
-- ----------------------------------------------------------------------------
GRANT CONNECT ON DATABASE db_anitrack TO r_anicine_app;
GRANT USAGE ON SCHEMA anitrack TO r_anicine_app;

-- Tabel referensi & katalog anime (CRUD oleh Admin melalui endpoint API)
GRANT SELECT, INSERT, UPDATE, DELETE ON
    anitrack.media_type,
    anitrack.age_rating,
    anitrack.watch_status,
    anitrack.genre,
    anitrack.company,
    anitrack.anime,
    anitrack.anime_genre,
    anitrack.anime_company,
    anitrack.anime_relation,
    anitrack.anime_synonym,
    anitrack.anime_theme,
    anitrack.anime_cinema_map
TO r_anicine_app;

-- Tabel akun pengguna: hanya SELECT, INSERT, UPDATE (tanpa DELETE demi jejak integritas)
GRANT SELECT, INSERT, UPDATE ON anitrack.app_user TO r_anicine_app;

-- Tabel data pribadi pengguna (dilindungi RLS berbasis app.user_id & app.user_role)
GRANT SELECT, INSERT, UPDATE, DELETE ON
    anitrack.user_anime_list,
    anitrack.user_anime_tag,
    anitrack.cinema_bookings,
    anitrack.cinema_booking_seats
TO r_anicine_app;

-- Tabel audit_log: hanya SELECT untuk Admin Audit Viewer (penulisan lewat trigger SECURITY DEFINER)
GRANT SELECT ON anitrack.audit_log TO r_anicine_app;

-- Views analitik & pemetaan tayang
GRANT SELECT ON
    anitrack.v_anime_detail,
    anitrack.v_anime_theatrical,
    anitrack.v_user_stats
TO r_anicine_app;

-- Sequence untuk operasi INSERT pada tabel yang diizinkan
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA anitrack TO r_anicine_app;

-- Function yang boleh dipanggil oleh API (fn_audit & fn_validate dipicu otomatis oleh trigger)
GRANT EXECUTE ON FUNCTION anitrack.fn_hitung_umur(date) TO r_anicine_app;
GRANT EXECUTE ON FUNCTION anitrack.fn_user_status_summary(integer) TO r_anicine_app;

-- ----------------------------------------------------------------------------
-- 5. GRANT untuk Role Auditor (r_anicine_auditor -> auditor_anicine)
--    Menerapkan Column-Level Security pada app_user (menyembunyikan password_hash)
-- ----------------------------------------------------------------------------
GRANT CONNECT ON DATABASE db_anitrack TO r_anicine_auditor;
GRANT USAGE ON SCHEMA anitrack TO r_anicine_auditor;

GRANT SELECT ON
    anitrack.anime,
    anitrack.anime_cinema_map,
    anitrack.cinema_bookings,
    anitrack.user_anime_list,
    anitrack.audit_log,
    anitrack.v_anime_detail,
    anitrack.v_anime_theatrical,
    anitrack.v_user_stats
TO r_anicine_auditor;

-- Hak akses kolom terbatas: SELECT * FROM anitrack.app_user akan DITOLAK oleh PostgreSQL
GRANT SELECT (
    user_id, username, email, role, gender, location,
    birth_date, join_date, is_active, created_at, updated_at
) ON anitrack.app_user TO r_anicine_auditor;

GRANT EXECUTE ON FUNCTION anitrack.fn_hitung_umur(date) TO r_anicine_auditor;

-- ----------------------------------------------------------------------------
-- 6. GRANT untuk Role Backup (r_backup)
-- ----------------------------------------------------------------------------
GRANT CONNECT ON DATABASE db_anitrack TO r_backup;
GRANT USAGE ON SCHEMA anitrack TO r_backup;

-- ----------------------------------------------------------------------------
-- 7. Default Privileges untuk Objek Baru yang Dibuat Owner (postgres) di Masa Depan
-- ----------------------------------------------------------------------------
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA anitrack
    REVOKE ALL ON TABLES FROM PUBLIC;

ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA anitrack
    REVOKE ALL ON FUNCTIONS FROM PUBLIC;

ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA anitrack
    GRANT SELECT ON TABLES TO r_anicine_auditor;

COMMIT;

-- ============================================================================
-- PANDUAN HARDENING VM AZURE (Port 5432 Terbuka) — Terapkan di Server PostgreSQL:
--
-- 1. /etc/postgresql/<versi>/main/postgresql.conf:
--      password_encryption = 'scram-sha-256'
--      ssl = on
--      ssl_min_protocol_version = 'TLSv1.2'
--      log_connections = on
--      log_disconnections = on
--      log_statement = 'ddl'
--      log_line_prefix = '%m [%p] %u@%d %r '
--      log_min_duration_statement = 1000
--
-- 2. /etc/postgresql/<versi>/main/pg_hba.conf (baris pertama yang cocok dipakai):
--      local   all           postgres                      peer
--      host    db_anitrack   api_anicine     127.0.0.1/32  scram-sha-256
--      host    db_anitrack   r_backup        127.0.0.1/32  scram-sha-256
--      hostssl db_anitrack   +r_anicine_app  <IP_CLIENT>   scram-sha-256
--      host    all           all             0.0.0.0/0     reject
--
--    Terapkan tanpa restart: SELECT pg_reload_conf();
-- ============================================================================
