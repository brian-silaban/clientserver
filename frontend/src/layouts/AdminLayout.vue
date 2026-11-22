<script setup>
import { ref } from 'vue';
import { RouterLink, RouterView, useRouter } from 'vue-router';
import {
  LayoutDashboard,
  Library,
  Clapperboard,
  Ticket,
  Users,
  ScrollText,
  HardDrive,
  ArrowLeft,
  LogOut,
  Menu,
  X,
} from 'lucide-vue-next';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';

const auth = useAuthStore();
const toast = useToastStore();
const router = useRouter();
const sidebarOpen = ref(false);

const navItems = [
  { label: 'Dashboard', to: '/admin', icon: LayoutDashboard, exact: true },
  { label: 'Katalog Anime', to: '/admin/anime', icon: Library },
  { label: 'Pemetaan Bioskop', to: '/admin/bioskop', icon: Clapperboard },
  { label: 'Transaksi Tiket', to: '/admin/bookings', icon: Ticket },
  { label: 'Pengguna', to: '/admin/users', icon: Users },
  { label: 'Audit Log', to: '/admin/audit-logs', icon: ScrollText },
  { label: 'Backup & Verifikasi', to: '/admin/backups', icon: HardDrive },
];

function handleLogout() {
  auth.logout();
  toast.info('Sesi administrator telah berakhir.');
  router.push({ name: 'login' });
}
</script>

<template>
  <div class="flex min-h-screen bg-paper text-ink-body">
    <!-- Sidebar Desktop -->
    <aside class="hidden w-64 shrink-0 flex-col bg-charcoal text-stone-200 lg:flex">
      <div class="flex h-16 items-center justify-between border-b border-white/10 px-5">
        <RouterLink to="/admin" class="flex items-baseline gap-2">
          <span class="font-display text-2xl font-bold tracking-tight">
            <span class="text-crimson">Ani</span><span class="text-white">Cine</span>
          </span>
          <span class="rounded bg-crimson/20 px-1.5 py-0.5 font-mono text-[10px] font-semibold uppercase text-rose-300">
            Admin
          </span>
        </RouterLink>
      </div>

      <nav class="flex-1 space-y-1 p-3" aria-label="Navigasi panel admin">
        <RouterLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-stone-300 transition-colors hover:bg-white/10 hover:text-white"
          :active-class="item.exact ? '' : 'bg-crimson text-white hover:bg-crimson'"
          :exact-active-class="item.exact ? 'bg-crimson text-white hover:bg-crimson' : ''"
        >
          <component :is="item.icon" class="h-4 w-4 shrink-0" :stroke-width="1.75" />
          {{ item.label }}
        </RouterLink>
      </nav>

      <div class="border-t border-white/10 p-4">
        <div class="mb-3 rounded-lg bg-charcoal-elevated px-3 py-2 text-xs">
          <p class="font-semibold text-white">{{ auth.displayName }}</p>
          <p class="truncate text-stone-400">{{ auth.user?.email }}</p>
        </div>
        <div class="flex flex-col gap-1.5">
          <RouterLink
            to="/"
            class="flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-stone-300 hover:bg-white/10 hover:text-white"
          >
            <ArrowLeft class="h-3.5 w-3.5" :stroke-width="1.75" />
            Kembali ke Web Publik
          </RouterLink>
          <button
            type="button"
            class="flex items-center gap-2 rounded-lg px-3 py-2 text-left text-xs font-medium text-rose-300 hover:bg-white/10"
            @click="handleLogout"
          >
            <LogOut class="h-3.5 w-3.5" :stroke-width="1.75" />
            Keluar
          </button>
        </div>
      </div>
    </aside>

    <!-- Area Konten Utama -->
    <div class="flex min-w-0 flex-1 flex-col">
      <!-- Topbar Mobile untuk Admin -->
      <header class="flex h-14 items-center justify-between border-b border-stone-200 bg-charcoal px-4 text-white lg:hidden">
        <RouterLink to="/admin" class="font-display text-xl font-bold">
          <span class="text-crimson">Ani</span>Cine <span class="font-mono text-xs text-rose-300">Admin</span>
        </RouterLink>
        <button
          type="button"
          class="rounded-lg border border-white/15 p-1.5 text-stone-200"
          @click="sidebarOpen = !sidebarOpen"
        >
          <X v-if="sidebarOpen" class="h-5 w-5" :stroke-width="1.75" />
          <Menu v-else class="h-5 w-5" :stroke-width="1.75" />
        </button>
      </header>

      <div v-if="sidebarOpen" class="border-b border-white/10 bg-charcoal p-3 text-stone-200 lg:hidden">
        <div class="grid grid-cols-2 gap-1.5">
          <RouterLink
            v-for="item in navItems"
            :key="item.to"
            :to="item.to"
            class="flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium hover:bg-white/10"
            @click="sidebarOpen = false"
          >
            <component :is="item.icon" class="h-4 w-4 shrink-0" :stroke-width="1.75" />
            {{ item.label }}
          </RouterLink>
        </div>
        <div class="mt-3 flex items-center justify-between border-t border-white/10 pt-2 text-xs">
          <RouterLink to="/" class="text-stone-300" @click="sidebarOpen = false">Web Publik</RouterLink>
          <button type="button" class="text-rose-300" @click="handleLogout">Keluar</button>
        </div>
      </div>

      <main class="flex-1 p-4 sm:p-6 lg:p-8">
        <div class="mx-auto max-w-6xl">
          <RouterView />
        </div>
      </main>
    </div>
  </div>
</template>
