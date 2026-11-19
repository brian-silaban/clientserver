<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;

/**
 * Seeder Database Bioskop Partner Satria (db_bioskop - schema public)
 *
 * Mengisi data genre, 6 studio Medan, 5 anime movie (`ANIME_MOVIE_MAP`), 20 film umum
 * fallback, relasi genre film, jadwal tayang bioskop Medan (`showtimes`), serta
 * kursi dan sampel transaksi booking.
 */
class BioskopSeeder extends BasePostgresSeeder
{
    /**
     * Grup koneksi database target pada app/Config/Database.php.
     *
     * @var non-empty-string
     */
    protected $DBGroup = 'bioskop';

    /**
     * Menjalankan proses pengisian data db_bioskop secara berurutan dan idempoten.
     */
    public function run(): void
    {
        $dataset = require __DIR__ . '/Data/bioskop_data.php';

        if (is_cli() && ! $this->silent) {
            CLI::write('[BioskopSeeder] Mengisi database db_bioskop (schema public)...', 'yellow');
        }

        $this->db->transException(true)->transStart();

        // 1. Master genre dan studio bioskop Medan
        $insertedGenres = $this->insertBatchIgnoreConflict(
            'public.genres',
            ['genre_id', 'genre_name', 'genre_description'],
            $dataset['genres']
        );
        $this->logSeedSummary('public.genres', count($dataset['genres']), $insertedGenres);

        $insertedStudios = $this->insertBatchIgnoreConflict(
            'public.studios',
            ['studio_id', 'cinema_name', 'kecamatan', 'address', 'studio_name', 'studio_type', 'capacity', 'base_price'],
            $dataset['studios']
        );
        $this->logSeedSummary('public.studios', count($dataset['studios']), $insertedStudios);

        // 2. Film bioskop (20 film umum fallback + 5 anime movie layar lebar)
        $movieColumns = [
            'id', 'title', 'original_title', 'release_date', 'original_language',
            'duration_minutes', 'age_rating', 'vote_average', 'vote_count',
            'popularity', 'poster_path', 'backdrop_path', 'overview',
        ];
        $insertedMovies = $this->insertBatchIgnoreConflict(
            'public.movies',
            $movieColumns,
            $dataset['movies'],
            [],
            25
        );
        $this->logSeedSummary('public.movies', count($dataset['movies']), $insertedMovies);

        // 3. Relasi film ke genre (agar tampil di view v_movies_lengkap_3nf)
        $insertedMovieGenres = $this->insertBatchIgnoreConflict(
            'public.movie_genres',
            ['movie_genre_id', 'movie_id', 'genre_id'],
            $dataset['movie_genres']
        );
        $this->logSeedSummary('public.movie_genres', count($dataset['movie_genres']), $insertedMovieGenres);

        // 4. Kursi studio dan jadwal tayang bioskop Medan
        $insertedSeats = $this->insertBatchIgnoreConflict(
            'public.seats',
            ['seat_id', 'studio_id', 'seat_code', 'row_label', 'seat_number'],
            $dataset['seats']
        );
        $this->logSeedSummary('public.seats', count($dataset['seats']), $insertedSeats);

        $insertedShowtimes = $this->insertBatchIgnoreConflict(
            'public.showtimes',
            ['showtime_id', 'movie_id', 'studio_id', 'show_date', 'start_time', 'ticket_price'],
            $dataset['showtimes']
        );
        $this->logSeedSummary('public.showtimes', count($dataset['showtimes']), $insertedShowtimes);

        // 5. Sampel booking agar perhitungan seats_booked / seats_available pada jadwal berjalan akurat
        $insertedBookings = $this->insertBatchIgnoreConflict(
            'public.bookings',
            ['booking_id', 'showtime_id', 'customer_name', 'customer_email', 'total_price', 'payment_status'],
            $dataset['bookings']
        );
        $this->logSeedSummary('public.bookings', count($dataset['bookings']), $insertedBookings);

        $insertedBookingSeats = $this->insertBatchIgnoreConflict(
            'public.booking_seats',
            ['booking_seat_id', 'booking_id', 'showtime_id', 'seat_id'],
            $dataset['booking_seats']
        );
        $this->logSeedSummary('public.booking_seats', count($dataset['booking_seats']), $insertedBookingSeats);

        // 6. Sinkronisasi seluruh serial sequence di db_bioskop
        $this->syncSerialSequence('public.genres', 'genre_id', 10751);
        $this->syncSerialSequence('public.studios', 'studio_id', 6);
        $this->syncSerialSequence('public.movie_genres', 'movie_genre_id', 61);
        $this->syncSerialSequence('public.seats', 'seat_id', 60);
        $this->syncSerialSequence('public.showtimes', 'showtime_id', 26);
        $this->syncSerialSequence('public.bookings', 'booking_id', 8);
        $this->syncSerialSequence('public.booking_seats', 'booking_seat_id', 16);

        $this->db->transComplete();
    }
}
