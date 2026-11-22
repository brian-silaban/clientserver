import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import PublicLayout from '@/layouts/PublicLayout.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

const routes = [
  {
    path: '/',
    component: PublicLayout,
    children: [
      {
        path: '',
        name: 'home',
        component: () => import('@/views/HomeView.vue'),
        meta: { title: 'Beranda — AniCine' },
      },
      {
        path: 'anime',
        name: 'anime-catalog',
        component: () => import('@/views/AnimeCatalogView.vue'),
        meta: { title: 'Katalog Anime — AniCine' },
      },
      {
        path: 'anime/:id',
        name: 'anime-detail',
        component: () => import('@/views/AnimeDetailView.vue'),
        meta: { title: 'Detail Anime — AniCine' },
      },
      {
        path: 'bioskop',
        name: 'cinema-schedule',
        component: () => import('@/views/CinemaScheduleView.vue'),
        meta: { title: 'Anime di Bioskop Medan — AniCine' },
      },
      {
        path: 'booking/:showtimeId',
        name: 'seat-booking',
        component: () => import('@/views/SeatBookingView.vue'),
        meta: { title: 'Pilih Kursi & Pesan Tiket — AniCine' },
      },
      {
        path: 'tiket',
        name: 'my-tickets',
        component: () => import('@/views/MyTicketsView.vue'),
        meta: { title: 'Tiket Saya — AniCine', requiresAuth: true },
      },
      {
        path: 'watchlist',
        name: 'my-watchlist',
        component: () => import('@/views/MyWatchlistView.vue'),
        meta: { title: 'Daftar Tontonan Saya — AniCine', requiresAuth: true },
      },
      {
        path: 'login',
        name: 'login',
        component: () => import('@/views/LoginView.vue'),
        meta: { title: 'Masuk Akun — AniCine', guestOnly: true },
      },
      {
        path: 'register',
        name: 'register',
        component: () => import('@/views/RegisterView.vue'),
        meta: { title: 'Daftar Akun — AniCine', guestOnly: true },
      },
    ],
  },
  {
    path: '/admin',
    component: AdminLayout,
    meta: { requiresAuth: true, requiresAdmin: true },
    children: [
      {
        path: '',
        name: 'admin-dashboard',
        component: () => import('@/views/admin/AdminDashboardView.vue'),
        meta: { title: 'Dashboard Admin — AniCine' },
      },
      {
        path: 'anime',
        name: 'admin-anime',
        component: () => import('@/views/admin/AdminAnimeView.vue'),
        meta: { title: 'Kelola Katalog Anime — Admin AniCine' },
      },
      {
        path: 'bioskop',
        name: 'admin-cinema',
        component: () => import('@/views/admin/AdminCinemaView.vue'),
        meta: { title: 'Pemetaan Anime Bioskop — Admin AniCine' },
      },
      {
        path: 'bookings',
        name: 'admin-bookings',
        component: () => import('@/views/admin/AdminBookingsView.vue'),
        meta: { title: 'Transaksi Tiket — Admin AniCine' },
      },
      {
        path: 'users',
        name: 'admin-users',
        component: () => import('@/views/admin/AdminUsersView.vue'),
        meta: { title: 'Kelola Pengguna — Admin AniCine' },
      },
      {
        path: 'audit-logs',
        name: 'admin-audit-logs',
        component: () => import('@/views/admin/AdminAuditLogView.vue'),
        meta: { title: 'Audit Log Database — Admin AniCine' },
      },
      {
        path: 'backups',
        name: 'admin-backups',
        component: () => import('@/views/admin/AdminBackupsView.vue'),
        meta: { title: 'Backup & Verifikasi DB — Admin AniCine' },
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/',
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() {
    return { top: 0 };
  },
});

/**
 * Navigation Guard RBAC:
 * - Guest yang mencoba masuk halaman Member/Admin diarahkan ke /login dengan query redirect.
 * - Member biasa yang mencoba mengakses /admin dikembalikan ke beranda.
 */
router.beforeEach((to) => {
  const auth = useAuthStore();

  if (to.meta?.title) {
    document.title = to.meta.title;
  }

  if (to.meta?.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } };
  }

  if (to.meta?.requiresAdmin && !auth.isAdmin) {
    return { name: 'home' };
  }

  if (to.meta?.guestOnly && auth.isAuthenticated) {
    return auth.isAdmin ? { name: 'admin-dashboard' } : { name: 'home' };
  }

  return true;
});

export default router;
