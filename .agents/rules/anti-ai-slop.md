# 🛡️ Ultimate Anti-AI-Slop UI/UX Ruleset (AniCine v2.0 — Vue 3 + Tailwind Edition)

> **Cakupan Wajib:** Ruleset ini berlaku untuk seluruh pengembangan antarmuka web **AniCine v2.0** di dalam **`frontend/src/**`** (Vue 3 + Vite + Tailwind CSS + `lucide-vue-next`), mencakup halaman Publik, Autentikasi, Pemesanan Tiket & Kursi, Watchlist, hingga Panel Admin.

Ruleset ini menggabungkan standar dari 4 referensi Anti-AI-Slop terbaik:
1. **`anthropics/skills` (`frontend-design`)** — *Aesthetic Direction & Subject-Matter Grounding*
2. **`pbakaus/impeccable`** — *The 5 Hallmark AI-Slop Anti-Patterns*
3. **`Nutlope/hallmark` (`sloplint`)** — *Macrostructures & Quality Gate Rules*
4. **`ui-ux-pro-max`** — *Intentional Design Tokens & Information Density*

---

## 1. The 5 Hallmark "AI Slop" Anti-Patterns (DILARANG KERAS)

1. **🚫 Font Default Seragam di Semua Elemen (`Overused Font Trap`)**
   - Jangan gunakan `Inter`, `Roboto`, atau `Arial` untuk semua elemen. Pertahankan kontras tipografi editorial (`Fraunces` untuk judul display/anime, `Plus Jakarta Sans` untuk UI/body, `JetBrains Mono` untuk kode booking, kursi, dan log).
2. **🚫 Gradien Ungu-ke-Biru / Neon SaaS (`Purple-to-Blue Gradient Cliché`)**
   - Dilarang memakai `bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500` pada hero, tombol, atau kartu. AniCine adalah **arsip katalog anime & loket tiket sinema**, bukan template startup AI SaaS.
3. **🚫 Kartu di Dalam Kartu di Dalam Kartu (`Endless Nested Cards`)**
   - Hindari membungkus setiap blok informasi dengan `border rounded-xl p-6 shadow-md bg-white` berlapis-lapis. Gunakan *whitespace* lega, garis pembatas tipis (`divide-y divide-stone-200`), dan aksen garis putus-putus (`border-dashed`) khas potongan tiket bioskop.
4. **🚫 Teks Abu-Abu Pudar & Kontras Rendah di Atas Poster (`Low-Contrast Typography`)**
   - Jangan menaruh `text-gray-400` di atas latar terang, atau teks putih telanjang di atas poster anime tanpa *scrim/gradient overlay* gelap (`bg-gradient-to-t from-black/85 via-black/35 to-transparent`).
5. **🚫 Grid 3-Kolom Ikon Kotak Bulat (`The Mechanical Icon Tile Grid`)**
   - Dilarang membuat section marketing generik berisi 3 kotak ikon acak + kalimat promosi klise (*"Cepat & Mudah"*, *"Aman Terpercaya"*). Ganti dengan konten katalog nyata, jadwal tayang Medan, atau data ringkasan aktual.

---

## 2. Macrostructure & Pola Layout Multi-Halaman (Vue 3)

Jangan pernah menggabungkan seluruh aplikasi ke dalam satu halaman panjang. Terapkan struktur per halaman yang selaras dengan dunia **Katalog Sinema & Loket Tiket** (*Letterboxd*, *Criterion*, *TIX ID*):

1. **Beranda (`HomeView.vue`):**
   - *Theatrical Spotlight Hero* berlatar *Deep Theater Charcoal* (`#121316`) khusus menyoroti **anime yang sedang/akan tayang di bioskop Medan** (`NOW_SHOWING` / `UPCOMING`), disertai akses cepat ke jadwal & katalog lengkap.
2. **Katalog Anime (`AnimeCatalogView.vue`):**
   - Baris pencarian & filter bersih (Tipe Media, Genre, Tahun, Urutan) + grid poster seragam `aspect-[2/3]` dengan badge indikator *"Tayang di Medan"* untuk anime yang terpetakan aktif + kontrol paginasi jelas.
3. **Detail Anime (`AnimeDetailView.vue`):**
   - Layout 2 kolom asimetris:
     - **Kolom Kiri:** Poster `aspect-[2/3]`, judul multi-bahasa, skor, rating usia, durasi, genre, studio, sinopsis, dan tombol aksi *"Simpan ke Watchlist"* (mengarahkan ke Login jika Guest).
     - **Kolom Kanan:** Panel jadwal bioskop Medan bergaya *ticket stub* (dikelompokkan per bioskop & tanggal, tipe studio, jam tayang, harga, sisa kursi, dan tombol langsung *"Pilih Kursi"*).
4. **Halaman Anime di Bioskop (`CinemaScheduleView.vue`):**
   - Tab filter **Sedang Tayang (`NOW_SHOWING`)** dan **Akan Tayang (`UPCOMING`)**, filter tanggal & jaringan bioskop Medan. Hanya menampilkan film anime.
5. **Pemilihan Kursi & Checkout (`SeatBookingView.vue`):**
   - Visual layar bioskop melengkung minimalis di atas, grid kursi proporsional dengan legenda kontras tinggi:
     - **Tersedia:** Outline `stone-300` di atas permukaan terang/gelap yang jelas.
     - **Dipilih:** *Crimson Curtain* (`#BE123C`) teks putih.
     - **Terisi:** `stone-300`/`stone-700` *disabled* dengan pola/ikon silang jelas (tidak hanya bergantung pada warna untuk aksesibilitas).
   - Panel ringkasan tiket (*sticky* di desktop, bottom bar di mobile): daftar kursi (`A1, A2`), jumlah tiket, total harga (`tabular-nums`), dan tombol *"Pesan Tiket"* (untuk Guest tampil banner *"Masuk untuk melanjutkan pemesanan"*).
6. **Tiket Saya (`MyTicketsView.vue`) & Watchlist (`MyWatchlistView.vue`):**
   - E-ticket bergaya sobekan tiket (*perforated border-dashed*) dengan `booking_code` monospasi (`JetBrains Mono`), status badge jelas (`PENDING` amber, `PAID` emerald, `CANCELLED` stone/rose), serta tombol bayar/batal.
   - Watchlist dilengkapi ringkasan statistik dari `v_user_stats` (jumlah *Watching, Completed, Mean Score, Episodes*) dan kontrol update progres episode langsung.
7. **Autentikasi (`LoginView.vue` & `RegisterView.vue`):**
   - Layout *editorial split* atau kartu terpusat rapi di atas latar *Warm Stone* dengan validasi per field yang jelas (HTTP `422`), tanpa gradien neon.
8. **Panel Admin (`AdminLayout.vue` & `views/admin/*`):**
   - Sidebar navigasi ramping (`#121316` atau `#1C1917`), area kerja `#FAF8F5` berdensitas tinggi, tabel data rapi dengan `tabular-nums`, diff JSON `old_data` vs `new_data` yang mudah dibaca pada **Audit Log Viewer**, serta status checksum/readable yang jelas pada **Panel Backup**.

---

## 3. Design Tokens Brand AniCine (Tailwind Theme)

- **Base Surface (Katalog & Panel Kerja):** *Warm Cinema Paper* (`#FAF8F5`)
- **Dark Theater Surface (Spotlight Hero & Sidebar Admin):** *Deep Theater Charcoal* (`#121316`) & Elevated (`#1A1C20`)
- **Elevated Card / Ticket Stub:** `#FFFFFF` dengan border halus `rgba(214, 211, 209, 0.85)` (`border-stone-200`)
- **Primary Brand (*"Ani"*):** *Crimson Curtain* (`#BE123C`, hover `#9F1239`, soft `#FFE4E6`)
- **Secondary Brand (*"Cine"*):** *Deep Slate Ink* (`#1E293B`) + *Projector Amber* (`#D97706`, soft `#FEF3C7`) untuk skor bintang & status *Pending*
- **Status Positive (Tersedia / Paid / Online / Verified):** *Emerald* (`#15803D`, soft `#DCFCE7`)
- **Text Hierarchy:** Heading `#1C1917`, Body `#44403C`, Muted `#78716C`
- **Tipografi:**
  - Display: `'Fraunces', Georgia, serif`
  - UI/Body: `'Plus Jakarta Sans', sans-serif` + wajib `tabular-nums` untuk skor, tahun, durasi, jam tayang, harga tiket, kursi, dan angka metrik
  - Monospace: `'JetBrains Mono', monospace` untuk `booking_code`, nama file `.dump`, dan inspeksi JSON `audit_log`

---

## 4. Checklist `Sloplint` (Wajib Lulus di Setiap Halaman Vue)

1. **[ ] Tidak ada dua tombol Primary (filled) bersebelahan**: Pasangkan 1 tombol Primary (`#BE123C`) dengan 1 Secondary/Outline atau Ghost.
2. **[ ] Tidak ada placeholder gradien kosong**: Gunakan `image_url`/`poster_path` nyata dengan komponen fallback poster sinema SVG yang rapi.
3. **[ ] Tidak ada spam Emoji sebagai pengganti ikon UI**: Gunakan ikon dari **`lucide-vue-next`** dengan `stroke-width="1.75"` secara konsisten.
4. **[ ] Kontras teks di atas poster terjamin**: Wajib dilapisi *gradient overlay* gelap atau pill solid.
5. **[ ] Copywriting bebas bahasa promosi AI**: Gunakan Bahasa Indonesia informatif yang merujuk langsung pada data katalog, jadwal Medan, kursi, dan status transaksi.
6. **[ ] Radius sudut konsisten**: `rounded-2xl` (kontainer utama), `rounded-xl` (poster/kartu dalam), `rounded-lg` (tombol/pill jadwal/kursi).
7. **[ ] Semua state interaktif & loading terdefinisi**: Sertakan `:hover`, `:focus-visible`, `:active`, `:disabled`, *skeleton loading*, dan *empty/error state* yang jelas.
8. **[ ] Alignment & Grid disiplin**: Poster selalu `aspect-[2/3]`, tinggi kartu dalam satu baris grid sejajar (`flex flex-col justify-between h-full`).
9. **[ ] Angka selalu menggunakan `tabular-nums`**: Skor, harga tiket (`Rp 75.000`), jam tayang (`14:00 WIB`), kapasitas (`60/60`), dan statistik admin.
10. **[ ] Responsif tanpa overflow horizontal**: Teruji rapi dari mobile (`375px`) hingga desktop (`1440px`), termasuk tabel admin (`overflow-x-auto`) dan peta kursi.
