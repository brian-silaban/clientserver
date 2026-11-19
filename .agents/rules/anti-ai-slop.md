# 🛡️ Ultimate Anti-AI-Slop UI/UX Ruleset (AniCine: Anime & Cinema Web Edition)

> **Catatan Konteks Projek:** Saat ini **AniCine** berjalan dengan fokus *Pure RESTful API Service & Server-to-Server Data Mashup*. Ruleset ini berlaku sebagai **standar wajib apabila pada pengembangan selanjutnya dibuat halaman antarmuka web (Frontend Katalog Anime, Jadwal Bioskop Medan, atau Dashboard Pengujian API)** di dalam `app/Views/**` dan `public/css/**`.

Ruleset ini menggabungkan standar dari 4 repositori Anti-AI-Slop terbaik:
1. **`anthropics/skills` (`frontend-design`)** — *Aesthetic Direction & Subject-Matter Grounding*
2. **`pbakaus/impeccable`** — *The 5 Hallmark AI-Slop Anti-Patterns*
3. **`Nutlope/hallmark` (`sloplint`)** — *Macrostructures & Quality Gate Rules*
4. **`ui-ux-pro-max`** — *Intentional Design Tokens & Information Density*

---

## 1. The 5 Hallmark "AI Slop" Anti-Patterns (DILARANG KERAS)
*(Diadaptasi dari `pbakaus/impeccable`)*

AI yang dibiarkan tanpa aturan akan selalu jatuh ke probabilitas statistik yang sama. **Dilarang keras menghasilkan 5 pola ini:**

1. **🚫 Inter / Default Sans-Serif di Semua Elemen (`Overused Font Trap`)**
   - Jangan gunakan `Inter`, `Roboto`, atau `Arial` sekaligus untuk Heading, Judul Film, Body, dan Label Angka tanpa kontras karakter tipografi.
2. **🚫 Gradien Ungu-ke-Biru / Neon SaaS (`Purple-to-Blue Gradient Cliché`)**
   - Dilarang keras memakai `bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500` pada teks judul, tombol utama, atau latar belakang hero. Katalog anime dan jadwal bioskop bukan aplikasi crypto atau B2B AI SaaS.
3. **🚫 Kartu di Dalam Kartu di Dalam Kartu (`Endless Nested Cards`)**
   - Hindari membungkus setiap detail anime dan setiap jam tayang dengan `border rounded-xl p-6 shadow-md bg-white` berlapis-lapis. Gunakan *whitespace*, garis pembatas tiket (`divide-y divide-stone-200` atau `border-dashed`), dan pengelompokan per bioskop yang rapi.
4. **🚫 Teks Abu-Abu Pudar & Kontras Rendah di Atas Poster (`Low-Contrast Typography`)**
   - Jangan menaruh `text-gray-400` di atas latar terang, atau teks putih telanjang di atas poster anime tanpa *scrim/gradient overlay* gelap (`bg-gradient-to-t from-black/85 via-black/35 to-transparent`).
5. **🚫 Grid 3-Kolom Ikon Kotak Bulat (`The Mechanical Icon Tile Grid`)**
   - Dilarang membuat section berisi 3 kartu identik yang masing-masing diawali kotak kecil `w-12 h-12 rounded-lg` berisi ikon acak + judul 2 kata + kalimat promosi generik.

---

## 2. Macrostructure & Layout Rules (Khusus Katalog Anime & Jadwal Bioskop)
*(Diadaptasi dari `Nutlope/hallmark` & `anthropics/frontend-design`)*

Jangan gunakan urutan kerangka halaman template SaaS (*Centered Hero $\rightarrow$ 3-Card Feature Grid $\rightarrow$ Testimonial $\rightarrow$ CTA Banner*). Gunakan struktur yang sesuai dengan dunia **Katalog Sinema & Eksplorasi Anime** (seperti *Letterboxd*, *TMDB*, *Criterion*, atau *TIX ID*):

- **Theatrical Spotlight & Interactive Filter Hero**:
  - Buka halaman dengan highlight film anime layar lebar yang sedang tayang di bioskop Medan (*Theatrical Anime Spotlight* seperti *Kimi no Na wa.* atau *Sen to Chihiro no Kamikakushi*), langsung disertai **Search & Filter Bar** fungsional (Pencarian Judul `?q=`, Filter Tahun Rilis `/tahun/{tahun}`, dan Toggle *"Sedang Tayang di Bioskop Medan"* `/theatrical`).
- **Asymmetric / Bento Catalog & Now-Showing Showcase**:
  - Bedakan hierarki visual antara katalog anime reguler (*TV Series*) dengan 5 *Anime Movie* yang memiliki jadwal tayang aktif di bioskop Medan (`is_theatrical: true`).
- **Editorial Detail & Cinema Schedule Split Layout**:
  - Untuk halaman detail anime (`/api/anime/{id}`), gunakan struktur 2 kolom asimetris:
    - **Kolom Kiri (Info Katalog `db_anitrack`)**: Poster proporsional (`aspect-[2/3]`), judul utama (`title`), judul bahasa Inggris & Jepang (`title_japanese`), skor (`score`), rating usia (`rating_code`), durasi (`duration_min`), daftar genre, dan studio produksi (`studios`).
    - **Kolom Kanan (Panel Jadwal Bioskop Medan `cinema_showtimes` dari `db_bioskop`)**: Kartu jadwal bergaya tiket sinema yang mengelompokkan penayangan berdasarkan lokasi bioskop di Medan (*Delipark XXI, Sun Plaza Cinepolis, Centre Point XXI, Manhattan XXI, Ringroad*), lengkap dengan tipe studio (`IMAX Laser 2D` / `Regular 2D`), pill jam tayang (`14:00`, `17:30`), harga tiket (`Rp 75.000`), dan indikator sisa kursi (`seats_available / total_capacity`).

---

## 3. Aesthetic Direction & Design Tokens (Brand AniCine)
*(Diadaptasi dari `anthropics/frontend-design` & `ui-ux-pro-max`)*

### A. Tone & Persona Visual: *Editorial Cinema & Curated Anime Archive*
- **Base Surface (Background)**:
  - Gunakan *Warm Cinema Paper / Stone Off-White* (`#FAF8F5` atau `bg-stone-50`) untuk area baca katalog, dan *Deep Theater Charcoal* (`#121316` atau `bg-stone-950`) khusus untuk banner *Theatrical Spotlight*.
- **Elevated Surface (Card / Ticket Stub)**:
  - `#FFFFFF` dengan border halus (`border-stone-200/80`) dan aksen garis putus-putus (`border-dashed`) khas potongan tiket bioskop pada bagian jadwal tayang.
- **Primary Brand 1 (Kata *"Ani"*)**:
  - *Crimson Curtain / Torii Red* (`#BE123C` / `rose-700`) untuk identitas utama dan badge *Theatrical Movie*.
- **Primary Brand 2 (Kata *"Cine"*)**:
  - *Deep Slate Ink* (`#1E293B` / `slate-800`) dipadukan dengan *Projector Amber* (`#D97706` / `amber-600`) untuk rating bintang/skor anime (`★ 9.19`) dan sorotan jadwal bioskop.
- **Status Indicator Colors**:
  - Kursi Tersedia / Koneksi Online (`EXTERNAL_API` / `LOCAL_FALLBACK`): *Emerald Green* (`#15803D` / `green-700`).
- **Text Hierarchy**:
  - Heading: `#1C1917` (`stone-900`)
  - Body Copy: `#44403C` (`stone-700`)
  - Metadata / Caption / Japanese Subtitle: `#78716C` (`stone-500`)

### B. Tipografi Berkarakter (Font Pairing)
1. **Display / Judul Anime (`h1`–`h3`)**:
   - `'Playfair Display'`, `'Fraunces'`, atau `'Plus Jakarta Sans'` (`font-bold`, `tracking-tight`, `leading-[1.15]`).
2. **UI / Body / Metadata Jadwal**:
   - `'Plus Jakarta Sans'` atau `'DM Sans'`, wajib mengaktifkan `tabular-nums` untuk seluruh angka: skor (`9.19`), tahun rilis (`2016`), durasi (`106 min`), jam tayang (`14:00:00`), harga tiket (`Rp 75.000`), dan sisa kursi (`60/60`).

---

## 4. Kepadatan Informasi Nyata (`High Information Density`)
Salah satu ciri paling mencolok dari UI buatan AI adalah **isi kartu yang kosong dan mengambang** (hanya judul + kalimat klise *"Tonton anime seru ini di bioskop kesayangan Anda"*).

**Setiap komponen Katalog Anime & Jadwal Bioskop WAJIB memuat metadata nyata dari API AniCine:**
- **Visual Poster**: Poster anime dengan rasio poster film standar (`aspect-[2/3]`), efek transisi halus saat hover (`group-hover:scale-105 transition-transform duration-500 ease-out`).
- **Metadata Katalog (`db_anitrack`)**:
  - Badge Tipe & Rating Usia: contoh `Movie` • `PG-13`.
  - Judul Multi-bahasa: `Kimi no Na wa.` (`Your Name.` / `君の名は。`).
  - Skor & Studio: `★ 9.19` • `CoMix Wave Films`.
  - Genre: `Drama, Romance, School, Supernatural`.
- **Metadata Jadwal Bioskop Medan (`db_bioskop` / `cinema_showtimes`)**:
  - Nama Bioskop & Kecamatan: contoh `Delipark XXI & IMAX Medan (Medan Barat)`.
  - Nama & Tipe Studio: contoh `DELIPARK IMAX Studio 1` (`IMAX Laser 2D`).
  - Tanggal & Jam Tayang: contoh `2026-10-15` • `14:00 WIB` & `17:30 WIB`.
  - Harga Tiket & Ketersediaan Kursi: contoh `Rp 75.000` • `Tersedia 60 dari 60 kursi`.

---

## 5. Checklist `Sloplint` (Wajib Diperiksa Sebelum Menulis Kode UI)
*(Diadaptasi dari `Nutlope/hallmark` `sloplint.mjs`)*

Jika membuat atau memodifikasi file View (`app/Views/...`), pastikan 10 poin ini **LULUS**:
1. **[ ] Tidak ada dua tombol Primary (filled) bersebelahan**: Jika ada 2 tombol aksi, satu harus *Primary* (solid fill) dan satu lagi *Secondary/Ghost* (outline atau teks).
2. **[ ] Tidak ada placeholder gradien kosong**: Gunakan `image_url` atau `poster_path` nyata dari database, lengkap dengan fallback poster sinema yang rapi jika URL gambar kosong.
3. **[ ] Tidak ada spam Emoji sebagai pengganti ikon UI**: Jangan menaruh emoji (🚀, ✨, 🔥, 🌟) di judul section atau navigasi. Gunakan SVG inline bersih (Lucide / Heroicons dengan `stroke-width="1.75"`).
4. **[ ] Kontras teks di atas poster terjamin**: Setiap badge atau teks di atas poster wajib dilapisi gradient overlay gelap (`from-black/80 via-black/30 to-transparent`) atau berada di dalam pill solid.
5. **[ ] Copywriting bebas bahasa promosi AI**: Hapus kata-kata klise seperti *"Platform revolusioner"*, *"Pengalaman menonton tak terlupakan"*, *"Solusi terdepan"*. Gunakan bahasa informatif yang merujuk langsung pada data katalog anime dan jadwal bioskop Medan.
6. **[ ] Radius sudut konsisten**: Gunakan skala konsisten (misal: `rounded-2xl` untuk kontainer kartu luar, `rounded-xl` untuk poster/elemen dalam, `rounded-lg` untuk tombol/pill jam tayang).
7. **[ ] Semua state interaktif terdefinisi**: Setiap tombol, filter, pill jam tayang, dan kartu anime wajib memiliki state `:hover`, `:focus-visible`, dan `:active` yang jelas.
8. **[ ] Alignment & Grid disiplin**: Poster anime memiliki rasio seragam (`aspect-[2/3]`), dan tinggi kartu dalam satu baris grid sejajar (`flex flex-col justify-between h-full`).
9. **[ ] Angka menggunakan `tabular-nums`**: Skor (`9.19`), harga tiket (`Rp 75.000`), jam tayang (`14:00`), dan kapasitas kursi (`60/60`) menggunakan `tabular-nums` agar rapi saat dipindai mata.
10. **[ ] Responsif tanpa overflow horizontal**: Pastikan layout di layar mobile (`375px`) tidak bocor ke samping dan grid menyusut dengan proporsional.
