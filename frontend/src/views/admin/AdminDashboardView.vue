<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import {
  Library,
  Film,
  Ticket,
  Users,
  ScrollText,
  HardDrive,
  RefreshCw,
} from 'lucide-vue-next';
import { http } from '@/api/http';
import { useToastStore } from '@/stores/toast';
import { formatRupiah } from '@/utils/formatters';

const toast = useToastStore();
const stats = ref(null);
const loading = ref(true);

async function loadDashboard() {
  loading.value = true;
  try {
    const res = await http.get('/admin/dashboard');
    stats.value = res.data;
  } catch (err) {
    toast.error(err.message || 'Gagal memuat dashboard admin.');
  } finally {
    loading.value = false;
  }
}

onMounted(loadDashboard);
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <p class="font-mono text-xs font-semibold uppercase tracking-wider text-crimson">
          Panel Administrator · RBAC jwt:admin
        </p>
        <h1 class="mt-1 font-display text-2xl font-bold text-ink-heading sm:text-3xl">
          Ringkasan Operasional AniCine
        </h1>
      </div>
      <button type="button" class="btn-secondary !py-2 text-xs" :disabled="loading" @click="loadDashboard">
        <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': loading }" :stroke-width="1.75" />
        Segarkan Data
      </button>
    </div>

    <div v-if="loading && !stats" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div v-for="n in 4" :key="n" class="h-28 animate-pulse rounded-2xl bg-stone-200/70" />
    </div>

    <template v-else-if="stats">
      <!-- Metrik Utama -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="ticket-card p-5">
          <div class="flex items-center justify-between text-ink-muted">
            <span class="text-xs font-semibold uppercase">Total Katalog Anime</span>
            <Library class="h-4 w-4 text-crimson" :stroke-width="1.75" />
          </div>
          <p class="mt-2 font-display text-3xl font-bold text-ink-heading tabular-nums">
            {{ Number(stats.total_anime).toLocaleString('id-ID') }}
          </p>
          <p class="mt-1 text-xs text-ink-muted">Database `db_anitrack`</p>
        </div>

        <div class="ticket-card p-5">
          <div class="flex items-center justify-between text-ink-muted">
            <span class="text-xs font-semibold uppercase">Tayang di Bioskop</span>
            <Film class="h-4 w-4 text-ambercine" :stroke-width="1.75" />
          </div>
          <p class="mt-2 font-display text-3xl font-bold text-ink-heading tabular-nums">
            {{ stats.now_showing_count }}
            <span class="text-sm font-normal text-ink-muted">+ {{ stats.upcoming_count }} Akan Tayang</span>
          </p>
          <p class="mt-1 text-xs text-ink-muted">Tabel `anime_cinema_map`</p>
        </div>

        <div class="ticket-card p-5">
          <div class="flex items-center justify-between text-ink-muted">
            <span class="text-xs font-semibold uppercase">Omzet Tiket Lunas</span>
            <Ticket class="h-4 w-4 text-emeraldok" :stroke-width="1.75" />
          </div>
          <p class="mt-2 font-mono text-2xl font-bold text-emeraldok tabular-nums">
            {{ formatRupiah(stats.bookings.revenue) }}
          </p>
          <p class="mt-1 text-xs text-ink-muted tabular-nums">
            {{ stats.bookings.paid }} Lunas · {{ stats.bookings.pending }} Pending · {{ stats.bookings.cancelled }} Batal
          </p>
        </div>

        <div class="ticket-card p-5">
          <div class="flex items-center justify-between text-ink-muted">
            <span class="text-xs font-semibold uppercase">Akun &amp; Audit 24 Jam</span>
            <Users class="h-4 w-4 text-ink-slate" :stroke-width="1.75" />
          </div>
          <p class="mt-2 font-display text-3xl font-bold text-ink-heading tabular-nums">
            {{ stats.registered_users }}
            <span class="text-sm font-normal text-ink-muted">Akun</span>
          </p>
          <p class="mt-1 text-xs text-ink-muted tabular-nums">
            {{ stats.audit_events_24h }} perubahan tercatat di `audit_log`
          </p>
        </div>
      </div>

      <!-- Status Integrasi Partner & Akses Cepat -->
      <div class="grid gap-6 lg:grid-cols-12">
        <div class="ticket-card p-5 lg:col-span-6">
          <h2 class="font-display text-lg font-bold text-ink-heading">Status Integrasi Layanan Bioskop Partner</h2>
          <dl class="mt-4 space-y-2.5 text-sm">
            <div class="flex items-center justify-between border-b border-stone-100 pb-2">
              <dt class="text-ink-muted">Mode Aktif</dt>
              <dd class="rounded bg-emeraldok-soft px-2.5 py-0.5 font-mono text-xs font-semibold text-emeraldok">
                {{ stats.cinema_connection?.mode }}
              </dd>
            </div>
            <div class="flex items-center justify-between border-b border-stone-100 pb-2">
              <dt class="text-ink-muted">Kebijakan Tulis Partner</dt>
              <dd class="font-mono text-xs text-ink-heading">{{ stats.cinema_connection?.partner_write }}</dd>
            </div>
            <div class="flex items-center justify-between">
              <dt class="text-ink-muted">Endpoint Partner (Satria)</dt>
              <dd class="max-w-xs truncate font-mono text-xs text-ink-muted">
                {{ stats.cinema_connection?.external_url }}
              </dd>
            </div>
          </dl>
        </div>

        <div class="ticket-card p-5 lg:col-span-6">
          <h2 class="font-display text-lg font-bold text-ink-heading">Akses Cepat Manajemen PLMKB502</h2>
          <div class="mt-4 grid grid-cols-1 gap-2.5 sm:grid-cols-2">
            <RouterLink to="/admin/anime" class="btn-secondary justify-start text-xs">
              <Library class="h-4 w-4 text-crimson" :stroke-width="1.75" />
              Kelola Katalog Anime
            </RouterLink>
            <RouterLink to="/admin/bioskop" class="btn-secondary justify-start text-xs">
              <Film class="h-4 w-4 text-ambercine" :stroke-width="1.75" />
              Pemetaan Tayang Bioskop
            </RouterLink>
            <RouterLink to="/admin/audit-logs" class="btn-secondary justify-start text-xs">
              <ScrollText class="h-4 w-4 text-ink-slate" :stroke-width="1.75" />
              Buka Audit Log Viewer
            </RouterLink>
            <RouterLink to="/admin/backups" class="btn-secondary justify-start text-xs">
              <HardDrive class="h-4 w-4 text-emeraldok" :stroke-width="1.75" />
              Backup &amp; Verifikasi DB
            </RouterLink>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
