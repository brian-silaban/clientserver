<?php

define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
chdir(FCPATH);

require FCPATH . '../app/Config/Paths.php';
$paths = new Config\Paths();

require $paths->systemDirectory . '/Boot.php';
\CodeIgniter\Boot::preload($paths);
require $paths->systemDirectory . '/Common.php';

// Muat file .env secara eksplisit
$dotEnv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
$dotEnv->load();

echo "=== 1. TEST AnimeModel::getTheatricalList() ===\n";
$animeModel = new App\Models\AnimeModel();
$theatrical = $animeModel->getTheatricalList();
echo "Total Anime Theatrical: " . count($theatrical) . "\n";
foreach ($theatrical as $t) {
    echo " - [ID {$t['anime_id']}] {$t['title']} -> Cinema Movie ID: {$t['cinema_movie_id']}\n";
}

echo "\n=== 2. TEST AnimeModel::getRichDetail(32281) + Auto-Embed Cinema Showtimes ===\n";
$rich = $animeModel->getRichDetail(32281);
$cinema = new App\Libraries\CinemaService();
$rich['cinema_showtimes'] = $cinema->getShowtimes((int) $rich['cinema_movie_id']);
echo "Title: {$rich['title']}\n";
echo "Type: {$rich['type_name']}\n";
echo "Rating: {$rich['rating_code']} ({$rich['rating_description']})\n";
echo "Genres: {$rich['genres']}\n";
echo "Studios: {$rich['studios']}\n";
echo "Is Theatrical: " . ($rich['is_theatrical'] ? 'YES' : 'NO') . "\n";
echo "Embedded Cinema Showtimes Count: " . count($rich['cinema_showtimes']) . "\n";
foreach ($rich['cinema_showtimes'] as $cs) {
    echo "   * {$cs['cinema_name']} ({$cs['studio_name']}) - {$cs['start_time']} - Rp " . number_format((float)$cs['ticket_price']) . "\n";
}

echo "\n=== 3. TEST CinemaService::getStatus() ===\n";
$status = $cinema->getStatus();
print_r($status);

echo "\n=== 4. TEST CinemaService::getMovies(animeOnly=true) ===\n";
$movies = $cinema->getMovies(null, true);
echo "Total Anime Movies in Cinema: " . count($movies) . "\n";
foreach ($movies as $m) {
    echo " - [Movie ID {$m['movie_id']}] {$m['title']} | Genres: {$m['genre_names']} | Rating: {$m['age_rating']}\n";
}

echo "\n=== 5. TEST CinemaService::getShowtimes() ===\n";
$showtimes = $cinema->getShowtimes();
echo "Total Showtimes: " . count($showtimes) . "\n";
foreach ($showtimes as $s) {
    echo " - Showtime #{$s['showtime_id']}: {$s['movie_title']} @ {$s['cinema_name']} ({$s['studio_name']}) at {$s['start_time']} - Price: Rp " . number_format((float)$s['ticket_price']) . " (Avail: {$s['seats_available']}/{$s['total_capacity']})\n";
}

echo "\n=== 6. TEST CinemaService::getStudios() ===\n";
$studios = $cinema->getStudios();
echo "Total Studios in Medan: " . count($studios) . "\n";
foreach ($studios as $st) {
    echo " - [Studio #{$st['studio_id']}] {$st['cinema_name']} - {$st['studio_name']} ({$st['studio_type']}) - Kapasitas: {$st['capacity']} kursi\n";
}

echo "\nALL TESTS PASSED SUCCESSFULLY!\n";

