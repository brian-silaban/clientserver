<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

// REST API Group
$routes->group('api', ['namespace' => 'App\Controllers\Api'], static function ($routes) {
    // 1. Anime REST API (Service Brian)
    $routes->get('anime/theatrical', 'Anime::theatrical');
    $routes->get('anime/tahun/(:num)', 'Anime::byYear/$1');
    $routes->resource('anime', ['except' => 'new,edit']);

    // 2. Cinema REST API (Integrasi Informasi Jadwal Bioskop - Service Sat)
    $routes->get('cinema/status', 'Cinema::status');
    $routes->get('cinema/movies', 'Cinema::movies');
    $routes->get('cinema/movies/(:num)', 'Cinema::movieDetail/$1');
    $routes->get('cinema/showtimes', 'Cinema::showtimes');
    $routes->get('cinema/studios', 'Cinema::studios');
});
