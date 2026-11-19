<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migrasi Skema Database AniTrack (db_anitrack)
 *
 * Membuat skema `anitrack`, seluruh 14 tabel katalog & tracking anime,
 * 7 sequence ID, 13 index performa, 2 view analitik (`v_anime_detail`, `v_user_stats`),
 * fungsi PL/pgSQL, dan trigger validasi `trg_user_anime_validate`.
 *
 * Seluruh perintah DDL bersifat idempoten (`IF NOT EXISTS` / `OR REPLACE`)
 * sehingga aman dijalankan baik pada VM Azure baru maupun database lokal yang sudah ada.
 */
class CreateAnitrackSchema extends Migration
{
    /**
     * Grup koneksi target pada app/Config/Database.php (menggunakan grup default -> db_anitrack).
     *
     * @var string
     */
    protected $DBGroup = 'default';

    /**
     * Membuat skema, tabel, index, view, fungsi, dan trigger anitrack.
     */
    public function up(): void
    {
        // 1. Pastikan schema 'anitrack' tersedia dan aktif pada sesi koneksi ini
        $this->db->query('CREATE SCHEMA IF NOT EXISTS anitrack;');
        $this->db->query('SET search_path TO anitrack, public;');

        // 2. Buat seluruh sequence dengan nama asli agar sinkronisasi setval() tetap konsisten
        $sequences = [
            'CREATE SEQUENCE IF NOT EXISTS anitrack.age_rating_rating_id_seq AS smallint START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;',
            'CREATE SEQUENCE IF NOT EXISTS anitrack.anime_anime_id_seq START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;',
            'CREATE SEQUENCE IF NOT EXISTS anitrack.anime_synonym_synonym_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;',
            'CREATE SEQUENCE IF NOT EXISTS anitrack.anime_theme_theme_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;',
            'CREATE SEQUENCE IF NOT EXISTS anitrack.company_company_id_seq AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;',
            'CREATE SEQUENCE IF NOT EXISTS anitrack.genre_genre_id_seq AS smallint START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;',
            'CREATE SEQUENCE IF NOT EXISTS anitrack.media_type_type_id_seq AS smallint START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;',
        ];

        foreach ($sequences as $sql) {
            $this->db->query($sql);
        }

        // 3. Buat tabel referensi utama (parent tables) terlebih dahulu
        $this->createReferenceTables();

        // 4. Buat tabel inti katalog anime dan tabel relasinya
        $this->createCatalogAndRelationTables();

        // 5. Buat tabel pelacakan user (struktur dipertahankan untuk integritas FK & endpoint DELETE)
        $this->createUserTrackingTables();

        // 6. Kaitkan kepemilikan sequence ke masing-masing kolom primary key
        $sequenceOwnerships = [
            'ALTER SEQUENCE anitrack.age_rating_rating_id_seq OWNED BY anitrack.age_rating.rating_id;',
            'ALTER SEQUENCE anitrack.anime_anime_id_seq OWNED BY anitrack.anime.anime_id;',
            'ALTER SEQUENCE anitrack.anime_synonym_synonym_id_seq OWNED BY anitrack.anime_synonym.synonym_id;',
            'ALTER SEQUENCE anitrack.anime_theme_theme_id_seq OWNED BY anitrack.anime_theme.theme_id;',
            'ALTER SEQUENCE anitrack.company_company_id_seq OWNED BY anitrack.company.company_id;',
            'ALTER SEQUENCE anitrack.genre_genre_id_seq OWNED BY anitrack.genre.genre_id;',
            'ALTER SEQUENCE anitrack.media_type_type_id_seq OWNED BY anitrack.media_type.type_id;',
        ];

        foreach ($sequenceOwnerships as $sql) {
            $this->db->query($sql);
        }

        // 7. Buat index pencarian, view gabungan, fungsi, dan trigger validasi
        $this->createIndexes();
        $this->createViewsAndFunctions();
    }

    /**
     * Menghapus seluruh objek database di schema `anitrack` (rollback).
     */
    public function down(): void
    {
        $this->db->query('DROP TRIGGER IF EXISTS trg_user_anime_validate ON anitrack.user_anime_list;');
        $this->db->query('DROP VIEW IF EXISTS anitrack.v_user_stats;');
        $this->db->query('DROP VIEW IF EXISTS anitrack.v_anime_detail;');

        $tablesInReverseOrder = [
            'anitrack.user_anime_tag',
            'anitrack.user_anime_list',
            'anitrack.anime_theme',
            'anitrack.anime_synonym',
            'anitrack.anime_relation',
            'anitrack.anime_company',
            'anitrack.anime_genre',
            'anitrack.anime',
            'anitrack.app_user',
            'anitrack.company',
            'anitrack.genre',
            'anitrack.watch_status',
            'anitrack.age_rating',
            'anitrack.media_type',
        ];

        foreach ($tablesInReverseOrder as $tableName) {
            $this->db->query("DROP TABLE IF EXISTS {$tableName} CASCADE;");
        }

        $this->db->query('DROP FUNCTION IF EXISTS anitrack.fn_validate_user_anime();');
        $this->db->query('DROP FUNCTION IF EXISTS anitrack.fn_user_status_summary(integer);');
    }

    /**
     * Membuat tabel lookup/referensi: media_type, age_rating, watch_status, genre, company, app_user.
     */
    private function createReferenceTables(): void
    {
        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS anitrack.media_type (
                type_id smallint NOT NULL DEFAULT nextval('anitrack.media_type_type_id_seq'::regclass),
                type_name character varying(20) NOT NULL,
                CONSTRAINT media_type_pkey PRIMARY KEY (type_id),
                CONSTRAINT media_type_type_name_key UNIQUE (type_name)
            );
        SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS anitrack.age_rating (
                rating_id smallint NOT NULL DEFAULT nextval('anitrack.age_rating_rating_id_seq'::regclass),
                rating_code character varying(10) NOT NULL,
                description character varying(60) NOT NULL,
                CONSTRAINT age_rating_pkey PRIMARY KEY (rating_id),
                CONSTRAINT age_rating_rating_code_key UNIQUE (rating_code)
            );
        SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS anitrack.watch_status (
                status_id smallint NOT NULL,
                status_name character varying(20) NOT NULL,
                CONSTRAINT watch_status_pkey PRIMARY KEY (status_id),
                CONSTRAINT watch_status_status_name_key UNIQUE (status_name)
            );
        SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS anitrack.genre (
                genre_id smallint NOT NULL DEFAULT nextval('anitrack.genre_genre_id_seq'::regclass),
                genre_name character varying(40) NOT NULL,
                CONSTRAINT genre_pkey PRIMARY KEY (genre_id),
                CONSTRAINT genre_genre_name_key UNIQUE (genre_name)
            );
        SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS anitrack.company (
                company_id integer NOT NULL DEFAULT nextval('anitrack.company_company_id_seq'::regclass),
                company_name character varying(120) NOT NULL,
                CONSTRAINT company_pkey PRIMARY KEY (company_id),
                CONSTRAINT company_company_name_key UNIQUE (company_name)
            );
        SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS anitrack.app_user (
                user_id integer NOT NULL,
                username character varying(50) NOT NULL,
                gender character varying(10),
                location character varying(100),
                birth_date date,
                join_date date DEFAULT CURRENT_DATE NOT NULL,
                last_online timestamp without time zone,
                CONSTRAINT app_user_pkey PRIMARY KEY (user_id),
                CONSTRAINT app_user_username_key UNIQUE (username),
                CONSTRAINT app_user_birth_date_check CHECK ((birth_date >= '1900-01-01'::date)),
                CONSTRAINT app_user_gender_check CHECK (((gender)::text = ANY ((ARRAY['Male'::character varying, 'Female'::character varying, 'Non-Binary'::character varying])::text[]))),
                CONSTRAINT ck_user_online CHECK (((last_online IS NULL) OR (last_online >= (join_date)::timestamp without time zone)))
            );
        SQL);
    }

    /**
     * Membuat tabel utama `anime` beserta relasi genre, studio/company, relasi cerita, sinonim, dan tema lagu.
     */
    private function createCatalogAndRelationTables(): void
    {
        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS anitrack.anime (
                anime_id integer NOT NULL DEFAULT nextval('anitrack.anime_anime_id_seq'::regclass),
                title character varying(255) NOT NULL,
                title_english character varying(255),
                title_japanese character varying(255),
                image_url character varying(255),
                type_id smallint NOT NULL,
                source character varying(30),
                episodes integer DEFAULT 0 NOT NULL,
                status character varying(20) DEFAULT 'Finished Airing'::character varying NOT NULL,
                aired_from date,
                aired_to date,
                duration_min smallint,
                rating_id smallint,
                score numeric(4,2),
                scored_by integer DEFAULT 0 NOT NULL,
                score_rank integer,
                popularity integer,
                members integer DEFAULT 0 NOT NULL,
                favorites integer DEFAULT 0 NOT NULL,
                background text,
                premiered_season character varying(6),
                premiered_year smallint,
                broadcast character varying(50),
                CONSTRAINT anime_pkey PRIMARY KEY (anime_id),
                CONSTRAINT anime_type_id_fkey FOREIGN KEY (type_id) REFERENCES anitrack.media_type(type_id),
                CONSTRAINT anime_rating_id_fkey FOREIGN KEY (rating_id) REFERENCES anitrack.age_rating(rating_id),
                CONSTRAINT anime_duration_min_check CHECK ((duration_min > 0)),
                CONSTRAINT anime_episodes_check CHECK ((episodes >= 0)),
                CONSTRAINT anime_favorites_check CHECK ((favorites >= 0)),
                CONSTRAINT anime_members_check CHECK ((members >= 0)),
                CONSTRAINT anime_popularity_check CHECK ((popularity > 0)),
                CONSTRAINT anime_premiered_season_check CHECK (((premiered_season)::text = ANY ((ARRAY['Winter'::character varying, 'Spring'::character varying, 'Summer'::character varying, 'Fall'::character varying])::text[]))),
                CONSTRAINT anime_premiered_year_check CHECK (((premiered_year >= 1900) AND (premiered_year <= 2100))),
                CONSTRAINT anime_score_check CHECK (((score >= (0)::numeric) AND (score <= (10)::numeric))),
                CONSTRAINT anime_score_rank_check CHECK ((score_rank > 0)),
                CONSTRAINT anime_scored_by_check CHECK ((scored_by >= 0)),
                CONSTRAINT anime_status_check CHECK (((status)::text = ANY ((ARRAY['Finished Airing'::character varying, 'Currently Airing'::character varying, 'Not yet aired'::character varying])::text[]))),
                CONSTRAINT ck_anime_aired CHECK (((aired_from IS NULL) OR (aired_to IS NULL) OR (aired_to >= aired_from)))
            );
        SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS anitrack.anime_genre (
                anime_id integer NOT NULL,
                genre_id smallint NOT NULL,
                CONSTRAINT anime_genre_pkey PRIMARY KEY (anime_id, genre_id),
                CONSTRAINT anime_genre_anime_id_fkey FOREIGN KEY (anime_id) REFERENCES anitrack.anime(anime_id) ON DELETE CASCADE,
                CONSTRAINT anime_genre_genre_id_fkey FOREIGN KEY (genre_id) REFERENCES anitrack.genre(genre_id)
            );
        SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS anitrack.anime_company (
                anime_id integer NOT NULL,
                company_id integer NOT NULL,
                role character varying(10) NOT NULL,
                CONSTRAINT anime_company_pkey PRIMARY KEY (anime_id, company_id, role),
                CONSTRAINT anime_company_anime_id_fkey FOREIGN KEY (anime_id) REFERENCES anitrack.anime(anime_id) ON DELETE CASCADE,
                CONSTRAINT anime_company_company_id_fkey FOREIGN KEY (company_id) REFERENCES anitrack.company(company_id),
                CONSTRAINT anime_company_role_check CHECK (((role)::text = ANY ((ARRAY['producer'::character varying, 'licensor'::character varying, 'studio'::character varying])::text[])))
            );
        SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS anitrack.anime_relation (
                anime_id integer NOT NULL,
                relation_type character varying(30) NOT NULL,
                related_media character varying(5) NOT NULL,
                related_mal_id integer NOT NULL,
                related_title character varying(255),
                CONSTRAINT anime_relation_pkey PRIMARY KEY (anime_id, relation_type, related_media, related_mal_id),
                CONSTRAINT anime_relation_anime_id_fkey FOREIGN KEY (anime_id) REFERENCES anitrack.anime(anime_id) ON DELETE CASCADE,
                CONSTRAINT anime_relation_related_media_check CHECK (((related_media)::text = ANY ((ARRAY['anime'::character varying, 'manga'::character varying])::text[])))
            );
        SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS anitrack.anime_synonym (
                synonym_id integer NOT NULL DEFAULT nextval('anitrack.anime_synonym_synonym_id_seq'::regclass),
                anime_id integer NOT NULL,
                synonym character varying(255) NOT NULL,
                CONSTRAINT anime_synonym_pkey PRIMARY KEY (synonym_id),
                CONSTRAINT uq_anime_synonym UNIQUE (anime_id, synonym),
                CONSTRAINT anime_synonym_anime_id_fkey FOREIGN KEY (anime_id) REFERENCES anitrack.anime(anime_id) ON DELETE CASCADE
            );
        SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS anitrack.anime_theme (
                theme_id integer NOT NULL DEFAULT nextval('anitrack.anime_theme_theme_id_seq'::regclass),
                anime_id integer NOT NULL,
                theme_kind character(2) NOT NULL,
                theme_no smallint DEFAULT 1 NOT NULL,
                theme_title character varying(255) NOT NULL,
                artist character varying(150),
                episodes_used character varying(30),
                CONSTRAINT anime_theme_pkey PRIMARY KEY (theme_id),
                CONSTRAINT uq_anime_theme UNIQUE (anime_id, theme_kind, theme_no),
                CONSTRAINT anime_theme_anime_id_fkey FOREIGN KEY (anime_id) REFERENCES anitrack.anime(anime_id) ON DELETE CASCADE,
                CONSTRAINT anime_theme_theme_kind_check CHECK ((theme_kind = ANY (ARRAY['OP'::bpchar, 'ED'::bpchar]))),
                CONSTRAINT anime_theme_theme_no_check CHECK ((theme_no > 0))
            );
        SQL);
    }

    /**
     * Membuat tabel daftar tontonan user dan tag user.
     */
    private function createUserTrackingTables(): void
    {
        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS anitrack.user_anime_list (
                user_id integer NOT NULL,
                anime_id integer NOT NULL,
                status_id smallint NOT NULL,
                watched_episodes integer DEFAULT 0 NOT NULL,
                score smallint DEFAULT 0 NOT NULL,
                start_date date,
                finish_date date,
                is_rewatching boolean DEFAULT false NOT NULL,
                rewatching_ep integer DEFAULT 0 NOT NULL,
                last_updated timestamp without time zone DEFAULT now() NOT NULL,
                CONSTRAINT user_anime_list_pkey PRIMARY KEY (user_id, anime_id),
                CONSTRAINT user_anime_list_user_id_fkey FOREIGN KEY (user_id) REFERENCES anitrack.app_user(user_id) ON DELETE CASCADE,
                CONSTRAINT user_anime_list_anime_id_fkey FOREIGN KEY (anime_id) REFERENCES anitrack.anime(anime_id) ON DELETE RESTRICT,
                CONSTRAINT user_anime_list_status_id_fkey FOREIGN KEY (status_id) REFERENCES anitrack.watch_status(status_id),
                CONSTRAINT ck_ual_dates CHECK (((start_date IS NULL) OR (finish_date IS NULL) OR (finish_date >= start_date))),
                CONSTRAINT user_anime_list_rewatching_ep_check CHECK ((rewatching_ep >= 0)),
                CONSTRAINT user_anime_list_score_check CHECK (((score >= 0) AND (score <= 10))),
                CONSTRAINT user_anime_list_watched_episodes_check CHECK ((watched_episodes >= 0))
            );
        SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS anitrack.user_anime_tag (
                user_id integer NOT NULL,
                anime_id integer NOT NULL,
                tag character varying(50) NOT NULL,
                CONSTRAINT user_anime_tag_pkey PRIMARY KEY (user_id, anime_id, tag),
                CONSTRAINT user_anime_tag_user_id_anime_id_fkey FOREIGN KEY (user_id, anime_id) REFERENCES anitrack.user_anime_list(user_id, anime_id) ON DELETE CASCADE,
                CONSTRAINT user_anime_tag_tag_check CHECK ((btrim((tag)::text) <> ''::text))
            );
        SQL);
    }

    /**
     * Membuat seluruh B-Tree index untuk mempercepat query pencarian dan filter REST API.
     */
    private function createIndexes(): void
    {
        $indexes = [
            'CREATE INDEX IF NOT EXISTS idx_anime_company_comp ON anitrack.anime_company USING btree (company_id, role);',
            'CREATE INDEX IF NOT EXISTS idx_anime_genre_genre ON anitrack.anime_genre USING btree (genre_id);',
            'CREATE INDEX IF NOT EXISTS idx_anime_premiered ON anitrack.anime USING btree (premiered_year, premiered_season);',
            'CREATE INDEX IF NOT EXISTS idx_anime_score ON anitrack.anime USING btree (score DESC NULLS LAST);',
            'CREATE INDEX IF NOT EXISTS idx_anime_title_lower ON anitrack.anime USING btree (lower((title)::text));',
            'CREATE INDEX IF NOT EXISTS idx_anime_type ON anitrack.anime USING btree (type_id);',
            'CREATE INDEX IF NOT EXISTS idx_synonym_lower ON anitrack.anime_synonym USING btree (lower((synonym)::text));',
            'CREATE INDEX IF NOT EXISTS idx_theme_anime ON anitrack.anime_theme USING btree (anime_id);',
            'CREATE INDEX IF NOT EXISTS idx_ual_anime ON anitrack.user_anime_list USING btree (anime_id);',
            'CREATE INDEX IF NOT EXISTS idx_ual_last_updated ON anitrack.user_anime_list USING btree (last_updated DESC);',
            'CREATE INDEX IF NOT EXISTS idx_ual_user_status ON anitrack.user_anime_list USING btree (user_id, status_id);',
            'CREATE INDEX IF NOT EXISTS idx_ual_watching_only ON anitrack.user_anime_list USING btree (user_id) WHERE (status_id = 1);',
            'CREATE INDEX IF NOT EXISTS idx_user_location ON anitrack.app_user USING btree (location);',
        ];

        foreach ($indexes as $sql) {
            $this->db->query($sql);
        }
    }

    /**
     * Membuat View gabungan dan fungsi + trigger validasi pada schema `anitrack`.
     */
    private function createViewsAndFunctions(): void
    {
        $this->db->query(<<<'SQL'
            CREATE OR REPLACE VIEW anitrack.v_anime_detail AS
            SELECT a.anime_id,
                a.title,
                a.title_english,
                mt.type_name,
                a.episodes,
                a.status,
                a.score,
                a.members,
                ar.rating_code,
                (((a.premiered_season)::text || ' '::text) || a.premiered_year) AS premiered,
                ( SELECT string_agg((g.genre_name)::text, ', '::text ORDER BY (g.genre_name)::text) AS string_agg
                       FROM (anitrack.anime_genre ag
                         JOIN anitrack.genre g ON ((g.genre_id = ag.genre_id)))
                      WHERE (ag.anime_id = a.anime_id)) AS genres,
                ( SELECT string_agg((c.company_name)::text, ', '::text ORDER BY (c.company_name)::text) AS string_agg
                       FROM (anitrack.anime_company ac
                         JOIN anitrack.company c ON ((c.company_id = ac.company_id)))
                      WHERE ((ac.anime_id = a.anime_id) AND ((ac.role)::text = 'studio'::text))) AS studios
               FROM ((anitrack.anime a
                 JOIN anitrack.media_type mt ON ((mt.type_id = a.type_id)))
                 LEFT JOIN anitrack.age_rating ar ON ((ar.rating_id = a.rating_id)));
        SQL);

        $this->db->query(<<<'SQL'
            CREATE OR REPLACE VIEW anitrack.v_user_stats AS
            SELECT u.user_id,
                u.username,
                count(*) FILTER (WHERE (ual.status_id = 1)) AS user_watching,
                count(*) FILTER (WHERE (ual.status_id = 2)) AS user_completed,
                count(*) FILTER (WHERE (ual.status_id = 3)) AS user_onhold,
                count(*) FILTER (WHERE (ual.status_id = 4)) AS user_dropped,
                count(*) FILTER (WHERE (ual.status_id = 6)) AS user_plantowatch,
                round(avg(NULLIF(ual.score, 0)), 2) AS stats_mean_score,
                count(*) FILTER (WHERE ual.is_rewatching) AS stats_rewatched,
                COALESCE(sum(ual.watched_episodes), (0)::bigint) AS stats_episodes,
                round(((COALESCE(sum((ual.watched_episodes * COALESCE((a.duration_min)::integer, 24))), (0)::bigint))::numeric / 1440.0), 2) AS user_days_spent_watching
               FROM ((anitrack.app_user u
                 LEFT JOIN anitrack.user_anime_list ual ON ((ual.user_id = u.user_id)))
                 LEFT JOIN anitrack.anime a ON ((a.anime_id = ual.anime_id)))
              GROUP BY u.user_id, u.username;
        SQL);

        $this->db->query(<<<'SQL'
            CREATE OR REPLACE FUNCTION anitrack.fn_user_status_summary(p_user_id integer)
            RETURNS TABLE(r_status character varying, r_total_anime bigint, r_total_episodes bigint)
            LANGUAGE sql
            STABLE
            AS $function$
                SELECT ws.status_name,
                       COUNT(ual.anime_id),
                       COALESCE(SUM(ual.watched_episodes), 0)
                FROM anitrack.watch_status ws
                LEFT JOIN anitrack.user_anime_list ual
                       ON ual.status_id = ws.status_id AND ual.user_id = p_user_id
                GROUP BY ws.status_id, ws.status_name
                ORDER BY ws.status_id;
            $function$;
        SQL);

        $this->db->query(<<<'SQL'
            CREATE OR REPLACE FUNCTION anitrack.fn_validate_user_anime()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                v_eps INTEGER;
            BEGIN
                SELECT episodes INTO v_eps FROM anitrack.anime WHERE anime_id = NEW.anime_id;

                IF v_eps IS NOT NULL AND v_eps > 0 THEN
                    IF NEW.watched_episodes > v_eps THEN
                        RAISE EXCEPTION 'Episode ditonton (%) melebihi total episode anime % (%)',
                            NEW.watched_episodes, NEW.anime_id, v_eps
                            USING ERRCODE = 'check_violation';
                    END IF;
                    -- BR: status Completed berarti seluruh episode sudah ditonton
                    IF NEW.status_id = 2 THEN
                        NEW.watched_episodes := v_eps;
                    END IF;
                END IF;

                NEW.last_updated := now();
                RETURN NEW;
            END;
            $function$;
        SQL);

        $this->db->query('DROP TRIGGER IF EXISTS trg_user_anime_validate ON anitrack.user_anime_list;');
        $this->db->query(<<<'SQL'
            CREATE TRIGGER trg_user_anime_validate
            BEFORE INSERT OR UPDATE ON anitrack.user_anime_list
            FOR EACH ROW
            EXECUTE FUNCTION anitrack.fn_validate_user_anime();
        SQL);
    }
}
