# AGENTS.md — AniCine: Anime & Cinema Schedule Integration (Tugas Client-Server)

Dokumen ini adalah panduan utama (*Single Source of Truth*) untuk seluruh AI Coding Agent (**Antigravity**, **Claude Code**, dan **OpenAI CODEX**) yang bekerja di repositori ini.

---

## 0. Dokumen Acuan Wajib (Baca Sebelum Menulis Kode)
1. **[`arsitektur_dan_rancangan_projek.md`](arsitektur_dan_rancangan_projek.md)** — Dokumentasi arsitektur **AniCine** (terpisah `backend/` & `frontend/`), diagram alur *Client-Server & Data Mashup*, integrasi `db_anitrack` (Brian) + `db_bioskop` (Sat), daftar endpoint lengkap, dan contoh payload JSON.
2. **[`.agents/rules/clean-code-indonesia.md`](.agents/rules/clean-code-indonesia.md)** — Ruleset wajib **Clean Code Ramah Mahasiswa & Konvensi Hybrid** (kolom DB, JSON key, variabel, dan method mengikuti skema Bahasa Inggris yang sudah ada; komentar, docblock, dan pesan API wajib Bahasa Indonesia).
3. **[`.agents/rules/anti-ai-slop.md`](.agents/rules/anti-ai-slop.md)** — Ruleset wajib **Anti-AI-Slop UI/UX Design (AniCine Edition)** untuk pembuatan dan modifikasi tampilan antarmuka web di `frontend/app/Views/**`.

---

## 1. Konteks & Arsitektur Projek (Terpisah Backend & Frontend)
- **Nama Projek**: **AniCine** (*Anime & Cinema Schedule Integration*)
- **Pendekatan**: Arsitektur **Client-Server Terpisah (`backend/` & `frontend/`)** + *Server-to-Server Data Mashup* (*Read-Only Cinema Schedule Aggregation* — fokus pada pertukaran informasi katalog anime & jadwal tayang bioskop Medan).
- **Tech Stack**:
  - **Backend (`backend/`)**: PHP 8.2+ dengan **CodeIgniter 4 (CI4)** (`ResourceController`, format respons JSON murni + CORS aktif) pada Port `8080`.
  - **Frontend (`frontend/`)**: **CodeIgniter 4 (CI4)** khusus **Client Web UI** pada Port `8081`. **Frontend tidak terhubung langsung ke PostgreSQL**; seluruh data diambil melalui HTTP `fetch` ke REST API `backend/` (`ANICINE_BACKEND_API_URL = 'http://localhost:8080'`).
  - **Database (PostgreSQL Lokal - Port `5432`)**:
    1. **`db_anitrack`** (Layanan Utama / Brian — grup koneksi `default`, schema `anitrack`):
       - Menyimpan ribuan katalog anime beserta relasinya (`anime`, `media_type`, `age_rating`, `genre`, `anime_genre`, `company`, `anime_company`, `user_anime_list`).
    2. **`db_bioskop`** (Layanan Partner / Sat — grup koneksi `bioskop`, schema `public`):
       - Menyimpan data film, studio bioskop di Medan (*Delipark XXI, Sun Plaza Cinepolis, Centre Point XXI, Manhattan XXI, Ringroad*), dan jadwal tayang (`movies`, `studios`, `showtimes`, `booking_seats`, serta view `v_movies_lengkap_3nf`).
- **Struktur Direktori & File Utama**:
  - **`backend/` (Server REST API & Database PostgreSQL — Port `8080`)**:
    - `backend/app/Controllers/Home.php` — Mengembalikan status JSON Server REST API AniCine.
    - `backend/app/Controllers/Api/Anime.php` — Endpoint CRUD katalog anime & endpoint integrasi *Data Mashup* (`/api/anime`, `/api/anime/tahun/{tahun}`, `/api/anime/theatrical`, `/api/anime/{id}`).
    - `backend/app/Controllers/Api/Cinema.php` — Endpoint informasi bioskop partner (`/api/cinema/status`, `/api/cinema/movies`, `/api/cinema/movies/{id}`, `/api/cinema/showtimes`, `/api/cinema/studios`).
    - `backend/app/Models/AnimeModel.php` — Query builder tabel `anitrack.anime`, validasi input, konstanta `THEATRICAL_MAPPING` (5 Anime Movie), `getTheatricalList()`, dan `getRichDetail()`.
    - `backend/app/Libraries/CinemaService.php` — Client integrasi *Hybrid* ke layanan bioskop Sat (`EXTERNAL_API` via HTTP `CURLRequest` atau `LOCAL_FALLBACK` ke `db_bioskop` lokal).
  - **`frontend/` (Client Web UI AniCine — Port `8081`)**:
    - `frontend/app/Controllers/Home.php` — Me-render halaman antarmuka `anicine_landing.php`.
    - `frontend/app/Views/anicine_landing.php` — Antarmuka katalog anime & jadwal bioskop Medan yang mengonsumsi REST API dari `backend/` (`http://localhost:8080/api/...`).
  - **File Pendukung di Root**:
    - `tambah_anime_ke_bioskop.sql` — Script SQL untuk menyinkronkan 5 Anime Movie beserta jadwal tayangnya ke `db_bioskop`.
    - `test_api.php` — Script CLI untuk menguji fungsi `AnimeModel` dan `CinemaService` di `backend/`.
    - `postman/API_AniCine_Integration.postman_collection.json` — Koleksi pengujian endpoint Postman.

---

## 2. 🇮🇩 Wajib Patuhi: Clean Code & Konvensi Hybrid Bahasa Indonesia
Seluruh kode PHP yang ditulis wajib mematuhi **[`.agents/rules/clean-code-indonesia.md`](.agents/rules/clean-code-indonesia.md)**:
1. **Kolom Database & JSON Key (`snake_case` Bahasa Inggris sesuai Skema)**:
   - Ikuti skema asli `db_anitrack` dan `db_bioskop` (contoh: `anime_id`, `title`, `duration_min`, `rating_code`, `cinema_movie_id`, `is_theatrical`, `cinema_showtimes`, `showtime_id`, `ticket_price`, `seats_available`).
2. **Variabel & Method (`camelCase` Bahasa Inggris Deskriptif)**:
   - Selaras dengan codebase saat ini, gunakan nama berbahasa Inggris yang jelas (contoh: `$movieId`, `$animeOnly`, `$theatricalIds`, `getTheatricalList()`, `getRichDetail()`, `getShowtimes()`, `getMovies()`).
3. **Komentar, Docblock, Validasi & Pesan API (WAJIB Bahasa Indonesia)**:
   - Seluruh komentar penjelas (`// ...`), PHPDoc, `$validationMessages`, dan field `message` pada JSON response wajib ditulis dalam Bahasa Indonesia yang mudah dipahami mahasiswa.
4. **Keterbacaan di Atas Baris Pendek (*Readability over Brevity*)**:
   - Dilarang memadatkan logika integrasi atau pemanggilan API ke dalam *nested ternary* 1 baris. Gunakan struktur `if/else` dan `try/catch` yang rapi.

---

## 3. 🎨 Ruleset Desain "Anti-AI-Slop" (Frontend `frontend/app/Views/**`)
Seluruh pengembangan tampilan web wajib mematuhi aturan di **[`.agents/rules/anti-ai-slop.md`](.agents/rules/anti-ai-slop.md)**:
- Gunakan persona **Editorial Cinema & Curated Anime Archive** (*Warm Stone* `#FAF8F5`, *Crimson Curtain* `#BE123C`, *Projector Amber* `#D97706`).
- Hindari 5 ciri khas AI Slop (font `Inter` di semua elemen, gradien ungu-biru neon, kartu bersarang berlapis-lapis, kontras rendah di atas poster, dan grid 3 ikon generik).
- Tampilkan metadata nyata dari API AniCine (`title`, `title_japanese`, `score`, `studios`, serta rincian `cinema_showtimes` per studio bioskop di Medan dengan `tabular-nums`).

---

## 4. ⚙️ Standar Coding, Pemisahan Client-Server, PostgreSQL & Keamanan CI4
1. **Disiplin Pemisahan Backend vs Frontend**:
   - **`backend/`** hanya mengembalikan respons JSON (`ResourceController` / `setJSON`), tidak me-render halaman HTML.
   - **`frontend/`** tidak mengakses PostgreSQL secara langsung; seluruh data diambil melalui REST API `backend/`.
2. **Kompatibilitas PostgreSQL & Multi-Schema (di `backend/`)**:
   - Koneksi `default` mengarah ke `db_anitrack` dengan `schema = 'anitrack'`, sedangkan koneksi `bioskop` mengarah ke `db_bioskop` dengan `schema = 'public'`.
   - Pastikan callback `emptyStringToNull` tetap berjalan di `AnimeModel`.
3. **Ketahanan Integrasi (*Graceful Fallback*)**:
   - Selalu bungkus pemanggilan `CinemaService` di dalam blok `try/catch` agar kegagalan layanan partner tidak menyebabkan error `500`.

---

## 5. Perintah Terminal (CLI)
```bash
# 0. Membuat Skema & Mengisi Data Awal (db_anitrack + db_bioskop)
cd backend
php spark migrate
php spark db:seed AniCineSeeder

# 1. Menjalankan Server Backend REST API (Port 8080)
cd backend
php spark serve --port 8081

# 2. Menjalankan Aplikasi Client Frontend Web (Port 8081)
cd frontend
php spark serve --port 8082

# 3. Menjalankan pengujian cepat integrasi AnimeModel + CinemaService via CLI (dari root)
php test_api.php
```
