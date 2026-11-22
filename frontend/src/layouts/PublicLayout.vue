<script setup>
import { ref } from 'vue';
import { RouterLink, RouterView, useRouter } from 'vue-router';
import {
  Film,
  Compass,
  Ticket,
  Bookmark,
  ShieldCheck,
  LogOut,
  LogIn,
  Menu,
  X,
} from 'lucide-vue-next';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';

const auth = useAuthStore();
const toast = useToastStore();
const router = useRouter();
const mobileMenuOpen = ref(false);

function handleLogout() {
  auth.logout();
  mobileMenuOpen.value = false;
  toast.info('Anda telah keluar dari akun AniCine.');
  router.push({ name: 'home' });
}
</script>

<template>
  <div class="flex min-h-screen flex-col bg-paper text-ink-body">
    <!-- Sticky Masthead Editorial -->
    <header class="sticky top-0 z-40 border-b border-stone-200/90 bg-paper/95 backdrop-blur-md">
      <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
        <!-- Brand Lockup -->
        <RouterLink to="/" class="flex items-baseline gap-3 focus-visible:outline-none">
          <span class="font-display text-2xl font-bold tracking-tight leading-none">
            <span class="text-crimson">Ani</span><span class="text-ink-slate">Cine</span>
          </span>
          <span class="hidden border-l border-stone-300 pl-3 text-xs font-medium text-ink-muted sm:inline">
            Arsip Anime &amp; Bioskop Medan
          </span>
        </RouterLink>

        <!-- Navigasi Desktop -->
        <nav class="hidden items-center gap-1 md:flex" aria-label="Navigasi utama">
          <RouterLink
            to="/"
            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-ink-body transition-colors hover:bg-subtle hover:text-ink-heading"
            active-class="bg-subtle text-ink-heading font-semibold"
            exact-active-class="bg-subtle text-crimson font-semibold"
          >
            Beranda
          </RouterLink>
          <RouterLink
            to="/anime"
            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-ink-body transition-colors hover:bg-subtle hover:text-ink-heading"
            active-class="bg-subtle text-crimson font-semibold"
          >
            <Compass class="h-4 w-4" :stroke-width="1.75" />
            Katalog Anime
          </RouterLink>
          <RouterLink
            to="/bioskop"
            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-ink-body transition-colors hover:bg-subtle hover:text-ink-heading"
            active-class="bg-subtle text-crimson font-semibold"
          >
            <Film class="h-4 w-4" :stroke-width="1.75" />
            Anime di Bioskop
          </RouterLink>
          <RouterLink
            v-if="auth.isAuthenticated"
            to="/tiket"
            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-ink-body transition-colors hover:bg-subtle hover:text-ink-heading"
            active-class="bg-subtle text-crimson font-semibold"
          >
            <Ticket class="h-4 w-4" :stroke-width="1.75" />
            Tiket Saya
          </RouterLink>
          <RouterLink
            v-if="auth.isAuthenticated"
            to="/watchlist"
            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-ink-body transition-colors hover:bg-subtle hover:text-ink-heading"
            active-class="bg-subtle text-crimson font-semibold"
          >
            <Bookmark class="h-4 w-4" :stroke-width="1.75" />
            Watchlist
          </RouterLink>
        </nav>

        <!-- Aksi Akun Desktop -->
        <div class="hidden items-center gap-2.5 md:flex">
          <template v-if="auth.isAuthenticated">
            <RouterLink
              v-if="auth.isAdmin"
              to="/admin"
              class="btn-secondary !px-3 !py-2 text-xs"
            >
              <ShieldCheck class="h-4 w-4 text-crimson" :stroke-width="1.75" />
              Panel Admin
            </RouterLink>
            <div class="flex items-center gap-2 rounded-lg border border-stone-200 bg-white px-3 py-1.5 text-xs">
              <span class="font-semibold text-ink-heading">{{ auth.displayName }}</span>
              <span
                class="rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                :class="auth.isAdmin ? 'bg-crimson-soft text-crimson' : 'bg-subtle text-ink-muted'"
              >
                {{ auth.user?.role }}
              </span>
            </div>
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-2 text-xs font-semibold text-ink-muted transition-colors hover:bg-subtle hover:text-crimson"
              @click="handleLogout"
            >
              <LogOut class="h-4 w-4" :stroke-width="1.75" />
              Keluar
            </button>
          </template>
          <template v-else>
            <RouterLink to="/login" class="btn-secondary !px-3.5 !py-2">
              <LogIn class="h-4 w-4" :stroke-width="1.75" />
              Masuk
            </RouterLink>
            <RouterLink to="/register" class="btn-primary !px-3.5 !py-2">
              Daftar Akun
            </RouterLink>
          </template>
        </div>

        <!-- Tombol Menu Mobile -->
        <button
          type="button"
          class="inline-flex items-center justify-center rounded-lg border border-stone-200 p-2 text-ink-heading hover:bg-subtle md:hidden"
          :aria-label="mobileMenuOpen ? 'Tutup menu' : 'Buka menu'"
          @click="mobileMenuOpen = !mobileMenuOpen"
        >
          <X v-if="mobileMenuOpen" class="h-5 w-5" :stroke-width="1.75" />
          <Menu v-else class="h-5 w-5" :stroke-width="1.75" />
        </button>
      </div>

      <!-- Menu Drawer Mobile -->
      <div v-if="mobileMenuOpen" class="border-t border-stone-200 bg-white px-4 py-4 md:hidden">
        <div class="flex flex-col gap-1">
          <RouterLink
            to="/"
            class="rounded-lg px-3 py-2.5 text-sm font-medium text-ink-heading hover:bg-subtle"
            @click="mobileMenuOpen = false"
          >
            Beranda
          </RouterLink>
          <RouterLink
            to="/anime"
            class="rounded-lg px-3 py-2.5 text-sm font-medium text-ink-heading hover:bg-subtle"
            @click="mobileMenuOpen = false"
          >
            Katalog Anime
          </RouterLink>
          <RouterLink
            to="/bioskop"
            class="rounded-lg px-3 py-2.5 text-sm font-medium text-ink-heading hover:bg-subtle"
            @click="mobileMenuOpen = false"
          >
            Anime di Bioskop Medan
          </RouterLink>
          <template v-if="auth.isAuthenticated">
            <RouterLink
              to="/tiket"
              class="rounded-lg px-3 py-2.5 text-sm font-medium text-ink-heading hover:bg-subtle"
              @click="mobileMenuOpen = false"
            >
              Tiket Saya
            </RouterLink>
            <RouterLink
              to="/watchlist"
              class="rounded-lg px-3 py-2.5 text-sm font-medium text-ink-heading hover:bg-subtle"
              @click="mobileMenuOpen = false"
            >
              Watchlist Saya
            </RouterLink>
            <RouterLink
              v-if="auth.isAdmin"
              to="/admin"
              class="rounded-lg px-3 py-2.5 text-sm font-semibold text-crimson hover:bg-crimson-soft/50"
              @click="mobileMenuOpen = false"
            >
              Panel Admin
            </RouterLink>
            <button
              type="button"
              class="mt-2 rounded-lg border border-stone-200 px-3 py-2 text-left text-sm font-semibold text-ink-muted hover:bg-subtle"
              @click="handleLogout"
            >
              Keluar ({{ auth.displayName }})
            </button>
          </template>
          <div v-else class="mt-3 grid grid-cols-2 gap-2">
            <RouterLink to="/login" class="btn-secondary" @click="mobileMenuOpen = false">
              Masuk
            </RouterLink>
            <RouterLink to="/register" class="btn-primary" @click="mobileMenuOpen = false">
              Daftar Akun
            </RouterLink>
          </div>
        </div>
      </div>
    </header>

    <!-- Konten Utama -->
    <main class="flex-1">
      <RouterView />
    </main>

    <!-- Footer Editorial Ringkas -->
    <footer class="border-t border-stone-200 bg-white">
      <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-6 text-xs text-ink-muted sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
        <div>
          <span class="font-display text-sm font-bold text-ink-heading">
            <span class="text-crimson">Ani</span>Cine
          </span>
          <span class="ml-2">Katalog Anime (`db_anitrack`) &amp; Integrasi Jadwal Bioskop Medan.</span>
        </div>
        <div class="flex flex-wrap items-center gap-4">
          <RouterLink to="/anime" class="hover:text-crimson">Katalog Lengkap</RouterLink>
          <RouterLink to="/bioskop" class="hover:text-crimson">Tayang di Medan</RouterLink>
          <span class="font-mono text-[11px] text-stone-400">REST API v1 · Vue 3</span>
        </div>
      </div>
    </footer>
  </div>
</template>
