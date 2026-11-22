import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { http, TOKEN_STORAGE_KEY } from '@/api/http';

const USER_STORAGE_KEY = 'anicine_auth_user';

/**
 * Pinia Store Autentikasi & Sesi JWT (Guest / User / Admin).
 */
export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem(TOKEN_STORAGE_KEY) || '');
  const user = ref(loadStoredUser());
  const loading = ref(false);

  const isAuthenticated = computed(() => Boolean(token.value && user.value));
  const isAdmin = computed(() => user.value?.role === 'admin');
  const displayName = computed(() => user.value?.username || 'Pengguna');

  function loadStoredUser() {
    try {
      const raw = localStorage.getItem(USER_STORAGE_KEY);
      return raw ? JSON.parse(raw) : null;
    } catch {
      return null;
    }
  }

  function persistSession(accessToken, userProfile) {
    token.value = accessToken;
    user.value = userProfile;
    localStorage.setItem(TOKEN_STORAGE_KEY, accessToken);
    localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(userProfile));
  }

  function clearSession() {
    token.value = '';
    user.value = null;
    localStorage.removeItem(TOKEN_STORAGE_KEY);
    localStorage.removeItem(USER_STORAGE_KEY);
  }

  async function login(identifier, password) {
    loading.value = true;
    try {
      const res = await http.post(
        '/auth/login',
        { identifier, password },
        { skipAuthRedirect: true }
      );
      persistSession(res.data.token.access_token, res.data.user);
      return res.data.user;
    } finally {
      loading.value = false;
    }
  }

  async function register(payload) {
    loading.value = true;
    try {
      const res = await http.post('/auth/register', payload, { skipAuthRedirect: true });
      persistSession(res.data.token.access_token, res.data.user);
      return res.data.user;
    } finally {
      loading.value = false;
    }
  }

  async function refreshProfile() {
    if (!token.value) return null;
    try {
      const res = await http.get('/auth/me');
      user.value = res.data.user;
      localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(res.data.user));
      return res.data;
    } catch {
      clearSession();
      return null;
    }
  }

  function logout() {
    clearSession();
  }

  return {
    token,
    user,
    loading,
    isAuthenticated,
    isAdmin,
    displayName,
    login,
    register,
    refreshProfile,
    logout,
  };
});
