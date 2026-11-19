<?php

namespace App\Controllers\Api;

use App\Libraries\CinemaService;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\HTTP\Exceptions\HTTPException;
use CodeIgniter\RESTful\ResourceController;
use Exception;

class Anime extends ResourceController
{
    protected $modelName = 'App\Models\AnimeModel';
    protected $format    = 'json';

    // Membaca body JSON; mengembalikan null jika kosong / format JSON salah
    private function getInput(): ?array
    {
        try {
            $input = $this->request->getJSON(true);
        } catch (HTTPException $e) {
            return null;
        }

        return empty($input) ? null : $input;
    }

    // Menyusun response list + informasi pagination
    private function respondPaginated(array $data, string $message)
    {
        $pager = $this->model->pager;

        return $this->respond([
            'status'  => 200,
            'message' => $message,
            'total'   => count($data),
            'pager'   => [
                'current_page' => $pager->getCurrentPage(),
                'per_page'     => $pager->getPerPage(),
                'total_data'   => $pager->getTotal(),
                'total_pages'  => $pager->getPageCount(),
            ],
            'data' => $data,
        ]);
    }

    // Jumlah data per halaman: ?per_page= (default 20, maksimal 100)
    private function getPerPage(): int
    {
        $perPage = (int) ($this->request->getGet('per_page') ?? 20);

        return max(1, min($perPage, 100));
    }

    // GET /api/anime (opsional: ?q=kata_kunci&type_id=2&page=1&per_page=20)
    public function index()
    {
        $q      = $this->request->getGet('q');
        $typeId = $this->request->getGet('type_id');

        if ($q) {
            $this->model->groupStart()
                ->like('title', $q, 'both', null, true)            // tidak case-sensitive
                ->orLike('title_english', $q, 'both', null, true)
                ->groupEnd();
        }

        // Filter opsional berdasarkan tipe media (1: TV, 2: Movie, 3: OVA, dll.)
        if ($typeId !== null && ctype_digit((string) $typeId)) {
            $this->model->where('type_id', (int) $typeId);
        }

        $data = $this->model->orderBy('anime_id', 'ASC')->paginate($this->getPerPage());

        return $this->respondPaginated($data, 'Daftar anime');
    }

    // GET /api/anime/tahun/{tahun}  (Latihan Mandiri no. 4)
    public function byYear($tahun = null)
    {
        $data = $this->model
            ->where('premiered_year', (int) $tahun)
            ->orderBy('anime_id', 'ASC')
            ->paginate($this->getPerPage());

        return $this->respondPaginated($data, "Daftar anime tahun {$tahun}");
    }

    // GET /api/anime/theatrical (Anime movie yang sedang tayang di bioskop + jadwal bioskop Medan)
    public function theatrical()
    {
        $data = $this->model->getTheatricalList();
        $cinema = new CinemaService();

        foreach ($data as &$anime) {
            $movieId = isset($anime['cinema_movie_id']) ? (int) $anime['cinema_movie_id'] : null;
            try {
                $anime['cinema_showtimes'] = $movieId ? $cinema->getShowtimes($movieId) : [];
            } catch (Exception $e) {
                $anime['cinema_showtimes'] = [];
            }
        }
        unset($anime);

        return $this->respond([
            'status'  => 200,
            'message' => 'Daftar anime movie yang sedang tayang di bioskop beserta jadwal penayangan',
            'total'   => count($data),
            'data'    => $data,
        ]);
    }

    // GET /api/anime/{id}
    public function show($id = null)
    {
        if (! ctype_digit((string) $id)) {
            return $this->failNotFound("Anime dengan id {$id} tidak ditemukan.");
        }

        $data = $this->model->getRichDetail((int) $id);

        if (! $data) {
            return $this->failNotFound("Anime dengan id {$id} tidak ditemukan.");
        }

        // Jika anime tayang di bioskop, otomatis ambil jadwal tayang dari CinemaService (Service Sat)
        if (! empty($data['is_theatrical']) && ! empty($data['cinema_movie_id'])) {
            try {
                $cinema = new CinemaService();
                $data['cinema_showtimes'] = $cinema->getShowtimes((int) $data['cinema_movie_id']);
            } catch (Exception $e) {
                $data['cinema_showtimes'] = [];
            }
        } else {
            $data['cinema_showtimes'] = [];
        }

        return $this->respond([
            'status'  => 200,
            'message' => 'Detail anime',
            'data'    => $data,
        ]);
    }

    // POST /api/anime
    public function create()
    {
        $input = $this->getInput();

        if ($input === null) {
            return $this->fail('Body request harus berupa JSON dan tidak boleh kosong.', 400);
        }

        try {
            if (! $this->model->insert($input)) {
                return $this->failValidationErrors($this->model->errors());
            }
        } catch (DatabaseException $e) {
            // Mis. melanggar CHECK constraint (aired_to < aired_from)
            return $this->fail('Data ditolak database: ' . $e->getMessage(), 400);
        }

        return $this->respondCreated([
            'status'  => 201,
            'message' => 'Anime berhasil ditambahkan.',
            'data'    => $this->model->find($this->model->getInsertID()),
        ]);
    }

    // PUT/PATCH /api/anime/{id}
    public function update($id = null)
    {
        if (! ctype_digit((string) $id) || ! $this->model->find($id)) {
            return $this->failNotFound("Anime dengan id {$id} tidak ditemukan.");
        }

        $input = $this->getInput();

        if ($input === null) {
            return $this->fail('Body request harus berupa JSON dan tidak boleh kosong.', 400);
        }

        $input['anime_id'] = $id; // dibutuhkan rule is_unique[...,anime_id,{anime_id}]

        try {
            if (! $this->model->update($id, $input)) {
                return $this->failValidationErrors($this->model->errors());
            }
        } catch (DatabaseException $e) {
            return $this->fail('Data ditolak database: ' . $e->getMessage(), 400);
        }

        return $this->respond([
            'status'  => 200,
            'message' => 'Anime berhasil diperbarui.',
            'data'    => $this->model->find($id),
        ]);
    }

    // DELETE /api/anime/{id}
    public function delete($id = null)
    {
        if (! ctype_digit((string) $id) || ! $this->model->find($id)) {
            return $this->failNotFound("Anime dengan id {$id} tidak ditemukan.");
        }

        try {
            $this->model->delete($id);
        } catch (DatabaseException $e) {
            // FK user_anime_list ON DELETE RESTRICT: anime masih ada di daftar user
            return $this->fail("Anime dengan id {$id} tidak dapat dihapus karena masih dipakai di user_anime_list.", 409);
        }

        return $this->respondDeleted([
            'status'  => 200,
            'message' => "Anime dengan id {$id} berhasil dihapus.",
        ]);
    }
}
