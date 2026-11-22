# 🇮🇩 Aturan Wajib: Clean Code & Standar Dokumentasi Bahasa Indonesia (AniCine v2.0)

Seluruh kode yang ditulis di repositori **AniCine v2.0**—baik **Backend PHP CodeIgniter 4 (`backend/`)** maupun **Frontend Vue 3 + Pinia (`frontend/`)**—**WAJIB** mengutamakan **keterbacaan (*readability*)**, keamanan (*secure by default*), dan penjelasan **Bahasa Indonesia** yang jelas agar mudah dipelihara dan layak dijadikan portofolio profesional.

---

## 1. Standar Penamaan Hybrid (`Naming Convention`)

Projek **AniCine** memakai skema PostgreSQL berbahasa Inggris (`db_anitrack` milik sendiri + adapter `db_bioskop`/API Partner). Gunakan konvensi **Hybrid** berikut agar konsisten dari database hingga antarmuka Vue 3:

### A. Kolom Database & Key JSON Response (`snake_case` Bahasa Inggris sesuai Skema)
- **Katalog & Akun (`db_anitrack`):**
  `anime_id`, `title`, `title_english`, `title_japanese`, `type_id`, `type_name`, `duration_min`, `rating_id`, `rating_code`, `score`, `premiered_year`, `genres`, `studios`, `version`, `user_id`, `username`, `email`, `role`, `birth_date`, `screening_status`, `is_featured`
- **Watchlist & Booking AniCine (`db_anitrack`):**
  `status_id`, `watched_episodes`, `booking_id`, `booking_code`, `partner_booking_id`, `seat_ids_json`, `seat_labels`, `seat_count`, `ticket_price`, `total_price`, `payment_status`, `expires_at`, `paid_at`, `cancelled_at`
- **Audit Log & Backup:**
  `table_name`, `operation`, `row_pk`, `old_data`, `new_data`, `changed_by`, `app_user`, `client_ip`, `changed_at`, `checksum`, `readable`
- **Jadwal & Kursi Bioskop (Partner API / Fallback `db_bioskop`):**
  `cinema_movie_id`, `is_theatrical`, `cinema_showtimes`, `showtime_id`, `show_date`, `start_time`, `cinema_name`, `kecamatan`, `studio_name`, `studio_type`, `seat_id`, `seat_code`, `row_label`, `seat_number`, `is_booked`, `total_capacity`, `seats_booked`, `seats_available`

### B. Nama Variabel, Method PHP & JavaScript/Vue
- **PHP (`backend/`):** `camelCase` Bahasa Inggris deskriptif untuk variabel & method (`$animeId`, `$showtimeId`, `$selectedSeatIds`, `createBooking()`, `applyRlsContext()`, `verifyBackupFile()`), `PascalCase` untuk class (`JwtFilter`, `BackupService`, `CinemaService`).
- **Vue 3 (`frontend/src/`):**
  - Nama file & komponen: `PascalCase` (`AnimeCard.vue`, `SeatMapPicker.vue`, `TicketStubCard.vue`, `AdminBackupView.vue`).
  - Composables: `use*.js` (`useAuth.js`, `useFormatters.js`).
  - Pinia Store: `use*Store` (`useAuthStore`, `useBookingStore`).
  - Variabel & fungsi JS: `camelCase` deskriptif (`selectedSeats`, `fetchTheatricalAnime()`, `submitSeatBooking()`).
- ❌ **Dilarang:** singkatan tidak jelas seperti `$d`, `$r`, `$tmp`, `fn1()`, `doIt()`.

### C. Komentar Kode, Docblock, Validasi, Label UI & Pesan API (WAJIB Bahasa Indonesia)
1. **Komentar (`// ...`), PHPDoc (`/** ... */`), & JSDoc**: Jelaskan maksud fungsi, alur transaksi, dan alasan keputusan penting dalam Bahasa Indonesia.
2. **Pesan Validasi & Respons JSON (`message`, `errors`)**: Wajib Bahasa Indonesia yang sopan, informatif, dan aman (tanpa membocorkan stack trace atau nama tabel internal saat error server).
   - Contoh baik: `'Kursi A3 baru saja dipesan oleh pengguna lain. Silakan pilih kursi lain.'`, `'Anda harus masuk terlebih dahulu untuk memesan tiket.'`, `'Akses ditolak. Endpoint ini hanya untuk administrator.'`
3. **Copywriting & Label Antarmuka Vue 3**: Seluruh label tombol, navigasi, pesan kosong (*empty state*), notifikasi *toast*, dan konfirmasi dialog ditulis dalam Bahasa Indonesia yang natural dan konsisten.

---

## 2. Prinsip Clean Code & Keamanan Wajib

1. **Keterbacaan di Atas Baris Pendek (*Readability over Brevity*):**
   - **DILARANG** memadatkan logika bisnis (transaksi booking, cek role, *fallback* API) ke dalam *nested ternary* 1 baris atau rantai `array_reduce`/`map` yang sulit dilacak.
   - Gunakan `if/else` dengan *early return* dan `try/catch` yang eksplisit.

2. **Disiplin Keamanan Backend (PLMKB502):**
   - **Anti SQL Injection:** Selalu gunakan CI4 Query Builder, query binding (`?`), atau `$db->prepare()`. Dilarang menyambung input user ke string SQL.
   - **Konteks RLS & Audit di Setiap Transaksi:** Setiap operasi tulis atau baca tabel terproteksi RLS (`user_anime_list`, `cinema_bookings`) wajib menyetel `set_config('app.user_id', ?, true)` dan `set_config('app.user_role', ?, true)` di dalam transaksi.
   - **Anti Command Injection & Path Traversal:** Pemanggilan `pg_dump` / `pg_restore` di `BackupService` wajib memakai `escapeshellarg()`, menyimpan file di `WRITEPATH . 'backups/'`, dan memvalidasi nama file dengan regex *whitelist* ketat.
   - **Konkurensi:** Selalu bungkus proses booking kursi dalam transaksi ACID (`transBegin` / `transCommit` / `transRollback`) dan tangani konflik dengan HTTP `409 Conflict`.

3. **Modularitas Frontend Vue 3 (Tanpa File Monolitik):**
   - Dilarang menumpuk seluruh halaman aplikasi dalam satu file view raksasa.
   - Pisahkan halaman ke `src/views/`, komponen ulang pakai ke `src/components/`, komunikasi API ke `src/api/`, dan state global ke `src/stores/`.
   - Gunakan Vue 3 Composition API (`<script setup>`) secara konsisten di seluruh komponen.

4. **Anti Over-Engineering (Tetap Sederhana & Terstruktur):**
   - Di Backend CI4, cukup gunakan lapisan standar: `Routes` → `Filters` (`jwt`, `throttle`) → `Controllers/Api` → `Services`/`Libraries` → `Models`.
   - Tidak perlu menambah *pattern* abstrak berlebihan (*Repository* berlapis, *Abstract Factory*, *CQRS*) yang tidak dibutuhkan skala proyek ini.
   - Satu fungsi / komponen mengerjakan satu tanggung jawab yang jelas (*Single Responsibility*).
