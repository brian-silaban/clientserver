<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migrasi Upgrade Skema Database AniTrack ke AniCine v2.0 (db_anitrack)
 *
 * Hanya beroperasi pada database milik sendiri (`db_anitrack`, schema `anitrack`)
 * dan TIDAK menyentuh database bioskop milik partner (`db_bioskop`).
 *
 * Cakupan objek yang dibuat/diperbarui:
 * 1. Kolom autentikasi & role pada `anitrack.app_user` + sequence ID + partial unique email.
 * 2. Kolom `version` (*optimistic locking*) pada `anitrack.anime`.
 * 3. Tabel pemetaan dinamis `anitrack.anime_cinema_map` & view `anitrack.v_anime_theatrical`.
 * 4. Tabel transaksi pemesanan tiket sisi AniCine `anitrack.cinema_bookings`.
 * 5. Tabel `anitrack.audit_log`, function `anitrack.fn_hitung_umur()`, dan trigger generik `anitrack.fn_audit()`.
 * 6. Kebijakan Row Level Security (RLS) pada `user_anime_list`, `user_anime_tag`, dan `cinema_bookings`.
 */
class UpgradeAnitrackV2 extends Migration
{
    /**
     * Grup koneksi target (default -> db_anitrack).
     *
     * @var string
     */
    protected $DBGroup = 'default';

    /**
     * Menerapkan perubahan skema AniCine v2.0 secara idempoten.
     */
    public function up(): void
    {
        $this->db->query('SET search_path TO anitrack, public;');

        // 1. Perbarui struktur app_user & anime
        $this->upgradeAppUserAndAnime();

        // 2. Buat tabel pemetaan dinamis anime <-> bioskop, transaksi booking AniCine, dan audit_log
        $this->createV2Tables();

        // 3. Buat view theatrical, function fn_hitung_umur(), dan trigger generik fn_audit()
        $this->createViewsFunctionsAndAuditTriggers();

        // 4. Aktifkan Row Level Security (RLS) pada tabel data pribadi pengguna
        $this->enableRowLevelSecurity();
    }

    /**
     * Mengembalikan perubahan skema AniCine v2.0 (rollback).
     */
    public function down(): void
    {
        $this->db->query('SET search_path TO anitrack, public;');

        // 1. Nonaktifkan policy RLS
        $this->db->query('DROP POLICY IF EXISTS p_cb_owner_or_admin ON anitrack.cinema_bookings;');
        $this->db->query('DROP POLICY IF EXISTS p_ual_owner_or_admin ON anitrack.user_anime_list;');
        $this->db->query('DROP POLICY IF EXISTS p_uat_owner_or_admin ON anitrack.user_anime_tag;');
        $this->db->query('ALTER TABLE IF EXISTS anitrack.user_anime_list DISABLE ROW LEVEL SECURITY;');
        $this->db->query('ALTER TABLE IF EXISTS anitrack.user_anime_tag DISABLE ROW LEVEL SECURITY;');

        // 2. Hapus trigger audit pada seluruh tabel terkait
        $this->db->query('DROP TRIGGER IF EXISTS trg_audit_cinema_bookings ON anitrack.cinema_bookings;');
        $this->db->query('DROP TRIGGER IF EXISTS trg_audit_user_anime_list ON anitrack.user_anime_list;');
        $this->db->query('DROP TRIGGER IF EXISTS trg_audit_app_user ON anitrack.app_user;');
        $this->db->query('DROP TRIGGER IF EXISTS trg_audit_anime_cinema_map ON anitrack.anime_cinema_map;');
        $this->db->query('DROP TRIGGER IF EXISTS trg_audit_anime ON anitrack.anime;');

        // 3. Hapus view, function, dan tabel baru v2.0
        $this->db->query('DROP VIEW IF EXISTS anitrack.v_anime_theatrical;');
        $this->db->query('DROP FUNCTION IF EXISTS anitrack.fn_audit();');
        $this->db->query('DROP FUNCTION IF EXISTS anitrack.fn_hitung_umur(date);');
        $this->db->query('DROP TABLE IF EXISTS anitrack.audit_log CASCADE;');
        $this->db->query('DROP TABLE IF EXISTS anitrack.cinema_bookings CASCADE;');
        $this->db->query('DROP TABLE IF EXISTS anitrack.anime_cinema_map CASCADE;');

        // 4. Hapus kolom tambahan pada anime & app_user
        $this->db->query('DROP INDEX IF EXISTS anitrack.uq_app_user_email_lower;');
        $this->db->query('DROP INDEX IF EXISTS anitrack.idx_app_user_username_lower;');
        $this->db->query('ALTER TABLE anitrack.anime DROP CONSTRAINT IF EXISTS anime_version_check;');
        $this->db->query('ALTER TABLE anitrack.anime DROP COLUMN IF EXISTS version;');

        $this->db->query('ALTER TABLE anitrack.app_user ALTER COLUMN user_id DROP DEFAULT;');
        $this->db->query('DROP SEQUENCE IF EXISTS anitrack.app_user_user_id_seq;');
        $this->db->query('ALTER TABLE anitrack.app_user DROP CONSTRAINT IF EXISTS app_user_role_check;');
        $this->db->query('ALTER TABLE anitrack.app_user DROP CONSTRAINT IF EXISTS app_user_version_check;');
        $this->db->query('ALTER TABLE anitrack.app_user DROP COLUMN IF EXISTS updated_at;');
        $this->db->query('ALTER TABLE anitrack.app_user DROP COLUMN IF EXISTS created_at;');
        $this->db->query('ALTER TABLE anitrack.app_user DROP COLUMN IF EXISTS version;');
        $this->db->query('ALTER TABLE anitrack.app_user DROP COLUMN IF EXISTS is_active;');
        $this->db->query('ALTER TABLE anitrack.app_user DROP COLUMN IF EXISTS role;');
        $this->db->query('ALTER TABLE anitrack.app_user DROP COLUMN IF EXISTS password_hash;');
        $this->db->query('ALTER TABLE anitrack.app_user DROP COLUMN IF EXISTS email;');
    }

    /**
     * Menambahkan kolom autentikasi/role/version pada app_user dan kolom version pada anime.
     */
    private function upgradeAppUserAndAnime(): void
    {
        // Kolom email & password_hash bersifat nullable untuk menjaga kompatibilitas 112.018 baris dataset lama
        $this->db->query(<<<'SQL'
            ALTER TABLE anitrack.app_user
                ADD COLUMN IF NOT EXISTS email character varying(120),
                ADD COLUMN IF NOT EXISTS password_hash character varying(255),
                ADD COLUMN IF NOT EXISTS role character varying(20) NOT NULL DEFAULT 'user',
                ADD COLUMN IF NOT EXISTS is_active boolean NOT NULL DEFAULT true,
                ADD COLUMN IF NOT EXISTS version integer NOT NULL DEFAULT 1,
                ADD COLUMN IF NOT EXISTS created_at timestamptz NOT NULL DEFAULT now(),
                ADD COLUMN IF NOT EXISTS updated_at timestamptz NOT NULL DEFAULT now();
        SQL);

        // Tambahkan CHECK constraint secara idempoten
        $this->db->query(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'app_user_role_check'
                ) THEN
                    ALTER TABLE anitrack.app_user
                        ADD CONSTRAINT app_user_role_check CHECK (role IN ('user', 'admin'));
                END IF;

                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'app_user_version_check'
                ) THEN
                    ALTER TABLE anitrack.app_user
                        ADD CONSTRAINT app_user_version_check CHECK (version >= 1);
                END IF;
            END $$;
        SQL);

        // Index unik case-insensitive pada email yang terisi, serta index pencarian username
        $this->db->query(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS uq_app_user_email_lower
                ON anitrack.app_user (lower(email))
                WHERE email IS NOT NULL;
        SQL);

        $this->db->query(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_app_user_username_lower
                ON anitrack.app_user (lower(username));
        SQL);

        // Buat dan sinkronkan sequence user_id agar registrasi user baru otomatis melanjutkan ID tertinggi
        $this->db->query(<<<'SQL'
            CREATE SEQUENCE IF NOT EXISTS anitrack.app_user_user_id_seq
                AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
        SQL);

        $this->db->query(<<<'SQL'
            ALTER TABLE anitrack.app_user
                ALTER COLUMN user_id SET DEFAULT nextval('anitrack.app_user_user_id_seq'::regclass);
        SQL);

        $this->db->query('ALTER SEQUENCE anitrack.app_user_user_id_seq OWNED BY anitrack.app_user.user_id;');

        $this->db->query(<<<'SQL'
            SELECT setval(
                'anitrack.app_user_user_id_seq',
                GREATEST(COALESCE((SELECT MAX(user_id) FROM anitrack.app_user), 0), 1),
                true
            );
        SQL);

        // Kolom version untuk optimistic locking pada tabel katalog anime
        $this->db->query(<<<'SQL'
            ALTER TABLE anitrack.anime
                ADD COLUMN IF NOT EXISTS version integer NOT NULL DEFAULT 1;
        SQL);

        $this->db->query(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'anime_version_check'
                ) THEN
                    ALTER TABLE anitrack.anime
                        ADD CONSTRAINT anime_version_check CHECK (version >= 1);
                END IF;
            END $$;
        SQL);
    }

    /**
     * Membuat tabel anime_cinema_map, cinema_bookings, dan audit_log beserta index kueri utama.
     */
    private function createV2Tables(): void
    {
        // 1. Pemetaan dinamis anime (db_anitrack) <-> film bioskop partner (menggantikan array hardcode)
        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS anitrack.anime_cinema_map (
                map_id serial PRIMARY KEY,
                anime_id integer NOT NULL,
                cinema_movie_id bigint NOT NULL,
                screening_status character varying(20) NOT NULL DEFAULT 'NOW_SHOWING',
                is_featured boolean NOT NULL DEFAULT false,
                release_note text,
                version integer NOT NULL DEFAULT 1,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT uq_acm_anime_id UNIQUE (anime_id),
                CONSTRAINT uq_acm_cinema_movie_id UNIQUE (cinema_movie_id),
                CONSTRAINT acm_anime_id_fkey FOREIGN KEY (anime_id)
                    REFERENCES anitrack.anime(anime_id) ON DELETE CASCADE,
                CONSTRAINT acm_screening_status_check CHECK (
                    screening_status IN ('NOW_SHOWING', 'UPCOMING', 'ENDED')
                ),
                CONSTRAINT acm_version_check CHECK (version >= 1)
            );
        SQL);

        $this->db->query(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_acm_status_featured
                ON anitrack.anime_cinema_map (screening_status, is_featured DESC);
        SQL);

        // 2. Rekaman pemesanan tiket bioskop anime milik AniCine (mandiri dari DB partner)
        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS anitrack.cinema_bookings (
                booking_id serial PRIMARY KEY,
                booking_code character varying(24) NOT NULL,
                user_id integer NOT NULL,
                anime_id integer NOT NULL,
                cinema_movie_id bigint NOT NULL,
                showtime_id integer NOT NULL,
                partner_booking_id character varying(64),
                cinema_name character varying(120) NOT NULL,
                kecamatan character varying(60),
                studio_name character varying(80) NOT NULL,
                studio_type character varying(40),
                show_date date NOT NULL,
                start_time time without time zone NOT NULL,
                seat_ids_json jsonb NOT NULL,
                seat_labels text NOT NULL,
                seat_count smallint NOT NULL,
                ticket_price numeric(10,2) NOT NULL,
                total_price numeric(12,2) NOT NULL,
                payment_status character varying(20) NOT NULL DEFAULT 'PENDING',
                version integer NOT NULL DEFAULT 1,
                expires_at timestamptz,
                paid_at timestamptz,
                cancelled_at timestamptz,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT uq_cb_booking_code UNIQUE (booking_code),
                CONSTRAINT cb_user_id_fkey FOREIGN KEY (user_id)
                    REFERENCES anitrack.app_user(user_id) ON DELETE RESTRICT,
                CONSTRAINT cb_anime_id_fkey FOREIGN KEY (anime_id)
                    REFERENCES anitrack.anime(anime_id) ON DELETE RESTRICT,
                CONSTRAINT cb_seat_count_check CHECK (seat_count > 0),
                CONSTRAINT cb_ticket_price_check CHECK (ticket_price >= 0),
                CONSTRAINT cb_total_price_check CHECK (total_price >= 0),
                CONSTRAINT cb_payment_status_check CHECK (
                    payment_status IN ('PENDING', 'PAID', 'CANCELLED', 'EXPIRED')
                ),
                CONSTRAINT cb_version_check CHECK (version >= 1)
            );
        SQL);

        // Index pada kolom FK dan filter RLS untuk performa kueri optimal
        $this->db->query(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_cb_user_created
                ON anitrack.cinema_bookings (user_id, created_at DESC);
        SQL);

        $this->db->query(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_cb_anime_id
                ON anitrack.cinema_bookings (anime_id);
        SQL);

        $this->db->query(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_cb_showtime_status
                ON anitrack.cinema_bookings (showtime_id, payment_status);
        SQL);

        $this->db->query(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_cb_status_created
                ON anitrack.cinema_bookings (payment_status, created_at DESC);
        SQL);

        // 3. Tabel Audit Log sesuai standar praktikum PLMKB502
        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS anitrack.audit_log (
                id bigserial PRIMARY KEY,
                table_name text NOT NULL,
                operation text NOT NULL,
                row_pk text,
                old_data jsonb,
                new_data jsonb,
                changed_by text NOT NULL DEFAULT current_user,
                app_user text DEFAULT current_setting('app.user_id', true),
                client_ip inet DEFAULT inet_client_addr(),
                changed_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT audit_log_operation_check CHECK (operation IN ('INSERT', 'UPDATE', 'DELETE'))
            );
        SQL);

        $this->db->query(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_audit_tabel_pk_waktu
                ON anitrack.audit_log (table_name, row_pk, changed_at DESC);
        SQL);

        $this->db->query(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_audit_changed_at
                ON anitrack.audit_log (changed_at DESC);
        SQL);

        // Lindungi integritas tabel audit_log dari modifikasi atau penghapusan
        $this->db->query('REVOKE UPDATE, DELETE, TRUNCATE ON anitrack.audit_log FROM PUBLIC;');
    }

    /**
     * Membuat view v_anime_theatrical, function fn_hitung_umur(), dan trigger generik fn_audit().
     */
    private function createViewsFunctionsAndAuditTriggers(): void
    {
        // View khusus anime yang sedang tayang (NOW_SHOWING) atau akan tayang (UPCOMING) di bioskop Medan
        $this->db->query(<<<'SQL'
            CREATE OR REPLACE VIEW anitrack.v_anime_theatrical AS
            SELECT
                m.map_id,
                m.anime_id,
                m.cinema_movie_id,
                m.screening_status,
                m.is_featured,
                m.release_note,
                m.version AS map_version,
                a.title,
                a.title_english,
                a.title_japanese,
                a.image_url,
                mt.type_name,
                a.episodes,
                a.status AS airing_status,
                a.duration_min,
                ar.rating_code,
                ar.description AS rating_description,
                a.score,
                a.score_rank,
                a.popularity,
                a.background,
                a.premiered_season,
                a.premiered_year,
                (
                    SELECT string_agg(g.genre_name::text, ', ' ORDER BY g.genre_name::text)
                    FROM anitrack.anime_genre ag
                    JOIN anitrack.genre g ON g.genre_id = ag.genre_id
                    WHERE ag.anime_id = a.anime_id
                ) AS genres,
                (
                    SELECT string_agg(c.company_name::text, ', ' ORDER BY c.company_name::text)
                    FROM anitrack.anime_company ac
                    JOIN anitrack.company c ON c.company_id = ac.company_id
                    WHERE ac.anime_id = a.anime_id AND ac.role = 'studio'
                ) AS studios
            FROM anitrack.anime_cinema_map m
            JOIN anitrack.anime a ON a.anime_id = m.anime_id
            JOIN anitrack.media_type mt ON mt.type_id = a.type_id
            LEFT JOIN anitrack.age_rating ar ON ar.rating_id = a.rating_id
            WHERE m.screening_status IN ('NOW_SHOWING', 'UPCOMING');
        SQL);

        // Function PL/pgSQL STABLE untuk menghitung umur akurat (mengikuti modul praktikum fn_umur)
        $this->db->query(<<<'SQL'
            CREATE OR REPLACE FUNCTION anitrack.fn_hitung_umur(p_birth_date date)
            RETURNS integer
            LANGUAGE plpgsql
            STABLE
            AS $function$
            BEGIN
                IF p_birth_date IS NULL THEN
                    RETURN NULL;
                END IF;

                IF p_birth_date > current_date THEN
                    RAISE EXCEPTION 'Tanggal lahir % melebihi tanggal hari ini', p_birth_date;
                END IF;

                RETURN extract(year FROM age(current_date, p_birth_date))::integer;
            END;
            $function$;
        SQL);

        // Trigger function generik fn_audit() dengan SECURITY DEFINER & perlindungan kolom password_hash
        $this->db->query(<<<'SQL'
            CREATE OR REPLACE FUNCTION anitrack.fn_audit()
            RETURNS trigger
            LANGUAGE plpgsql
            SECURITY DEFINER
            SET search_path = anitrack, public
            AS $function$
            DECLARE
                v_pk_spec text := COALESCE(TG_ARGV[0], 'id');
                v_pk_cols text[];
                v_col     text;
                v_row_pk  text := '';
                v_old     jsonb;
                v_new     jsonb;
                v_src     jsonb;
            BEGIN
                -- Hapus kolom sensitif password_hash agar tidak pernah tersimpan di log audit
                IF TG_OP = 'INSERT' THEN
                    v_new := to_jsonb(NEW) - 'password_hash';
                    v_src := v_new;
                ELSIF TG_OP = 'UPDATE' THEN
                    v_old := to_jsonb(OLD) - 'password_hash';
                    v_new := to_jsonb(NEW) - 'password_hash';
                    -- Abaikan UPDATE yang tidak menghasilkan perubahan nyata
                    IF v_new = v_old THEN
                        RETURN NEW;
                    END IF;
                    v_src := v_new;
                ELSE
                    v_old := to_jsonb(OLD) - 'password_hash';
                    v_src := v_old;
                END IF;

                -- Rangkai nilai primary key (mendukung PK tunggal maupun komposit seperti 'user_id,anime_id')
                v_pk_cols := string_to_array(v_pk_spec, ',');
                FOREACH v_col IN ARRAY v_pk_cols LOOP
                    IF v_row_pk <> '' THEN
                        v_row_pk := v_row_pk || ':';
                    END IF;
                    v_row_pk := v_row_pk || COALESCE(v_src ->> btrim(v_col), '');
                END LOOP;

                IF TG_OP = 'INSERT' THEN
                    INSERT INTO anitrack.audit_log (table_name, operation, row_pk, new_data)
                    VALUES (TG_TABLE_NAME, TG_OP, v_row_pk, v_new);
                    RETURN NEW;
                ELSIF TG_OP = 'UPDATE' THEN
                    INSERT INTO anitrack.audit_log (table_name, operation, row_pk, old_data, new_data)
                    VALUES (TG_TABLE_NAME, TG_OP, v_row_pk, v_old, v_new);
                    RETURN NEW;
                ELSE
                    INSERT INTO anitrack.audit_log (table_name, operation, row_pk, old_data)
                    VALUES (TG_TABLE_NAME, TG_OP, v_row_pk, v_old);
                    RETURN OLD;
                END IF;
            END;
            $function$;
        SQL);

        // Pasang trigger AFTER INSERT/UPDATE/DELETE pada 5 tabel inti AniCine
        $auditTargets = [
            ['trg_audit_anime', 'anitrack.anime', 'anime_id'],
            ['trg_audit_anime_cinema_map', 'anitrack.anime_cinema_map', 'map_id'],
            ['trg_audit_app_user', 'anitrack.app_user', 'user_id'],
            ['trg_audit_user_anime_list', 'anitrack.user_anime_list', 'user_id,anime_id'],
            ['trg_audit_cinema_bookings', 'anitrack.cinema_bookings', 'booking_id'],
        ];

        foreach ($auditTargets as [$triggerName, $tableName, $pkArg]) {
            $this->db->query("DROP TRIGGER IF EXISTS {$triggerName} ON {$tableName};");
            $this->db->query(
                "CREATE TRIGGER {$triggerName} AFTER INSERT OR UPDATE OR DELETE ON {$tableName} "
                . "FOR EACH ROW EXECUTE FUNCTION anitrack.fn_audit('{$pkArg}');"
            );
        }
    }

    /**
     * Mengaktifkan Row Level Security (RLS) berbasis konteks sesi (app.user_id & app.user_role).
     * Memakai pola (SELECT current_setting(...)) agar dievaluasi sekali per kueri (initPlan).
     */
    private function enableRowLevelSecurity(): void
    {
        $rlsTables = [
            ['anitrack.user_anime_list', 'p_ual_owner_or_admin'],
            ['anitrack.user_anime_tag', 'p_uat_owner_or_admin'],
            ['anitrack.cinema_bookings', 'p_cb_owner_or_admin'],
        ];

        foreach ($rlsTables as [$tableName, $policyName]) {
            $this->db->query("ALTER TABLE {$tableName} ENABLE ROW LEVEL SECURITY;");
            $this->db->query("ALTER TABLE {$tableName} FORCE ROW LEVEL SECURITY;");
            $this->db->query("DROP POLICY IF EXISTS {$policyName} ON {$tableName};");
            $this->db->query(<<<SQL
                CREATE POLICY {$policyName} ON {$tableName}
                FOR ALL
                TO PUBLIC
                USING (
                    (SELECT current_setting('app.user_role', true)) = 'admin'
                    OR user_id = (SELECT NULLIF(current_setting('app.user_id', true), '')::integer)
                )
                WITH CHECK (
                    (SELECT current_setting('app.user_role', true)) = 'admin'
                    OR user_id = (SELECT NULLIF(current_setting('app.user_id', true), '')::integer)
                );
            SQL);
        }
    }
}
