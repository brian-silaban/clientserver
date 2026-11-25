-- ============================================================================
-- AniCine v2.0 — Skrip Pembersihan Data Bertahap (db_anitrack / schema: anitrack)
-- Tujuan   : Membersihkan data katalog anime hasil impor (HTML entities, spasi
--            ganda, domain CDN mati, placeholder 'Unknown', inkonsistensi
--            skor/status/tanggal, artefak parsing anime_theme, fragmen company,
--            duplikat sinonim, dan disambiguasi judul ganda).
-- Catatan  : TIDAK menyentuh db_bioskop milik partner (Satria).
--
-- WAJIB BACKUP SEBELUM MENJALANKAN:
--   cd backend && php spark backup:run
--
-- Jalankan sebagai superuser postgres (atomic transaction, aman dijalankan ulang):
--   psql -U postgres -d db_anitrack -f backend/database/cleanup_anitrack_data.sql
-- ============================================================================

\set ON_ERROR_STOP on

BEGIN;

SET LOCAL search_path = anitrack, public;

-- Nonaktifkan sementara trigger audit pada tabel anime agar pembersihan massal
-- 13.891 baris tidak membanjiri anitrack.audit_log dengan belasan ribu baris ETL.
ALTER TABLE anitrack.anime DISABLE TRIGGER trg_audit_anime;

-- ----------------------------------------------------------------------------
-- FUNGSI BANTU SEMENTARA (otomatis hilang saat sesi psql berakhir)
-- ----------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION pg_temp.fn_clean_text(p_input text)
RETURNS text
LANGUAGE plpgsql
IMMUTABLE
AS $$
DECLARE
    v_out text;
BEGIN
    IF p_input IS NULL THEN
        RETURN NULL;
    END IF;

    v_out := p_input;

    -- Ganti tag <br> menjadi spasi lalu hapus sisa tag HTML
    v_out := regexp_replace(v_out, '<br\s*/?>', ' ', 'gi');
    v_out := regexp_replace(v_out, '<[^>]+>', '', 'g');

    -- Dekode seluruh HTML entities yang terdeteksi pada db_anitrack
    v_out := replace(v_out, '&#039;', '''');
    v_out := replace(v_out, '&apos;', '''');
    v_out := replace(v_out, '&rsquo;', '’');
    v_out := replace(v_out, '&ldquo;', '“');
    v_out := replace(v_out, '&rdquo;', '”');
    v_out := replace(v_out, '&quot;', '"');
    v_out := replace(v_out, '&amp;', '&');
    v_out := replace(v_out, '&lt;', '<');
    v_out := replace(v_out, '&gt;', '>');
    v_out := replace(v_out, '&nbsp;', ' ');
    v_out := replace(v_out, '&mdash;', '—');
    v_out := replace(v_out, '&ndash;', '–');
    v_out := replace(v_out, '&yen;', '¥');
    v_out := replace(v_out, '&eacute;', 'é');
    v_out := replace(v_out, '&egrave;', 'è');
    v_out := replace(v_out, '&aacute;', 'á');
    v_out := replace(v_out, '&oacute;', 'ó');
    v_out := replace(v_out, '&uacute;', 'ú');
    v_out := replace(v_out, '&ucirc;', 'û');
    v_out := replace(v_out, '&ouml;', 'ö');
    v_out := replace(v_out, '&uuml;', 'ü');
    v_out := replace(v_out, '&Auml;', 'Ä');
    v_out := replace(v_out, '&mu;', 'μ');
    v_out := replace(v_out, '&chi;', 'χ');
    v_out := replace(v_out, '&Psi;', 'Ψ');
    v_out := replace(v_out, '&times;', '×');
    v_out := replace(v_out, '&hearts;', '♥');
    v_out := replace(v_out, '&deg;', '°');
    v_out := replace(v_out, '&reg;', '®');

    -- Rapikan spasi ganda/tab/newline dan pangkas whitespace tepi
    v_out := regexp_replace(v_out, '\s+', ' ', 'g');
    v_out := btrim(v_out);

    RETURN NULLIF(v_out, '');
END;
$$;

-- ============================================================================
-- TAHAP 1: Dekode HTML Entities, Hapus Tag HTML, & Normalisasi Spasi
-- ============================================================================
UPDATE anitrack.anime
SET
    title          = COALESCE(pg_temp.fn_clean_text(title), title),
    title_english  = pg_temp.fn_clean_text(title_english),
    title_japanese = pg_temp.fn_clean_text(title_japanese),
    background     = pg_temp.fn_clean_text(background)
WHERE
    title          IS DISTINCT FROM COALESCE(pg_temp.fn_clean_text(title), title)
    OR title_english  IS DISTINCT FROM pg_temp.fn_clean_text(title_english)
    OR title_japanese IS DISTINCT FROM pg_temp.fn_clean_text(title_japanese)
    OR background     IS DISTINCT FROM pg_temp.fn_clean_text(background);

UPDATE anitrack.company
SET company_name = pg_temp.fn_clean_text(company_name)
WHERE company_name IS DISTINCT FROM pg_temp.fn_clean_text(company_name)
  AND pg_temp.fn_clean_text(company_name) IS NOT NULL;

UPDATE anitrack.anime_relation
SET related_title = pg_temp.fn_clean_text(related_title)
WHERE related_title IS DISTINCT FROM pg_temp.fn_clean_text(related_title);

-- Hapus calon duplikat sinonim terlebih dahulu sebelum update teks agar tidak
-- melanggar constraint UNIQUE uq_anime_synonym(anime_id, synonym)
DELETE FROM anitrack.anime_synonym s
USING anitrack.anime_synonym dup
WHERE s.anime_id = dup.anime_id
  AND lower(pg_temp.fn_clean_text(s.synonym)) = lower(pg_temp.fn_clean_text(dup.synonym))
  AND s.synonym_id > dup.synonym_id;

UPDATE anitrack.anime_synonym
SET synonym = pg_temp.fn_clean_text(synonym)
WHERE synonym IS DISTINCT FROM pg_temp.fn_clean_text(synonym)
  AND pg_temp.fn_clean_text(synonym) IS NOT NULL;

UPDATE anitrack.anime_theme
SET
    theme_title = COALESCE(pg_temp.fn_clean_text(theme_title), theme_title),
    artist      = pg_temp.fn_clean_text(artist)
WHERE
    theme_title IS DISTINCT FROM COALESCE(pg_temp.fn_clean_text(theme_title), theme_title)
    OR artist   IS DISTINCT FROM pg_temp.fn_clean_text(artist);

-- ============================================================================
-- TAHAP 2: Migrasi Domain Poster CDN Lama ke CDN Aktif MyAnimeList
--          (myanimelist.cdn-dena.com sudah mati -> cdn.myanimelist.net 200 OK)
-- ============================================================================
UPDATE anitrack.anime
SET image_url = regexp_replace(
    btrim(image_url),
    '^https?://myanimelist\.cdn-dena\.com/',
    'https://cdn.myanimelist.net/',
    'i'
)
WHERE image_url ~* '^https?://myanimelist\.cdn-dena\.com/';

UPDATE anitrack.anime
SET image_url = NULL
WHERE image_url IS NOT NULL AND btrim(image_url) = '';

-- ============================================================================
-- TAHAP 3: Normalisasi Nilai Placeholder ('Unknown', dsb.) Menjadi NULL
-- ============================================================================
UPDATE anitrack.anime
SET source = NULL
WHERE source IS NOT NULL
  AND lower(btrim(source)) IN ('', 'unknown', 'none', 'n/a', 'null', '-');

UPDATE anitrack.anime
SET broadcast = NULL
WHERE broadcast IS NOT NULL
  AND lower(btrim(broadcast)) IN (
      '', 'unknown', 'none', 'n/a', 'null', '-', 'not scheduled once per week'
  );

-- ============================================================================
-- TAHAP 4: Perbaikan Konsistensi Skor, Status Tayang, & Tanggal
-- ============================================================================
-- 4a. Skor 0.00 dianggap belum memiliki penilaian (NULL) agar tampilan UI
--     menampilkan '-' dan pengurutan NULLS LAST berjalan akurat.
UPDATE anitrack.anime
SET score = NULL
WHERE score = 0;

-- 4b. Anime 'Not yet aired' tidak semestinya memiliki voter/skor
UPDATE anitrack.anime
SET
    score     = NULL,
    scored_by = 0
WHERE status = 'Not yet aired'
  AND (score IS NOT NULL OR scored_by > 0);

-- 4c. Anime 'Currently Airing' yang sudah memiliki tanggal selesai (aired_to)
--     di masa lalu diperbaiki menjadi 'Finished Airing'
UPDATE anitrack.anime
SET status = 'Finished Airing'
WHERE status = 'Currently Airing'
  AND aired_to IS NOT NULL
  AND aired_to < CURRENT_DATE;

-- ============================================================================
-- TAHAP 5: Perbaikan Artefak Parsing pada Lagu Tema (anime_theme)
-- ============================================================================
-- 5a. Rekonsiliasi lagu yang judulnya terpotong di kata " by "
--     (contoh: title='stand', artist='me" by STEREOPONY')
UPDATE anitrack.anime_theme
SET
    theme_title = left(btrim(theme_title || ' by ' || split_part(artist, '" by ', 1)), 255),
    artist      = left(NULLIF(btrim(regexp_replace(artist, '^.*?"\s+by\s+', '')), ''), 150)
WHERE artist ~ '"\s+by\s+';

-- 5b. Pisahkan nama artis yang masih menempel di theme_title saat artist NULL
UPDATE anitrack.anime_theme
SET
    theme_title = left(btrim(regexp_replace(theme_title, '"+\s+[Bb][Yy]\s+.*$', '')), 255),
    artist      = left(NULLIF(btrim(regexp_replace(theme_title, '^[^"]+"\s+[Bb][Yy]\s+', '')), ''), 150)
WHERE (artist IS NULL OR btrim(artist) = '')
  AND theme_title ~* '^[^"]+"\s+by\s+.+$';

UPDATE anitrack.anime_theme
SET theme_title = btrim(regexp_replace(theme_title, '"+\s+[Bb][Yy]\s*$', ''))
WHERE (artist IS NULL OR btrim(artist) = '')
  AND theme_title ~* '^[^"]+"\s+by\s*$';

UPDATE anitrack.anime_theme
SET
    theme_title = left(btrim(regexp_replace(theme_title, '"+\s+[^(\s].*$', '')), 255),
    artist      = left(NULLIF(btrim(regexp_replace(theme_title, '^[^"]+"\s+', '')), ''), 150)
WHERE (artist IS NULL OR btrim(artist) = '')
  AND theme_title ~ '^[^"]+"\s+[^(\s].+$';

-- 5c. Bersihkan tanda kutip pembungkus/nyasar & awalan nomor urut (#1 "Judul)
--     tanpa merusak tanda kutip valid di tengah kata (mis. SUPER BELL"Z)
UPDATE anitrack.anime_theme
SET theme_title = btrim(regexp_replace(theme_title, '^#[0-9\-]+[:.]?\s+"', ''))
WHERE theme_title ~ '^#[0-9\-]+[:.]?\s+"'
  AND (length(theme_title) - length(replace(theme_title, '"', ''))) % 2 = 1;

UPDATE anitrack.anime_theme
SET theme_title = btrim(regexp_replace(theme_title, '"+\s*(\(|$)', ' \1', 'g'))
WHERE theme_title ~ '"+\s*(\(|$)'
  AND (length(theme_title) - length(replace(theme_title, '"', ''))) % 2 = 1;

-- Bersihkan sisa tanda kutip nyasar di artist & pindahkan "(eps ...)" bila ada
UPDATE anitrack.anime_theme
SET
    episodes_used = COALESCE(
        NULLIF(btrim(episodes_used), ''),
        left(substring(artist from '\(eps?\s*([^)]+)\)'), 30)
    ),
    artist = NULLIF(
        btrim(regexp_replace(artist, '"\s*\(eps?[^)]*\)\s*$', '', 'i')),
        ''
    )
WHERE artist ~ '"\s*\(eps?[^)]*\)\s*$';

UPDATE anitrack.anime_theme
SET artist = NULLIF(btrim(regexp_replace(artist, '(^"+|"+$)', '', 'g')), '')
WHERE artist ~ '(^"+|"+$)';

UPDATE anitrack.anime_theme
SET artist = NULLIF(btrim(regexp_replace(artist, '\s+"(?=\S)|(?<=\S)"\s+', ' ', 'g')), '')
WHERE artist IS NOT NULL
  AND (length(artist) - length(replace(artist, '"', ''))) % 2 = 1
  AND artist ~ '(\s+"|"\s+)';

-- ============================================================================
-- TAHAP 6: Pembersihan Fragmen Perusahaan Rusak & Deduplikasi
-- ============================================================================
-- Hapus relasi & baris company yang merupakan pecahan akhiran badan usaha
-- akibat pemisahan koma saat impor CSV (contoh: "..., Inc." -> "Inc.")
DELETE FROM anitrack.anime_company ac
USING anitrack.company c
WHERE ac.company_id = c.company_id
  AND c.company_name ~* '^(ltd\.?|inc\.?|co\.?|corp\.?|llc|kk|k\.k\.|gmbh|s\.a\.|pty|l\.)$';

DELETE FROM anitrack.company
WHERE company_name ~* '^(ltd\.?|inc\.?|co\.?|corp\.?|llc|kk|k\.k\.|gmbh|s\.a\.|pty|l\.)$';

-- ============================================================================
-- TAHAP 7: Disambiguasi Duplikat Judul (Kompatibilitas Validasi Admin CI4)
-- ============================================================================
-- AnimeModel memakai aturan is_unique[anime.title]; bedakan OVA 35102 dari TV 36296
UPDATE anitrack.anime
SET title = 'Hinamatsuri (OVA)'
WHERE anime_id = 35102
  AND title = 'Hinamatsuri'
  AND EXISTS (
      SELECT 1 FROM anitrack.anime a2
      WHERE a2.anime_id = 36296 AND a2.title = 'Hinamatsuri'
  );

-- Catatan Opsional (tidak dijalankan otomatis):
-- Terdapat 1.217 baris anime berating 'Rx' (Hentai, rating_id = 6).
-- Jika katalog publik tidak ingin menampilkan konten dewasa, Anda dapat
-- memfilter `WHERE rating_id <> 6` pada layer API atau menghapusnya via admin.

-- Aktifkan kembali trigger audit setelah pembersihan selesai
ALTER TABLE anitrack.anime ENABLE TRIGGER trg_audit_anime;

COMMIT;

-- ============================================================================
-- VERIFIKASI PASCA-PEMBERSIHAN (Read-Only Check)
-- ============================================================================
SELECT
    (SELECT COUNT(*) FROM anitrack.anime WHERE title ~ '&(#\d+|#x[0-9a-fA-F]+|[a-zA-Z]+);'
        OR title_english ~ '&(#\d+|#x[0-9a-fA-F]+|[a-zA-Z]+);'
        OR title_japanese ~ '&(#\d+|#x[0-9a-fA-F]+|[a-zA-Z]+);'
        OR background ~ '&(#\d+|#x[0-9a-fA-F]+|[a-zA-Z]+);'
        OR background ~ '<[^>]+>') AS sisa_html_anime,
    (SELECT COUNT(*) FROM anitrack.anime WHERE image_url LIKE '%cdn-dena.com%') AS sisa_cdn_mati,
    (SELECT COUNT(*) FROM anitrack.anime WHERE source = 'Unknown') AS sisa_source_unknown,
    (SELECT COUNT(*) FROM anitrack.anime WHERE broadcast IN ('Unknown', 'Not scheduled once per week')) AS sisa_broadcast_kotor,
    (SELECT COUNT(*) FROM anitrack.anime WHERE score = 0) AS sisa_score_nol,
    (SELECT COUNT(*) FROM anitrack.anime WHERE status = 'Currently Airing' AND aired_to IS NOT NULL) AS sisa_airing_anomali,
    (SELECT COUNT(*) FROM anitrack.anime WHERE status = 'Not yet aired' AND scored_by > 0) AS sisa_not_aired_voter,
    (SELECT COUNT(*) FROM (SELECT title FROM anitrack.anime GROUP BY title HAVING COUNT(*) > 1) d) AS sisa_dup_judul,
    (SELECT COUNT(*) FROM anitrack.company WHERE company_name ~* '^(ltd\.?|inc\.?|l\.)$') AS sisa_company_fragmen;
