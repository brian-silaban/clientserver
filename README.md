# 🎬 AniCine — Anime & Cinema Schedule Integration (Client-Server)

Projek tugas mata kuliah **Pemrograman Client-Server** yang mengintegrasikan katalog anime (**`db_anitrack`**) dengan informasi jadwal tayang bioskop di Medan (**`db_bioskop`**) menggunakan arsitektur **2 Projek CodeIgniter 4 Terpisah (`backend/` & `frontend/`)**.

---

## 🧩 Struktur Folder (`backend/` & `frontend/`)

```text
clientserver/
├── backend/                  <-- 🖥️ SERVER REST API & PostgreSQL Lokal (Port 8080)
│   ├── app/Controllers/Api/  <-- Endpoint JSON (/api/anime/* & /api/cinema/*)
│   ├── app/Models/           <-- AnimeModel.php (query db_anitrack)
│   └── app/Libraries/        <-- CinemaService.php (integrasi Hybrid HTTP / db_bioskop)
├── frontend/                 <-- 🌐 CLIENT WEB UI AniCine (Port 8081)
│   ├── app/Controllers/      <-- Home.php (menampilkan halaman web)
│   └── app/Views/            <-- anicine_landing.php (mengonsumsi REST API dari Port 8080)
├── arsitektur_dan_rancangan_projek.md
├── AGENTS.md
├── tambah_anime_ke_bioskop.sql
├── test_api.php
└── postman/
```

---

## ⚡ Cara Menjalankan Projek (Database PostgreSQL Lokal)

### 1. Jalankan Server Backend REST API (Port `8080`)
Pastikan database PostgreSQL lokal (`db_anitrack` dan `db_bioskop`) sudah aktif dan sesuai di `backend/.env`, lalu jalankan:
```bash
cd backend
php spark serve --port 8080
```
- Cek status API di browser: **`http://localhost:8080`** atau **`http://localhost:8080/api/anime/theatrical`**

### 2. Jalankan Client Frontend Web UI (Port `8081`)
Buka terminal baru, masuk ke folder `frontend/`, lalu jalankan:
```bash
cd frontend
php spark serve --port 8081
```
- Buka tampilan web AniCine di browser: **`http://localhost:8081`**
- Frontend pada port `8081` akan otomatis mengambil data JSON secara live dari REST API Backend pada port `8080`.

### 3. Pengujian Cepat via CLI
Dari folder root projek:
```bash
php test_api.php
```
