# 🎬 AniCine v2.0 — Anime Catalog & Cinema Ticket Booking Platform

Platform web **Client-Server Full-Stack** yang menggabungkan katalog anime (**`db_anitrack`**) dengan layanan **pemesanan tiket bioskop khusus film anime** di Kota Medan (terintegrasi secara *hybrid* ke layanan bioskop partner Satria).

Dibangun dengan standar keamanan dan manajemen basis data **PLMKB502 (Manajemen Database Client Server)** sebagai proyek portofolio: **JWT + RBAC (`Guest`, `User`, `Admin`)**, **PostgreSQL Least Privilege & Row Level Security (RLS)**, **Audit Trigger JSONB**, **Transaksi ACID + Seat Locking**, serta **Backup & Verifikasi Database via REST API & Cron**.

---

## ✨ Fitur Utama

- **Untuk Pengunjung (*Guest* — Tanpa Login)**
  - Menjelajahi ribuan katalog anime (`db_anitrack`) dengan pencarian, filter tipe/genre/tahun, sorting, dan paginasi.
  - Melihat detail lengkap anime beserta jadwal bioskop Medan (jika sedang/akan tayang).
  - Halaman **Anime di Bioskop** yang otomatis menyaring dan **hanya menampilkan film anime** berstatus *Now Showing* atau *Upcoming*, lengkap dengan pratinjau denah kursi studio.
- **Untuk Member (*User* — Wajib Daftar & Login)**
  - **Pemesanan Tiket Bioskop Anime:** Pilih jadwal → pilih kursi interaktif (*real-time anti-double-booking* dengan `SELECT ... FOR UPDATE`) → konfirmasi/simulasi pembayaran → **E-Ticket** & riwayat transaksi → pembatalan pesanan.
  - **My Anime List / Watchlist:** Kelola status tontonan (*Watching, Completed, On-Hold, Dropped, Plan to Watch*), progres episode, rating pribadi, dan statistik tontonan (`v_user_stats`).
- **Untuk Administrator (*Admin*)**
  - Dashboard ringkasan metrik katalog, penayangan, dan transaksi tiket.
  - CRUD katalog anime (dilengkapi *optimistic locking* `version`).
  - Manajemen pemetaan dinamis **Anime ↔ Film Bioskop** (`NOW_SHOWING`, `UPCOMING`, `ENDED`) dan jadwal penayangan.
  - Monitoring transaksi booking, manajemen user, **Audit Log Viewer** (`old_data` vs `new_data`), serta **Panel Backup & Verifikasi Database** (`pg_dump -Fc` + SHA-256).

---

## 🧩 Tech Stack & Struktur Direktori

- **Backend (`backend/`, Port `8083`):** PHP 8.2+, CodeIgniter 4, `firebase/php-jwt`, PostgreSQL 16/17.
- **Frontend (`frontend/`, Port `8084`):** Vue 3 (`<script setup>`), Vite, Vue Router 4, Pinia, Tailwind CSS, `lucide-vue-next`.
- **Integrasi Bioskop:** Adapter `CinemaService` mendukung **`EXTERNAL_API`** (target produksi: REST API Partner Satria via `cinema.api.baseURL`) dan **`LOCAL_FALLBACK`** (sementara selama pengembangan memakai database lokal `db_bioskop`).

```text
clientserver/
├── backend/                        # Server REST API v1 — CodeIgniter 4 (Port 8083)
│   ├── app/Controllers/Api/        # Auth, Anime, Watchlist, Cinema, Booking, Admin, Backup
│   ├── app/Filters/                # JwtFilter (Auth + RBAC), ThrottleFilter
│   ├── app/Libraries/              # CinemaService (Hybrid EXTERNAL_API / LOCAL_FALLBACK)
│   ├── app/Services/               # BackupService, JwtService, RlsContextService
│   ├── app/Commands/               # php spark backup:run (untuk Cron)
│   ├── app/Database/Migrations/    # Migrasi skema, trigger audit, view, function, RLS
│   ├── app/Database/Seeds/         # Seeder katalog, akun Admin/User demo, kursi fallback
│   └── database/                   # security_setup.sql (role, least privilege, RLS)
├── frontend/                       # Client Web SPA Multi-Halaman — Vue 3 + Vite (Port 8084)
│   └── src/                        # api/, stores/, router/, layouts/, components/, views/
├── scripts/                        # backup.sh & verify.sh (verifikasi 4 tingkat + retensi)
├── postman/                        # Koleksi pengujian REST API v1 (Postman)
├── arsitektur_dan_rancangan_projek.md
├── AGENTS.md
└── README.md
```

---

## ⚡ Panduan Instalasi & Menjalankan Secara Lokal

### Prasyarat
- PHP 8.2+ (dengan ekstensi `pgsql`, `intl`, `mbstring`, `curl`) & Composer
- PostgreSQL 16/17 (`psql`, `pg_dump`, `pg_restore`)
- Node.js 20+ & `npm`

### 1. Siapkan Database PostgreSQL
Buat database `db_anitrack` (dan sementara `db_bioskop` untuk mode fallback jika API partner belum aktif), lalu salin dan sesuaikan konfigurasi `backend/.env`:
```bash
cp backend/env backend/.env
```
Jalankan migrasi dan seeder dari folder `backend/`:
```bash
cd backend
composer install
php spark migrate
php spark db:seed AniCineSeeder
```
*(Opsional / Produksi)* Terapkan *least privilege role*, `REVOKE PUBLIC`, dan kebijakan keamanan database:
```bash
psql -U postgres -d db_anitrack -f backend/database/security_setup.sql
```

### 2. Jalankan Server Backend REST API v1 (Port `8083`)
```bash
cd backend
php spark serve --host 0.0.0.0 --port 8083
```
Cek status API di browser: `http://localhost:8083` atau `http://localhost:8083/api/v1/anime/theatrical`.

### 3. Jalankan Frontend Vue 3 SPA (Port `8084`)
Buka terminal baru:
```bash
cd frontend
npm install
npm run dev -- --host 0.0.0.0 --port 8084
```
Buka aplikasi AniCine di browser: **`http://localhost:8084`**.

---

## ☁️ Panduan Deployment di VM Azure (4 GB RAM · Port `8083` & `8084`)

Port `8080` dan `8082` pada VM Azure telah digunakan oleh proyek kelompok, sehingga **AniCine** memakai port **`8083`** (Backend) dan **`8084`** (Frontend) di dalam rentang inbound port `8080–8085`.

1. **Jalankan Backend di sesi `tmux` terpisah:**
   ```bash
   tmux new -s anicine-backend
   cd ~/clientserver/backend
   php spark serve --host 0.0.0.0 --port 8083
   ```
2. **Build & jalankan Frontend produksi (hemat RAM) di sesi `tmux` terpisah:**
   ```bash
   tmux new -s anicine-frontend
   cd ~/clientserver/frontend
   npm ci
   npm run build
   npm run preview -- --host 0.0.0.0 --port 8084
   ```
3. **Konfigurasi Integrasi API Partner:**
   Ketika REST API Bioskop Satria sudah aktif, isi `cinema.api.baseURL` pada `backend/.env` agar `CinemaService` otomatis beralih dari `LOCAL_FALLBACK` ke `EXTERNAL_API` tanpa perlu menyalakan `db_bioskop` lokal.
4. **Hardening PostgreSQL (Port `5432` Terbuka):**
   Pastikan `pg_hba.conf` menggunakan autentikasi `scram-sha-256` (+ `hostssl`), batasi IP akses, dan gunakan user aplikasi `api_anicine` (bukan superuser `postgres`) di `backend/.env`.

---

## 💾 Otomatisasi Backup & Verifikasi (Cron)

```bash
# Uji backup manual via Spark CLI (disimpan ke backend/writable/backups/)
cd backend
php spark backup:run

# Contoh jadwal crontab harian (01:30 backup) & mingguan (Minggu 03:00 verifikasi restore)
30 1 * * * cd /path/to/clientserver/backend && php spark backup:run >> writable/logs/backup.log 2>&1
0  3 * * 0 /path/to/clientserver/scripts/verify.sh >> /var/log/anicine_verify.log 2>&1
```
