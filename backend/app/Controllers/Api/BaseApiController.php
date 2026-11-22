<?php

namespace App\Controllers\Api;

use App\Services\RlsContextService;
use CodeIgniter\HTTP\Exceptions\HTTPException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;

/**
 * BaseApiController
 * Kelas induk tipis untuk menyatukan format respons JSON, pembacaan body JSON,
 * validasi 422, dan akses konteks autentikasi JWT pada seluruh endpoint `/api/v1/*`.
 */
abstract class BaseApiController extends ResourceController
{
    protected $format = 'json';

    /**
     * Membaca payload JSON dari body request secara aman.
     *
     * @return array<string, mixed>|null
     */
    protected function getJsonInput(): ?array
    {
        try {
            $input = $this->request->getJSON(true);
        } catch (HTTPException $e) {
            return null;
        }

        return is_array($input) && $input !== [] ? $input : null;
    }

    /**
     * Mengembalikan respons sukses dengan struktur JSON seragam.
     */
    protected function respondSuccess(
        mixed $data,
        string $message = 'Berhasil',
        int $status = 200,
        array $extra = []
    ): ResponseInterface {
        $payload = array_merge([
            'status'  => $status,
            'message' => $message,
            'data'    => $data,
        ], $extra);

        return $this->respond($payload, $status);
    }

    /**
     * Mengembalikan respons gagal dengan status HTTP spesifik tanpa membocorkan stack trace.
     */
    protected function respondError(
        string $message,
        int $status = 400,
        ?array $errors = null
    ): ResponseInterface {
        $payload = [
            'status'  => $status,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return $this->respond($payload, $status);
    }

    /**
     * Mengembalikan respons kegagalan validasi dengan HTTP 422 Unprocessable Entity.
     */
    protected function respondValidationError(
        array $errors,
        string $message = 'Input tidak valid. Periksa kembali data yang dikirim.'
    ): ResponseInterface {
        return $this->respondError($message, 422, $errors);
    }

    /**
     * Mengambil data pengguna terautentikasi dari JwtFilter.
     *
     * @return array{user_id: int, username: string, email: string, role: string}|null
     */
    protected function getAuthUser(): ?array
    {
        return RlsContextService::getAuthUser();
    }

    /**
     * Membaca parameter ?per_page= secara aman dalam rentang [1..$max].
     */
    protected function getPerPage(int $default = 12, int $max = 100): int
    {
        $perPage = (int) ($this->request->getGet('per_page') ?? $default);

        return max(1, min($perPage, $max));
    }

    /**
     * Membaca parameter ?page= secara aman (minimal 1).
     */
    protected function getPage(): int
    {
        return max(1, (int) ($this->request->getGet('page') ?? 1));
    }
}
