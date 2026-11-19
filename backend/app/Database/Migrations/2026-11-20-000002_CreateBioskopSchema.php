<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migrasi Skema Database Bioskop Partner Satria (db_bioskop)
 *
 * Membuat seluruh 8 tabel pada schema `public` (`genres`, `movies`, `movie_genres`,
 * `studios`, `seats`, `showtimes`, `bookings`, `booking_seats`) serta 2 view
 * (`v_movies_lengkap_3nf` dan `v_jadwal_bioskop_medan`) yang dikonsumsi oleh
 * `App\Libraries\CinemaService` saat mode `LOCAL_FALLBACK`.
 */
class CreateBioskopSchema extends Migration
{
    /**
     * Grup koneksi target pada app/Config/Database.php (menggunakan grup bioskop -> db_bioskop).
     *
     * @var string
     */
    protected $DBGroup = 'bioskop';

    /**
     * Membuat tabel, constraint, index, dan view pada database db_bioskop.
     */
    public function up(): void
    {
        $this->db->query('SET search_path TO public;');

        $this->createMasterTables();
        $this->createScheduleAndBookingTables();
        $this->createIndexesAndViews();
    }

    /**
     * Menghapus seluruh view dan tabel pada database db_bioskop (rollback).
     */
    public function down(): void
    {
        $this->db->query('DROP VIEW IF EXISTS public.v_jadwal_bioskop_medan;');
        $this->db->query('DROP VIEW IF EXISTS public.v_movies_lengkap_3nf;');

        $tablesInReverseOrder = [
            'public.booking_seats',
            'public.bookings',
            'public.showtimes',
            'public.seats',
            'public.movie_genres',
            'public.studios',
            'public.movies',
            'public.genres',
        ];

        foreach ($tablesInReverseOrder as $tableName) {
            $this->db->query("DROP TABLE IF EXISTS {$tableName} CASCADE;");
        }
    }

    /**
     * Membuat tabel master: genres, movies, studios, dan tabel penghubung movie_genres.
     */
    private function createMasterTables(): void
    {
        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS public.genres (
                genre_id serial NOT NULL,
                genre_name character varying(50) NOT NULL,
                genre_description character varying(255),
                CONSTRAINT genres_pkey PRIMARY KEY (genre_id),
                CONSTRAINT genres_genre_name_key UNIQUE (genre_name)
            );
        SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS public.movies (
                id bigint NOT NULL,
                title character varying(255) NOT NULL,
                original_title character varying(255),
                release_date date,
                original_language character varying(10),
                duration_minutes integer,
                age_rating character varying(10),
                vote_average numeric(5,3) DEFAULT 0,
                vote_count integer DEFAULT 0,
                popularity numeric(10,4) DEFAULT 0,
                poster_path character varying(255),
                backdrop_path character varying(255),
                overview text,
                CONSTRAINT movies_pkey PRIMARY KEY (id),
                CONSTRAINT movies_duration_minutes_check CHECK ((duration_minutes > 0)),
                CONSTRAINT movies_age_rating_check CHECK ((age_rating IN ('SU', 'R13+', 'D17+', 'D21+'))),
                CONSTRAINT movies_vote_average_check CHECK ((vote_average >= 0 AND vote_average <= 10)),
                CONSTRAINT movies_vote_count_check CHECK ((vote_count >= 0)),
                CONSTRAINT movies_popularity_check CHECK ((popularity >= 0))
            );
        SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS public.studios (
                studio_id serial NOT NULL,
                cinema_name character varying(100) NOT NULL,
                kecamatan character varying(50) NOT NULL,
                address text,
                studio_name character varying(60) NOT NULL,
                studio_type character varying(30) NOT NULL,
                capacity integer NOT NULL,
                base_price numeric(10,2) NOT NULL,
                CONSTRAINT studios_pkey PRIMARY KEY (studio_id),
                CONSTRAINT studios_studio_name_key UNIQUE (studio_name),
                CONSTRAINT studios_capacity_check CHECK ((capacity > 0)),
                CONSTRAINT studios_base_price_check CHECK ((base_price >= 0))
            );
        SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS public.movie_genres (
                movie_genre_id serial NOT NULL,
                movie_id bigint NOT NULL,
                genre_id integer NOT NULL,
                CONSTRAINT movie_genres_pkey PRIMARY KEY (movie_genre_id),
                CONSTRAINT uq_movie_genres UNIQUE (movie_id, genre_id),
                CONSTRAINT movie_genres_movie_id_fkey FOREIGN KEY (movie_id) REFERENCES public.movies(id) ON DELETE CASCADE,
                CONSTRAINT movie_genres_genre_id_fkey FOREIGN KEY (genre_id) REFERENCES public.genres(genre_id) ON DELETE CASCADE
            );
        SQL);
    }

    /**
     * Membuat tabel jadwal tayang, kursi studio, dan catatan booking kursi.
     */
    private function createScheduleAndBookingTables(): void
    {
        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS public.seats (
                seat_id serial NOT NULL,
                studio_id integer NOT NULL,
                seat_code character varying(5) NOT NULL,
                row_label character(1) NOT NULL,
                seat_number integer NOT NULL,
                CONSTRAINT seats_pkey PRIMARY KEY (seat_id),
                CONSTRAINT uq_seats_studio_code UNIQUE (studio_id, seat_code),
                CONSTRAINT seats_studio_id_fkey FOREIGN KEY (studio_id) REFERENCES public.studios(studio_id) ON DELETE CASCADE,
                CONSTRAINT seats_seat_number_check CHECK ((seat_number > 0))
            );
        SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS public.showtimes (
                showtime_id serial NOT NULL,
                movie_id bigint NOT NULL,
                studio_id integer NOT NULL,
                show_date date NOT NULL,
                start_time time without time zone NOT NULL,
                ticket_price numeric(10,2) NOT NULL,
                CONSTRAINT showtimes_pkey PRIMARY KEY (showtime_id),
                CONSTRAINT showtimes_movie_id_fkey FOREIGN KEY (movie_id) REFERENCES public.movies(id) ON DELETE CASCADE,
                CONSTRAINT showtimes_studio_id_fkey FOREIGN KEY (studio_id) REFERENCES public.studios(studio_id) ON DELETE CASCADE,
                CONSTRAINT showtimes_ticket_price_check CHECK ((ticket_price >= 0))
            );
        SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS public.bookings (
                booking_id serial NOT NULL,
                showtime_id integer NOT NULL,
                customer_name character varying(100) NOT NULL,
                customer_email character varying(100) NOT NULL,
                booking_time timestamp without time zone DEFAULT now() NOT NULL,
                total_price numeric(12,2) NOT NULL,
                payment_status character varying(20) DEFAULT 'PENDING'::character varying NOT NULL,
                CONSTRAINT bookings_pkey PRIMARY KEY (booking_id),
                CONSTRAINT bookings_showtime_id_fkey FOREIGN KEY (showtime_id) REFERENCES public.showtimes(showtime_id) ON DELETE CASCADE,
                CONSTRAINT bookings_total_price_check CHECK ((total_price >= 0)),
                CONSTRAINT bookings_payment_status_check CHECK ((payment_status IN ('PENDING', 'PAID', 'CANCELLED')))
            );
        SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS public.booking_seats (
                booking_seat_id serial NOT NULL,
                booking_id integer NOT NULL,
                showtime_id integer NOT NULL,
                seat_id integer NOT NULL,
                CONSTRAINT booking_seats_pkey PRIMARY KEY (booking_seat_id),
                CONSTRAINT uq_booking_seats_showtime_seat UNIQUE (showtime_id, seat_id),
                CONSTRAINT booking_seats_booking_id_fkey FOREIGN KEY (booking_id) REFERENCES public.bookings(booking_id) ON DELETE CASCADE,
                CONSTRAINT booking_seats_showtime_id_fkey FOREIGN KEY (showtime_id) REFERENCES public.showtimes(showtime_id) ON DELETE CASCADE,
                CONSTRAINT booking_seats_seat_id_fkey FOREIGN KEY (seat_id) REFERENCES public.seats(seat_id) ON DELETE CASCADE
            );
        SQL);
    }

    /**
     * Membuat index query bioskop dan 2 view yang dipakai langsung oleh CinemaService.
     */
    private function createIndexesAndViews(): void
    {
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_showtimes_movie ON public.showtimes USING btree (movie_id);');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_showtimes_date ON public.showtimes USING btree (show_date, start_time);');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_booking_seats_showtime ON public.booking_seats USING btree (showtime_id);');

        $this->db->query(<<<'SQL'
            CREATE OR REPLACE VIEW public.v_movies_lengkap_3nf AS
            SELECT
                m.id AS movie_id,
                m.title,
                m.original_title,
                m.release_date,
                m.original_language,
                m.duration_minutes,
                m.age_rating,
                m.vote_average,
                m.vote_count,
                m.popularity,
                m.poster_path,
                m.backdrop_path,
                m.overview,
                COALESCE(STRING_AGG(g.genre_name, ', ' ORDER BY g.genre_name), '') AS genre_names
            FROM public.movies m
            LEFT JOIN public.movie_genres mg ON mg.movie_id = m.id
            LEFT JOIN public.genres g ON g.genre_id = mg.genre_id
            GROUP BY
                m.id, m.title, m.original_title, m.release_date,
                m.original_language, m.duration_minutes, m.age_rating,
                m.vote_average, m.vote_count, m.popularity,
                m.poster_path, m.backdrop_path, m.overview;
        SQL);

        $this->db->query(<<<'SQL'
            CREATE OR REPLACE VIEW public.v_jadwal_bioskop_medan AS
            SELECT
                sh.showtime_id,
                sh.movie_id,
                m.title AS movie_title,
                m.age_rating,
                m.duration_minutes,
                m.poster_path,
                st.studio_id,
                st.cinema_name,
                st.kecamatan,
                st.address,
                st.studio_name,
                st.studio_type,
                sh.show_date,
                sh.start_time,
                sh.ticket_price,
                st.capacity AS total_capacity,
                COUNT(bs.booking_seat_id) AS seats_booked,
                (st.capacity - COUNT(bs.booking_seat_id)) AS seats_available
            FROM public.showtimes sh
            JOIN public.movies m ON m.id = sh.movie_id
            JOIN public.studios st ON st.studio_id = sh.studio_id
            LEFT JOIN public.booking_seats bs ON bs.showtime_id = sh.showtime_id
            GROUP BY
                sh.showtime_id, sh.movie_id, m.title, m.age_rating,
                m.duration_minutes, m.poster_path, st.studio_id,
                st.cinema_name, st.kecamatan, st.address,
                st.studio_name, st.studio_type, sh.show_date,
                sh.start_time, sh.ticket_price, st.capacity;
        SQL);
    }
}
