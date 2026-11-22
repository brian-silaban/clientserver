# AGENTS.md — AniCine v2.0: Anime Catalog & Cinema Ticket Booking Platform

Dokumen ini adalah panduan utama (*Single Source of Truth*) untuk seluruh AI Coding Agent (**Antigravity**, **Claude Code**, dan **OpenAI CODEX**) yang bekerja di repositori ini.

---

## 0. Dokumen Acuan Wajib (Baca Sebelum Menulis Kode)
1. **[`arsitektur_dan_rancangan_projek.md`](arsitektur_dan_rancangan_projek.md)** — Spesifikasi lengkap arsitektur **AniCine v2.0**, kepemilikan database (`db_anitrack` milik sendiri vs `db_bioskop` sementara milik partner), ERD, matriks hak akses DB, RLS, Audit Trigger, transaksi ACID booking, spesifikasi REST API `/api/v1/*`, Backup API, halaman Vue 3, dan deployment VM Azure.
2. **[`.agents/rules/clean-code-indonesia.md`](.agents/rules/clean-code-indonesia.md)** — Ruleset wajib **Clean Code & Konvensi Hybrid** untuk Backend PHP CI4 (`backend/`) dan Frontend Vue 3 (`frontend/`).
3. **[`.agents/rules/anti-ai-slop.md`](.agents/rules/anti-ai-slop.md)** — Ruleset wajib **Anti-AI-Slop UI/UX Design (AniCine Vue 3 Edition)** untuk seluruh tampilan antarmuka di `frontend/src/**`.
4. **[`Materi Kuliah Client Server/`](Materi%20Kuliah%20Client%20Server/)** — Acuan standar kualitas *Manajemen Database Client Server (PLMKB502)*: Rubrik Evaluasi K1–K10, Function & Trigger Audit Log, Keamanan Basis Data (Role/GRANT/RLS/SSL), serta Strategi & Praktikum Backup-Restore CI4.

---

## 1. Visi Produk, Role & Arsitektur Sistem
- **Nama Projek**: **AniCine** (*Anime Catalog & Cinema Ticket Booking Platform*)
- **Tujuan Aplikasi**:
  - Menyajikan **seluruh katalog anime** (`db_anitrack`) secara terbuka kepada pengunjung.
  - Menampilkan **khusus anime yang sedang tayang (`NOW_SHOWING`) atau akan tayang (`UPCOMING`) di bioskop Medan** (film non-anime dari layanan bioskop partner otomatis disaring dari sisi client).
  - Memungkinkan pengguna terdaftar memesan **tiket bioskop anime** (pilih jadwal → pilih kursi interaktif → checkout/bayar → e-ticket → batal) dan mengelola **My Anime List / Watchlist & Rating Pribadi**.
- **3 Peran Pengguna (*RBAC*)**:
  1. **Guest (Tanpa Login)**: Akses baca katalog anime, detail anime, daftar anime tayang bioskop, jadwal, dan pratinjau kursi. Diarahkan ke Login/Register saat mencoba memesan tiket atau menyimpan watchlist.
  2. **User (`role: user`, JWT)**: Semua akses Guest + pemesanan tiket bioskop, riwayat e-ticket, pembatalan pesanan, dan manajemen watchlist/rating pribadi.
  3. **Admin (`role: admin`, JWT)**: Dashboard admin, CRUD katalog anime, manajemen pemetaan anime ↔ bioskop (`anime_cinema_map`), manajemen jadwal (mode fallback), manajemen booking & user, **Audit Log Viewer**, serta **Backup & Verifikasi Database**.

- **Tech Stack & Alokasi Port (Konsisten Lokal & VM Azure)**:
  - **Backend (`backend/`)**: PHP 8.2+ dengan **CodeIgniter 4 (CI4)** — Pure JSON REST API **`/api/v1/*`** pada **Port `8083`** (tidak bentrok dengan proyek kelompok di VM Azure pada port `8080` & `8082`).
  - **Frontend (`frontend/`)**: **Vue 3 + Vite + Vue Router 4 + Pinia + Tailwind CSS** (multi-halaman SPA, tidak ada file view monolitik 1 halaman) pada **Port `8084`**. Frontend **tidak pernah** mengakses PostgreSQL secara langsung; seluruh data lewat REST API `backend/` (`VITE_API_BASE_URL`).
  - **Target Deployment VM Azure (Azure for Students)**:
    - Spesifikasi: 4 GB RAM, Dual Core, Premium SSD 30 GB (`tmux`, `PHP`, `psql`, `neovim`, `nano`).
    - Inbound TCP terbuka: Port **`8080–8085`** dan PostgreSQL **`5432`**.
    - Port terpakai proyek kelompok: `8080` (backend kelompok) & `8082` (frontend kelompok).
    - **Port AniCine**: **`8083`** (Backend CI4) & **`8084`** (Frontend Vue build `dist/` / preview).

---

## 2. Kepemilikan Database & Integrasi Partner (`db_anitrack` vs `db_bioskop`)
1. **`db_anitrack` (Milik Sendiri / Brian — grup `default`, schema `anitrack`)**:
   - Database utama (*Single Source of Truth* milik AniCine).
   - Menyimpan katalog anime (`anime`, `media_type`, `age_rating`, `genre`, `company`, dll.), akun pengguna & autentikasi (`app_user`), pemetaan dinamis anime tayang bioskop (`anime_cinema_map`), watchlist (`user_anime_list`, `user_anime_tag`), **catatan pemesanan tiket milik AniCine (`cinema_bookings`)**, dan `audit_log`.
   - **Seluruh fitur PLMKB502** (user DB *least privilege* `api_anicine`, RLS pada `user_anime_list` & `cinema_bookings`, trigger `fn_audit()`, function PL/pgSQL `fn_hitung_umur()` & `fn_user_status_summary()`, *optimistic locking* `version`, serta backup `pg_dump -Fc` via `r_backup`) **wajib berjalan penuh & mandiri di `db_anitrack`**.
2. **`db_bioskop` (Milik Partner / Satria — grup `bioskop`, schema `public`, SEMENTARA)**:
   - Hanya dipakai sementara sebagai **mock / `LOCAL_FALLBACK`** selama REST API buatan Satria belum selesai.
   - Pada integrasi akhir/produksi, `db_bioskop` lokal **tidak digunakan lagi** — seluruh data bioskop (film, jadwal, peta kursi, reservasi kursi, pembatalan kursi) dikonsumsi melalui HTTP ke **External REST API Partner** via `CinemaService` (`cinema.api.baseURL` di `backend/.env`).
   - **Aturan Ketat**: Jangan memodifikasi skema inti `db_bioskop` secara invasif agar kompatibel 100% saat beralih ke `EXTERNAL_API`. Seluruh interaksi ke sisi bioskop wajib melewati adapter `App\Libraries\CinemaService` dengan blok `try/catch` yang rapi.

---

## 3. Standar Keamanan, Database & Evaluasi PLMKB502 (Wajib Dipatuhi)
1. **Autentikasi JWT & RBAC**:
   - Gunakan `firebase/php-jwt` + CI4 Filter `jwt` (`App\Filters\JwtFilter`) dengan parameter role (`jwt:user,admin` atau `jwt:admin`).
   - Request tanpa token valid → **`401 Unauthorized`**; role tidak berhak → **`403 Forbidden`**.
2. **Keamanan Database (*Least Privilege* & RLS)**:
   - Aplikasi backend di produksi/VM Azure **dilarang** terhubung dengan akun superuser `postgres`; gunakan akun khusus aplikasi **`api_anicine`** (LOGIN, `CONNECTION LIMIT`, tanpa `SUPERUSER`/`CREATEDB`/`BYPASSRLS`) yang diatur lewat `backend/database/security_setup.sql`.
   - Teruskan identitas user ke sesi transaksi PostgreSQL via `SELECT set_config('app.user_id', ?, true)` dan `SELECT set_config('app.user_role', ?, true)` agar **Row Level Security (RLS)** dan kolom `app_user` di `audit_log` bekerja akurat.
   - Karena port `5432` terbuka di VM Azure, konfigurasi `pg_hba.conf` wajib menggunakan `scram-sha-256` (+ `hostssl` bila SSL aktif), dilarang keras memakai `trust` atau `md5`.
3. **Audit Log & Objek Database**:
   - Trigger generik `anitrack.fn_audit()` (`SECURITY DEFINER`, `SET search_path = anitrack, public`) wajib mencatat perubahan `INSERT/UPDATE/DELETE` ke `anitrack.audit_log`. Hak `UPDATE, DELETE, TRUNCATE` pada `audit_log` wajib dicabut dari `PUBLIC`.
4. **Transaksi ACID & Konkurensi**:
   - Pemesanan kursi wajib menggunakan transaksi ACID + *row locking* (`SELECT ... FOR UPDATE`) + constraint `UNIQUE (showtime_id, seat_id)` (pada fallback) / respons konflik API partner → kembalikan **`409 Conflict`** jika kursi sudah diambil pengguna lain, dan rollback penuh bila salah satu langkah gagal.
   - Pembaruan data katalog (`anime`) dan pemetaan (`anime_cinema_map`) oleh Admin wajib menerapkan *optimistic locking* (`version`).
5. **Validasi, Format Respons & Rate Limiting**:
   - Format JSON seragam: `{ "status": 200, "message": "...", "data": ..., "pager"?: ..., "errors"?: ... }`.
   - Input tidak valid → **`422 Unprocessable Entity`** dengan pesan Bahasa Indonesia; jangan pernah membocorkan stack trace server.
   - Aktifkan CI4 `Throttler` (*rate limiting*) pada endpoint sensitif (`auth/login`, `auth/register`, `bookings`, `backups`).
   - Seluruh query wajib menggunakan Query Builder / Query Binding (`?`) / `$db->prepare()` (anti SQL Injection).
6. **Backup & Verifikasi Database (Admin Only)**:
   - Gunakan `App\Services\BackupService` + akun DB read-only `r_backup` (`pg_read_all_data`, `BYPASSRLS`, `PGPASSFILE`) menyimpan ke `writable/backups/` (di luar `public/`).
   - Wajib gunakan `escapeshellarg()` dan whitelist regex nama file (anti *path traversal*).
   - Endpoint hanya `GET /api/v1/backups`, `POST /api/v1/backups`, `GET /api/v1/backups/{name}/verify` (`filter: jwt:admin`). **Dilarang** membuka endpoint restore atau download file dump publik lewat HTTP.
   - Sediakan CLI `php spark backup:run` serta skrip `scripts/backup.sh` dan `scripts/verify.sh` untuk cron.

---

## 4. Konvensi Kode & UI/UX
- **Clean Code Hybrid** ([`.agents/rules/clean-code-indonesia.md`](.agents/rules/clean-code-indonesia.md)):
  - Kolom DB & JSON key: `snake_case` Bahasa Inggris sesuai skema.
  - Variabel, method PHP & fungsi JS/Vue: `camelCase` Bahasa Inggris deskriptif; nama komponen Vue: `PascalCase`.
  - Komentar, PHPDoc/JSDoc, validasi, label UI, & `message` API: **wajib Bahasa Indonesia**.
  - Prioritaskan keterbacaan (*Readability over Brevity*) dan hindari *over-engineering*.
- **Anti-AI-Slop UI/UX** ([`.agents/rules/anti-ai-slop.md`](.agents/rules/anti-ai-slop.md)):
  - Persona **Editorial Cinema & Curated Anime Archive** (*Warm Stone* `#FAF8F5`, *Deep Charcoal* `#121316`, *Crimson Curtain* `#BE123C`, *Projector Amber* `#D97706`).
  - Tipografi: `Fraunces` (Display), `Plus Jakarta Sans` (UI/Body + `tabular-nums`), `JetBrains Mono` (Kode booking/API).
  - Wajib lolos 10 checklist `Sloplint` dan tidak boleh menggabungkan seluruh halaman ke dalam satu file raksasa.

---

## 5. Perintah Terminal (CLI)
```bash
# 1. Migrasi & Seeder Database (dari folder backend/)
cd backend
php spark migrate
php spark db:seed AniCineSeeder

# 2. Menjalankan Backend REST API v1 (Port 8083 — Lokal & VM Azure)
cd backend
php spark serve --host 0.0.0.0 --port 8083

# 3. Menjalankan Frontend Vue 3 (Port 8084 — Mode Development)
cd frontend
npm install
npm run dev -- --host 0.0.0.0 --port 8084

# 4. Build & Jalankan Frontend Produksi Hemat RAM di VM Azure (Port 8084)
cd frontend
npm run build
npm run preview -- --host 0.0.0.0 --port 8084

# 5. Menjalankan Backup Otomatis via Spark CLI (dari folder backend/)
cd backend
php spark backup:run
```
