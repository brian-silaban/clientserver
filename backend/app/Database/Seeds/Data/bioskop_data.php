<?php

use CodeIgniter\Database\RawSql;

/**
 * Dataset Fallback Bioskop Partner Satria (db_bioskop - schema public)
 *
 * Catatan:
 * - Data 14 genre, 6 studio Medan, 5 anime movie, relasi movie_genres (48..61), dan jadwal anime (21..26)
 *   sesuai persis dengan skema & `tambah_anime_ke_bioskop.sql` untuk integrasi `CinemaService::ANIME_MOVIE_MAP`.
 * - 20 judul film umum/Hollywood beserta jadwal (1..20) dan sampel booking (1..8) disiapkan sebagai
 *   data fallback lokal sambil menunggu REST API online dari Satria siap digunakan.
 */

$todaySql = new RawSql('CURRENT_DATE');

// Bangun kursi sampel A1..A10 per studio (60 kursi total agar mendukung relasi booking_seats)
$seats = [];
$seatId = 1;
for ($studioId = 1; $studioId <= 6; $studioId++) {
    for ($seatNumber = 1; $seatNumber <= 10; $seatNumber++) {
        $seats[] = [$seatId, $studioId, 'A' . $seatNumber, 'A', $seatNumber];
        $seatId++;
    }
}

// Bangun 8 transaksi booking sampel (masing-masing 2 kursi untuk showtime 1..8)
// Showtime anime 21..26 dibiarkan 0 booked agar seluruh kapasitas kursi tersedia saat demo
$bookings = [];
$bookingSeats = [];
$bookingSeatId = 1;
$studioPrices = [1 => 75000.00, 2 => 50000.00, 3 => 50000.00, 4 => 100000.00, 5 => 40000.00, 6 => 35000.00];

for ($bookingIndex = 1; $bookingIndex <= 8; $bookingIndex++) {
    $studioId = (($bookingIndex - 1) % 6) + 1;
    $ticketPrice = $studioPrices[$studioId];
    $bookings[] = [
        $bookingIndex,
        $bookingIndex, // showtime_id 1..8
        'Penonton Demo ' . $bookingIndex,
        'penonton' . $bookingIndex . '@anicine.local',
        number_format($ticketPrice * 2, 2, '.', ''),
        'PAID',
    ];

    $baseSeatId = (($studioId - 1) * 10) + 1;
    $bookingSeats[] = [$bookingSeatId++, $bookingIndex, $bookingIndex, $baseSeatId];
    $bookingSeats[] = [$bookingSeatId++, $bookingIndex, $bookingIndex, $baseSeatId + 1];
}

return [
    'genres' => [
        [12, 'Adventure', 'Film petualangan dan eksplorasi dunia'],
        [14, 'Fantasy', 'Film dunia fantasi, sihir, dan imajinasi'],
        [18, 'Drama', 'Film drama emosional dan konflik kehidupan'],
        [27, 'Horror', 'Film horor dan ketegangan supranatural'],
        [28, 'Action', 'Film aksi dan pertarungan intens'],
        [35, 'Comedy', 'Film komedi dan hiburan ringan'],
        [36, 'History', 'Film bertema sejarah dan kisah masa lampau'],
        [53, 'Thriller', 'Film ketegangan dan misteri kriminal'],
        [80, 'Crime', 'Film kejahatan dan investigasi'],
        [878, 'Science Fiction', 'Film fiksi ilmiah dan teknologi masa depan'],
        [9648, 'Mystery', 'Film teka-teki dan rahasia'],
        [10402, 'Music', 'Film musikal dan pertunjukan musik'],
        [10749, 'Romance', 'Film percintaan dan hubungan emosional'],
        [10751, 'Family', 'Film ramah keluarga segala usia'],
    ],

    'studios' => [
        [1, 'Delipark XXI & IMAX Medan', 'Medan Barat', 'Podomoro City Deli Medan Lt. 5, Jl. Putri Hijau No. 1', 'DELIPARK IMAX Studio 1', 'IMAX Laser 2D', 60, '75000.00'],
        [2, 'Delipark XXI & IMAX Medan', 'Medan Barat', 'Podomoro City Deli Medan Lt. 5, Jl. Putri Hijau No. 1', 'Delipark Regular Studio 2', 'Regular 2D', 45, '50000.00'],
        [3, 'Centre Point XXI Medan', 'Medan Timur', 'Mall Centre Point Lt. 3A, Jl. Jawa No. 8', 'Centre Point Studio 1', 'Regular 2D', 50, '50000.00'],
        [4, 'Sun Plaza Cinepolis Medan', 'Medan Polonia', 'Sun Plaza Lt. 4, Jl. KH. Zainul Arifin No. 7', 'Sun Plaza VIP Studio 1', 'Cinepolis VIP', 30, '100000.00'],
        [5, 'Manhattan XXI Medan', 'Medan Sunggal', 'Manhattan Times Square Lt. 3, Jl. Gatot Subroto No. 217', 'Manhattan Regular Studio 1', 'Regular 2D', 40, '40000.00'],
        [6, 'Ringroad City Walks XXI', 'Medan Sunggal', 'Ringroad City Walks Lt. 3, Jl. Gagak Hitam No. 28', 'Ringroad Regular Studio 1', 'Regular 2D', 40, '35000.00'],
    ],

    'movies' => [
        // 5 Anime Movie layar lebar (persis dengan tambah_anime_ke_bioskop.sql)
        [372058, 'Kimi no Na wa.', '君の名は。', '2016-08-26', 'ja', 106, 'R13+', '9.190', 8500, '250.5000', 'https://myanimelist.cdn-dena.com/images/anime/5/87048.jpg', '/7prYzufdIOy1KOKOnr4875708.jpg', 'Dua remaja, Mitsuha di desa terpencil dan Taki di Tokyo, secara misterius mulai bertukar tubuh saat tidur dan berusaha saling menemukan.'],
        [378064, 'Koe no Katachi', '聲の形', '2016-09-17', 'ja', 130, 'R13+', '9.040', 7200, '210.3000', 'https://myanimelist.cdn-dena.com/images/anime/3/80136.jpg', '/5k5nwhv8rK51qKOm06078.jpg', 'Shouya Ishida berusaha menebus kesalahannya di masa lalu kepada Shouko Nishimiya, gadis tunarungu yang pernah ia rundung saat sekolah dasar.'],
        [129, 'Sen to Chihiro no Kamikakushi', '千と千尋の神隠し', '2001-07-20', 'ja', 125, 'SU', '8.920', 15000, '310.8000', 'https://myanimelist.cdn-dena.com/images/anime/6/79597.jpg', '/mSDsSDwaP3E7cmMVA4.jpg', 'Chihiro, seorang gadis berusia 10 tahun, tanpa sengaja masuk ke dunia roh dan dewa setelah kedua orang tuanya dikutuk menjadi babi.'],
        [266396, 'Stand By Me Doraemon', 'STAND BY ME ドラえもん', '2014-08-08', 'ja', 95, 'SU', '8.130', 4300, '180.2000', 'https://myanimelist.cdn-dena.com/images/anime/9/65571.jpg', '/kKkP6u451Nf2kP.jpg', 'Kisah perpisahan Nobita dan robot kucing masa depan Doraemon saat Nobita berjuang mengubah masa depannya demi membahagiakan Shizuka.'],
        [376044, 'One Piece Film: Gold', 'ONE PIECE FILM GOLD', '2016-07-23', 'ja', 120, 'R13+', '8.140', 3900, '165.4000', 'https://myanimelist.cdn-dena.com/images/anime/12/81081.jpg', '/wF76hH5tGfK9.jpg', 'Luffy dan kru Topi Jerami tiba di Gran Tesoro, kapal hiburan kasino independen terbesar di dunia yang dikuasai oleh Gildo Tesoro.'],

        // 20 Film Bioskop Umum (Data fallback lokal database Satria)
        [1423191, 'Resident Evil', 'Resident Evil', '2026-09-18', 'en', 112, 'D17+', '7.100', 420, '195.4000', '/resident-evil-poster.jpg', '/resident-evil-backdrop.jpg', 'Adaptasi aksi horor terbaru tentang wabah biologis di fasilitas penelitian rahasia.'],
        [1368337, 'The Odyssey', 'The Odyssey', '2026-07-17', 'en', 158, 'R13+', '8.200', 980, '240.1000', '/the-odyssey-poster.jpg', '/the-odyssey-backdrop.jpg', 'Kisah epik perjalanan pulang Odysseus melintasi lautan penuh bahaya setelah Perang Troya.'],
        [1148365, 'Matchbox the Movie', 'Matchbox the Movie', '2026-06-12', 'en', 108, 'SU', '6.800', 310, '142.5000', '/matchbox-poster.jpg', '/matchbox-backdrop.jpg', 'Sekelompok sahabat masa kecil terseret ke dalam misi global ketika mainan ikonik mereka menjadi kunci rahasia besar.'],
        [1101412, 'Fall 2: Deadpoint', 'Fall 2: Deadpoint', '2026-08-07', 'en', 102, 'R13+', '6.900', 275, '138.2000', '/fall2-poster.jpg', '/fall2-backdrop.jpg', 'Dua pendaki terjebak di puncak menara komunikasi terpencil dengan persediaan menipis.'],
        [1377237, 'Runner', 'Runner', '2026-05-22', 'en', 106, 'D17+', '7.050', 240, '130.8000', '/runner-poster.jpg', '/runner-backdrop.jpg', 'Seorang kurir berkecepatan tinggi harus mengantarkan organ donor melintasi blokade sindikat kriminal.'],
        [1228834, 'The Fix', 'The Fix', '2026-04-17', 'en', 98, 'D17+', '6.400', 190, '118.6000', '/the-fix-poster.jpg', '/the-fix-backdrop.jpg', 'Di masa depan yang udaranya tercemar, seorang ilmuwan mencari obat penawar sebelum kota runtuh.'],
        [1236045, 'Animals', 'Animals', '2026-06-26', 'en', 114, 'D17+', '7.300', 350, '154.0000', '/animals-poster.jpg', '/animals-backdrop.jpg', 'Pasangan calon wali kota menghadapi penculikan anak mereka di tengah krisis politik besar.'],
        [1751701, 'After Impact', 'After Impact', '2026-05-08', 'en', 118, 'R13+', '6.750', 210, '125.3000', '/after-impact-poster.jpg', '/after-impact-backdrop.jpg', 'Para penyintas bencana meteor berjuang membangun kembali komunikasi antarwilayah.'],
        [1204680, 'Coyote vs. Acme', 'Coyote vs. Acme', '2026-07-03', 'en', 95, 'SU', '7.600', 620, '188.9000', '/coyote-acme-poster.jpg', '/coyote-acme-backdrop.jpg', 'Wile E. Coyote menggugat korporasi Acme ke pengadilan setelah seluruh produk jebakannya gagal.'],
        [1400837, 'Other Mommy', 'Other Mommy', '2026-10-02', 'en', 96, 'D17+', '6.650', 260, '129.4000', '/other-mommy-poster.jpg', '/other-mommy-backdrop.jpg', 'Seorang anak kecil mulai berkomunikasi dengan entitas misterius di balik lemari kamar barunya.'],
        [1232569, 'Pinocchio: Unstrung', 'Pinocchio: Unstrung', '2026-10-16', 'en', 94, 'D21+', '6.200', 180, '115.7000', '/pinocchio-unstrung-poster.jpg', '/pinocchio-unstrung-backdrop.jpg', 'Kisah kelam boneka kayu yang berusaha menjadi manusia dengan cara yang mengerikan.'],
        [1284046, 'Onslaught', 'Onslaught', '2026-08-21', 'en', 110, 'D17+', '7.200', 310, '149.2000', '/onslaught-poster.jpg', '/onslaught-backdrop.jpg', 'Seorang ibu dengan masa lalu militer berjuang menyelamatkan keluarganya dari pengepungan kelompok bersenjata.'],
        [1288445, 'Mutiny', 'Mutiny', '2026-06-19', 'en', 109, 'D17+', '7.150', 295, '146.5000', '/mutiny-poster.jpg', '/mutiny-backdrop.jpg', 'Mantan perwira pasukan khusus dijebak atas kematian bosnya di kapal kargo internasional.'],
        [1339713, 'Obsession', 'Obsession', '2026-05-15', 'en', 101, 'D17+', '6.850', 225, '133.1000', '/obsession-poster.jpg', '/obsession-backdrop.jpg', 'Keinginan tersembunyi berubah menjadi obsesi berbahaya saat rahasia masa lalu terungkap.'],
        [1280300, 'The Whistler', 'The Whistler', '2026-09-11', 'en', 115, 'R13+', '7.250', 340, '151.6000', '/the-whistler-poster.jpg', '/the-whistler-backdrop.jpg', 'Investigator menyelidiki skandal suap besar di balik kasus hukum yang tertutup rapat.'],
        [1248832, 'Digger', 'Digger', '2026-07-31', 'en', 124, 'R13+', '7.450', 410, '168.0000', '/digger-poster.jpg', '/digger-backdrop.jpg', 'Kisah drama berbalut ketegangan tentang penggalian rahasia di sebuah kota pertambangan.'],
        [1389172, 'रिपोर्टिंग लाइव', 'Reporting Live', '2026-04-24', 'hi', 122, 'R13+', '7.100', 205, '121.8000', '/reporting-live-poster.jpg', '/reporting-live-backdrop.jpg', 'Jurnalis lapangan berpacu dengan waktu untuk menyiarkan kebenaran di tengah tekanan media.'],
        [977942, 'The Uprising', 'The Uprising', '2026-09-25', 'en', 132, 'D17+', '7.350', 370, '159.4000', '/the-uprising-poster.jpg', '/the-uprising-backdrop.jpg', 'Pemberontakan rakyat pada abad ke-14 menentang pajak kerajaan yang menindas.'],
        [1742441, 'V/H/S/Mixtape', 'V/H/S/Mixtape', '2026-10-09', 'en', 104, 'D21+', '6.700', 280, '135.9000', '/vhs-mixtape-poster.jpg', '/vhs-mixtape-backdrop.jpg', 'Antologi rekaman found-footage penuh teror dari berbagai kaset misterius yang baru ditemukan.'],
        [1032863, 'Hipotesis Cinta', 'Hipotesis Cinta', '2026-08-14', 'id', 105, 'R13+', '7.500', 490, '172.3000', '/hipotesis-cinta-poster.jpg', '/hipotesis-cinta-backdrop.jpg', 'Dua peneliti muda membuat kesepakatan kencan pura-pura yang perlahan berubah menjadi perasaan nyata.'],
    ],

    'movie_genres' => [
        // Film Umum (ID 1..47)
        [1, 1423191, 28], [2, 1423191, 27], [3, 1423191, 878],
        [4, 1368337, 12], [5, 1368337, 18], [6, 1368337, 14], [7, 1368337, 36],
        [8, 1148365, 28], [9, 1148365, 12], [10, 1148365, 10751],
        [11, 1101412, 53], [12, 1101412, 18],
        [13, 1377237, 28], [14, 1377237, 53],
        [15, 1228834, 878], [16, 1228834, 53],
        [17, 1236045, 80], [18, 1236045, 18], [19, 1236045, 53],
        [20, 1751701, 28], [21, 1751701, 878],
        [22, 1204680, 35], [23, 1204680, 10751], [24, 1204680, 12],
        [25, 1400837, 27], [26, 1400837, 9648],
        [27, 1232569, 27], [28, 1232569, 14],
        [29, 1284046, 28], [30, 1284046, 53],
        [31, 1288445, 28], [32, 1288445, 53],
        [33, 1339713, 53], [34, 1339713, 27],
        [35, 1280300, 80], [36, 1280300, 9648],
        [37, 1248832, 18], [38, 1248832, 53],
        [39, 1389172, 18], [40, 1389172, 53],
        [41, 977942, 36], [42, 977942, 28], [43, 977942, 18],
        [44, 1742441, 27], [45, 1742441, 9648],
        [46, 1032863, 10749], [47, 1032863, 35],

        // 5 Anime Movie (ID 48..61 - persis dengan tambah_anime_ke_bioskop.sql)
        [48, 372058, 10749], [49, 372058, 14], [50, 372058, 18],
        [51, 378064, 18], [52, 378064, 10749],
        [53, 129, 14], [54, 129, 12], [55, 129, 10751],
        [56, 266396, 10751], [57, 266396, 14], [58, 266396, 35],
        [59, 376044, 28], [60, 376044, 12], [61, 376044, 14],
    ],

    'showtimes' => [
        // Jadwal Film Umum (showtime_id 1..20)
        [1, 1423191, 1, $todaySql, '13:00:00', '75000.00'],
        [2, 1368337, 2, $todaySql, '14:15:00', '50000.00'],
        [3, 1148365, 3, $todaySql, '15:30:00', '50000.00'],
        [4, 1101412, 4, $todaySql, '16:45:00', '100000.00'],
        [5, 1377237, 5, $todaySql, '17:00:00', '40000.00'],
        [6, 1228834, 6, $todaySql, '18:00:00', '35000.00'],
        [7, 1236045, 1, $todaySql, '19:00:00', '75000.00'],
        [8, 1751701, 2, $todaySql, '19:30:00', '50000.00'],
        [9, 1204680, 3, $todaySql, '12:30:00', '50000.00'],
        [10, 1400837, 4, $todaySql, '20:15:00', '100000.00'],
        [11, 1232569, 5, $todaySql, '21:00:00', '40000.00'],
        [12, 1284046, 6, $todaySql, '14:45:00', '35000.00'],
        [13, 1288445, 1, $todaySql, '16:15:00', '75000.00'],
        [14, 1339713, 2, $todaySql, '20:45:00', '50000.00'],
        [15, 1280300, 3, $todaySql, '18:30:00', '50000.00'],
        [16, 1248832, 4, $todaySql, '13:30:00', '100000.00'],
        [17, 1389172, 5, $todaySql, '15:00:00', '40000.00'],
        [18, 977942, 6, $todaySql, '16:30:00', '35000.00'],
        [19, 1742441, 1, $todaySql, '21:30:00', '75000.00'],
        [20, 1032863, 2, $todaySql, '12:00:00', '50000.00'],

        // Jadwal 5 Anime Movie di Bioskop Medan (showtime_id 21..26 - persis dengan tambah_anime_ke_bioskop.sql)
        [21, 372058, 1, $todaySql, '14:00:00', '75000.00'],
        [22, 372058, 2, $todaySql, '17:30:00', '50000.00'],
        [23, 378064, 3, $todaySql, '14:30:00', '50000.00'],
        [24, 129, 4, $todaySql, '16:00:00', '100000.00'],
        [25, 266396, 5, $todaySql, '13:00:00', '40000.00'],
        [26, 376044, 6, $todaySql, '19:00:00', '35000.00'],
    ],

    'seats'         => $seats,
    'bookings'      => $bookings,
    'booking_seats' => $bookingSeats,
];
