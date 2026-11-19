# 🇮🇩 Aturan Wajib: Clean Code & Standar Dokumentasi Bahasa Indonesia (Standar AniCine)

Seluruh kode yang ditulis di repositori **AniCine** (*Anime & Cinema Schedule Integration API*) **WAJIB** mengutamakan **keterbacaan (*readability*)** dan menggunakan penjelasan **Bahasa Indonesia** agar seluruh anggota tim (Brian & partner integrasi Sat) dapat memahami, memodifikasi, dan menjelaskan alur kode dengan lancar saat demo/presentasi dosen.

---

## 1. Standar Penamaan Hybrid (`Naming Convention`)

Projek **AniCine** mengintegrasikan dua database PostgreSQL (`db_anitrack` dan `db_bioskop`) yang skema tabelnya menggunakan Bahasa Inggris. Agar konsisten dan tidak menimbulkan kebingungan pemetaan data, gunakan standar **Hybrid** berikut:

### A. Kolom Database & Key JSON Response (`snake_case` Bahasa Inggris sesuai Skema)
Seluruh nama kolom database dan *array key* pada payload JSON mengikuti skema asli `db_anitrack` (schema `anitrack`) dan `db_bioskop` (schema `public`):
- ✅ **Skema Katalog Anime (`db_anitrack`):**
  `anime_id`, `title`, `title_english`, `title_japanese`, `type_id`, `type_name`, `duration_min`, `rating_id`, `rating_code`, `score`, `premiered_year`, `genres`, `studios`
- ✅ **Skema Integrasi & Jadwal Bioskop Medan (`db_bioskop`):**
  `cinema_movie_id`, `is_theatrical`, `cinema_showtimes`, `showtime_id`, `show_date`, `start_time`, `ticket_price`, `movie_id`, `cinema_name`, `kecamatan`, `studio_name`, `studio_type`, `total_capacity`, `seats_booked`, `seats_available`

### B. Nama Variabel & Method PHP (`camelCase` Bahasa Inggris Deskriptif)
Gunakan nama variabel dan method dalam Bahasa Inggris yang deskriptif, jelas, dan selaras dengan kode yang sudah ada (`Anime.php`, `Cinema.php`, `AnimeModel.php`, `CinemaService.php`):
- ❌ **SALAH (Singkatan Abstrak / Tidak Jelas):**
  `$d`, `$r`, `$x`, `$tmp`, `proc()`, `doData()`
- ✅ **BENAR (Deskriptif & Jelas):**
  - Variabel: `$movieId`, `$animeId`, `$animeOnly`, `$theatricalIds`, `$externalOnline`, `$localOnline`, `$perPage`, `$showtimes`
  - Method Model/Library: `getTheatricalList()`, `getRichDetail()`, `getShowtimes()`, `getMovies()`, `getMovieDetail()`, `getStudios()`, `getStatus()`, `enrichMoviesList()`
  - Method Helper Controller: `getInput()`, `getPerPage()`, `respondPaginated()`
  - *(Catatan: Parameter route berbahasa Indonesia yang sudah ada seperti `$tahun` pada endpoint `/api/anime/tahun/{tahun}` tetap dipertahankan sesuai spesifikasi tugas).*

### C. Komentar Kode, Docblock, & Pesan Respons API (WAJIB Bahasa Indonesia)
Seluruh elemen komunikasi manusia di dalam kode **WAJIB** menggunakan Bahasa Indonesia yang jelas dan rapi:
1. **Komentar Baris (`// ...`) & PHPDoc (`/** ... */`)**: Jelaskan tujuan fungsi dan alur logika dalam Bahasa Indonesia.
2. **Pesan Validasi (`$validationMessages` di Model)**: Gunakan pesan error yang informatif dalam Bahasa Indonesia (contoh: `'Judul (title) wajib diisi.'`, `'Format aired_from harus YYYY-MM-DD.'`).
3. **Field `message` pada JSON Response**: Gunakan Bahasa Indonesia yang konsisten (contoh: `'Daftar anime'`, `'Detail anime'`, `'Jadwal tayang bioskop Medan'`).

---

## 2. Prinsip Clean Code Ramah Mahasiswa (Bukan Kode "Pintar" yang Membingungkan)

1. **Keterbacaan Lebih Penting daripada Baris Pendek (*Readability over Brevity*):**
   - **DILARANG** memadatkan logika kompleks ke dalam 1 baris panjang (*nested ternary* `? : ? :` atau rantai `array_map`/`array_reduce` berlapis).
   - Pecah proses menjadi langkah-langkah berurutan dengan blok `if/else` dan `try/catch` yang jelas agar mudah diikuti mata.
   - **Contoh Buruk (Susah Dibaca):**
     ```php
     $data['cinema_showtimes'] = (!empty($data['is_theatrical']) && !empty($data['cinema_movie_id'])) ? (new CinemaService())->getShowtimes((int)$data['cinema_movie_id']) : [];
     ```
   - **Contoh Baik (Clean Code Sesuai Standar AniCine):**
     ```php
     // Jika anime tayang di bioskop, otomatis ambil jadwal tayang dari CinemaService (Service Sat)
     if (! empty($data['is_theatrical']) && ! empty($data['cinema_movie_id'])) {
         try {
             $cinema = new CinemaService();
             $data['cinema_showtimes'] = $cinema->getShowtimes((int) $data['cinema_movie_id']);
         } catch (Exception $e) {
             $data['cinema_showtimes'] = [];
         }
     } else {
         $data['cinema_showtimes'] = [];
     }
     ```

2. **Komentar Penjelas Berbahasa Indonesia:**
   - Berikan komentar singkat 1 baris (`// ...`) dalam Bahasa Indonesia di atas blok logika penting, seperti:
     - Alur *Data Mashup* antara `AnimeModel` dan `CinemaService`.
     - Mekanisme *Hybrid* (percobaan HTTP request ke External API Sat lalu *fallback* ke database lokal `db_bioskop`).
     - Penanganan constraint database PostgreSQL (seperti `emptyStringToNull` atau `ON DELETE RESTRICT`).
   - Tujuannya agar setiap anggota kelompok langsung mengerti fungsi blok kode tersebut dalam sekali baca.

3. **Anti Over-Engineering (Tetap Sederhana & Lurus):**
   - Gunakan arsitektur standar CodeIgniter 4 (`ResourceController` $\rightarrow$ `Model` / `Library`).
   - Jangan membuat *Design Pattern* berlapis-lapis (seperti *Repository*, *DTO*, *Factory*, *Trait* abstrak) atau meng-install library tambahan yang tidak diminta.
   - Satu fungsi hanya mengerjakan satu tugas yang jelas (*Single Responsibility*).
