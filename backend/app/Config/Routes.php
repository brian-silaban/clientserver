<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

// ============================================================================
// REST API v1 — AniCine v2.0 (Port 8083)
// ============================================================================
// Tangani seluruh CORS preflight OPTIONS agar browser tidak menerima 404 sebelum filter Cors berjalan
$routes->options('api/v1/(:any)', static function () {
    return service('response')->setStatusCode(204);
});

$routes->group('api/v1', ['namespace' => 'App\Controllers\Api'], static function ($routes) {
    // ------------------------------------------------------------------------
    // 1. Autentikasi Publik (Rate Limited via ThrottleFilter)
    // ------------------------------------------------------------------------
    $routes->post('auth/register', 'Auth::register', ['filter' => 'throttle:10,60']);
    $routes->post('auth/login', 'Auth::login', ['filter' => 'throttle:15,60']);

    // ------------------------------------------------------------------------
    // 2. Katalog Anime & Jadwal Bioskop Anime — Terbuka untuk Publik (Guest)
    // ------------------------------------------------------------------------
    $routes->get('anime', 'Anime::index');
    $routes->get('anime/meta', 'Anime::meta');
    $routes->get('anime/theatrical', 'Anime::theatrical');
    $routes->get('anime/tahun/(:num)', 'Anime::byYear/$1');
    $routes->get('anime/(:num)', 'Anime::show/$1');

    $routes->get('cinema/status', 'Cinema::status');
    $routes->get('cinema/movies', 'Cinema::movies');
    $routes->get('cinema/showtimes', 'Cinema::showtimes');
    $routes->get('cinema/showtimes/(:num)/seats', 'Cinema::showtimeSeats/$1');
    $routes->get('cinema/studios', 'Cinema::studios');

    // ------------------------------------------------------------------------
    // 3. Endpoint Member & Admin (Wajib Login — JWT role: user atau admin)
    // ------------------------------------------------------------------------
    $routes->group('', ['filter' => 'jwt:user,admin'], static function ($routes) {
        $routes->get('auth/me', 'Auth::me');
        $routes->put('auth/me', 'Auth::updateMe');

        $routes->get('bookings', 'Booking::index');
        $routes->get('bookings/(:num)', 'Booking::show/$1');
        $routes->post('bookings/(:num)/pay', 'Booking::pay/$1');
        $routes->post('bookings/(:num)/cancel', 'Booking::cancel/$1');

        $routes->get('watchlist', 'Watchlist::index');
        $routes->put('watchlist/(:num)', 'Watchlist::upsert/$1');
        $routes->delete('watchlist/(:num)', 'Watchlist::delete/$1');
    });

    // Pembuatan booking dilindungi JWT + Rate Limiting
    $routes->post('bookings', 'Booking::create', ['filter' => ['jwt:user,admin', 'throttle:20,60']]);

    // ------------------------------------------------------------------------
    // 4. Endpoint Khusus Administrator (Wajib Login — JWT role: admin)
    // ------------------------------------------------------------------------
    $routes->group('', ['filter' => 'jwt:admin'], static function ($routes) {
        // CRUD Katalog Anime (dengan Optimistic Locking & Audit Log)
        $routes->post('anime', 'Anime::create');
        $routes->put('anime/(:num)', 'Anime::update/$1');
        $routes->patch('anime/(:num)', 'Anime::update/$1');
        $routes->delete('anime/(:num)', 'Anime::delete/$1');

        // Manajemen Admin
        $routes->get('admin/dashboard', 'Admin::dashboard');
        $routes->get('admin/theatrical', 'Admin::theatricalList');
        $routes->post('admin/theatrical', 'Admin::createTheatrical');
        $routes->put('admin/theatrical/(:num)', 'Admin::updateTheatrical/$1');
        $routes->delete('admin/theatrical/(:num)', 'Admin::deleteTheatrical/$1');

        $routes->get('admin/bookings', 'Admin::bookings');
        $routes->patch('admin/bookings/(:num)/status', 'Admin::updateBookingStatus/$1');

        $routes->get('admin/users', 'Admin::users');
        $routes->patch('admin/users/(:num)', 'Admin::updateUser/$1');

        $routes->get('admin/audit-logs', 'Admin::auditLogs');

        // Backup & Verifikasi Database (Admin Only — tanpa restore/unduh publik)
        $routes->get('backups', 'Backup::index');
        $routes->get('backups/(:segment)/verify', 'Backup::verify/$1');
    });

    // Pemicu backup dilindungi JWT Admin + Rate Limiting ketat
    $routes->post('backups', 'Backup::create', ['filter' => ['jwt:admin', 'throttle:5,60']]);
});
