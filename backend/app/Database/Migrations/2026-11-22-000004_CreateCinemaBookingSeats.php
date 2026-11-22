<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migrasi Tabel Penguncian Kursi Sisi AniCine (`anitrack.cinema_booking_seats`)
 *
 * Menyimpan relasi kursi yang sedang terkunci oleh transaksi aktif AniCine
 * langsung di database milik sendiri (`db_anitrack`) dengan constraint
 * `UNIQUE (showtime_id, seat_id)` untuk mencegah double-booking secara atomik
 * tanpa perlu melakukan penulisan/modifikasi ke database `db_bioskop` milik partner.
 */
class CreateCinemaBookingSeats extends Migration
{
    /**
     * Grup koneksi target (default -> db_anitrack).
     *
     * @var string
     */
    protected $DBGroup = 'default';

    /**
     * Membuat tabel anitrack.cinema_booking_seats, index, dan trigger audit.
     */
    public function up(): void
    {
        $this->db->query('SET search_path TO anitrack, public;');

        $this->db->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS anitrack.cinema_booking_seats (
                booking_seat_id serial PRIMARY KEY,
                booking_id integer NOT NULL,
                showtime_id integer NOT NULL,
                seat_id integer NOT NULL,
                seat_code character varying(10) NOT NULL,
                created_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT uq_cbs_showtime_seat UNIQUE (showtime_id, seat_id),
                CONSTRAINT cbs_booking_id_fkey FOREIGN KEY (booking_id)
                    REFERENCES anitrack.cinema_bookings(booking_id) ON DELETE CASCADE
            );
        SQL);

        $this->db->query(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_cbs_booking_id
                ON anitrack.cinema_booking_seats (booking_id);
        SQL);

        $this->db->query(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_cbs_showtime_id
                ON anitrack.cinema_booking_seats (showtime_id);
        SQL);

        $this->db->query('DROP TRIGGER IF EXISTS trg_audit_cinema_booking_seats ON anitrack.cinema_booking_seats;');
        $this->db->query(<<<'SQL'
            CREATE TRIGGER trg_audit_cinema_booking_seats
            AFTER INSERT OR UPDATE OR DELETE ON anitrack.cinema_booking_seats
            FOR EACH ROW EXECUTE FUNCTION anitrack.fn_audit('booking_seat_id');
        SQL);
    }

    /**
     * Menghapus tabel anitrack.cinema_booking_seats (rollback).
     */
    public function down(): void
    {
        $this->db->query('DROP TRIGGER IF EXISTS trg_audit_cinema_booking_seats ON anitrack.cinema_booking_seats;');
        $this->db->query('DROP TABLE IF EXISTS anitrack.cinema_booking_seats CASCADE;');
    }
}
