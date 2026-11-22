/**
 * HTTP Client AniCine v2.0 berbasis Fetch API.
 * Otomatis menyisipkan Bearer JWT dari localStorage dan menormalisasi respons error API.
 */
const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8083/api/v1').replace(/\/+$/, '');
const TOKEN_STORAGE_KEY = 'anicine_access_token';

export class ApiError extends Error {
  constructor(message, status = 500, errors = null) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.errors = errors;
  }
}

let unauthorizedHandler = null;

/**
 * Mendaftarkan handler global ketika server mengembalikan 401 Unauthorized.
 */
export function setUnauthorizedHandler(callback) {
  unauthorizedHandler = callback;
}

/**
 * Menjalankan request HTTP ke Backend REST API v1 AniCine.
 *
 * @param {string} path - Endpoint relatif terhadap /api/v1 (contoh: '/anime?page=1')
 * @param {object} options - Opsi method, body, headers, skipAuthRedirect
 */
export async function apiRequest(path, options = {}) {
  const { method = 'GET', body = null, headers = {}, skipAuthRedirect = false } = options;

  const requestHeaders = {
    Accept: 'application/json',
    ...headers,
  };

  const token = localStorage.getItem(TOKEN_STORAGE_KEY);
  if (token) {
    requestHeaders.Authorization = `Bearer ${token}`;
  }

  let serializedBody;
  if (body !== null && body !== undefined) {
    requestHeaders['Content-Type'] = 'application/json';
    serializedBody = JSON.stringify(body);
  }

  let response;
  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      method,
      headers: requestHeaders,
      body: serializedBody,
    });
  } catch (networkError) {
    throw new ApiError(
      'Tidak dapat terhubung ke server backend AniCine. Pastikan server port 8083 sedang berjalan.',
      0
    );
  }

  let json = null;
  try {
    json = await response.json();
  } catch (parseError) {
    json = null;
  }

  if (!response.ok) {
    const message = json?.message || `Permintaan gagal dengan kode HTTP ${response.status}.`;
    const fieldErrors = json?.errors || null;

    if (response.status === 401 && !skipAuthRedirect && typeof unauthorizedHandler === 'function') {
      unauthorizedHandler();
    }

    throw new ApiError(message, response.status, fieldErrors);
  }

  return json;
}

export const http = {
  get: (path, options = {}) => apiRequest(path, { ...options, method: 'GET' }),
  post: (path, body = null, options = {}) => apiRequest(path, { ...options, method: 'POST', body }),
  put: (path, body = null, options = {}) => apiRequest(path, { ...options, method: 'PUT', body }),
  patch: (path, body = null, options = {}) => apiRequest(path, { ...options, method: 'PATCH', body }),
  delete: (path, options = {}) => apiRequest(path, { ...options, method: 'DELETE' }),
};

export { API_BASE_URL, TOKEN_STORAGE_KEY };
