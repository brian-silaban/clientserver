<script setup>
import { ref, computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { Bookmark, Save, Trash2, Compass } from 'lucide-vue-next';
import { http } from '@/api/http';
import { useToastStore } from '@/stores/toast';
import { POSTER_FALLBACK_SVG, onPosterError } from '@/utils/formatters';

const toast = useToastStore();
const items = ref([]);
const stats = ref(null);
const summary = ref([]);
const statusFilter = ref(0);
const loading = ref(true);
const busyAnimeId = ref(null);

const statusOptions = [
  { id: 1, label: 'Watching' },
  { id: 2, label: 'Completed' },
  { id: 3, label: 'On Hold' },
  { id: 4, label: 'Dropped' },
  { id: 6, label: 'Plan to Watch' },
];

const filteredItems = computed(() => {
  if (statusFilter.value === 0) return items.value;
  return items.value.filter((i) => Number(i.status_id) === statusFilter.value);
});

async function loadWatchlist() {
  loading.value = true;
  try {
    const res = await http.get('/watchlist');
    items.value = (res.data?.items || []).map((row) => ({ ...row }));
    stats.value = res.data?.stats || null;
    summary.value = res.data?.summary || [];
  } catch (err) {
    toast.error(err.message || 'Gagal memuat daftar tontonan.');
  } finally {
    loading.value = false;
  }
}

async function saveRow(row) {
  busyAnimeId.value = row.anime_id;
  try {
    await http.put(`/watchlist/${row.anime_id}`, {
      status_id: Number(row.status_id),
      watched_episodes: Number(row.watched_episodes),
      score: Number(row.score),
    });
    toast.success(`Watchlist "${row.title}" diperbarui.`);
    await loadWatchlist();
  } catch (err) {
    const detail = err.errors ? Object.values(err.errors).join(' ') : err.message;
    toast.error(detail || 'Gagal memperbarui watchlist.');
  } finally {
    busyAnimeId.value = null;
  }
}

async function removeRow(row) {
  busyAnimeId.value = row.anime_id;
  try {
    await http.delete(`/watchlist/${row.anime_id}`);
    toast.info(`"${row.title}" dihapus dari watchlist.`);
    await loadWatchlist();
  } catch (err) {
    toast.error(err.message || 'Gagal menghapus dari watchlist.');
  } finally {
    busyAnimeId.value = null;
  }
}

onMounted(loadWatchlist);
</script>

<template>
  <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-col gap-2 border-b border-stone-200 pb-5 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="font-mono text-xs font-semibold uppercase tracking-wider text-crimson">
          My Anime List · View v_user_stats &amp; RLS Aktif
        </p>
        <h1 class="mt-1 font-display text-3xl font-bold text-ink-heading">Daftar Tontonan Saya</h1>
      </div>
      <RouterLink to="/anime" class="btn-secondary self-start sm:self-auto">
        <Compass class="h-4 w-4" :stroke-width="1.75" />
        Tambah dari Katalog
      </RouterLink>
    </div>

    <!-- Ringkasan Statistik dari v_user_stats -->
    <div v-if="stats" class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-5">
      <div class="rounded-xl border border-stone-200 bg-white p-4">
        <p class="text-xs text-ink-muted">Watching</p>
        <p class="mt-1 font-display text-2xl font-bold text-ink-heading tabular-nums">{{ stats.user_watching }}</p>
      </div>
      <div class="rounded-xl border border-stone-200 bg-white p-4">
        <p class="text-xs text-ink-muted">Completed</p>
        <p class="mt-1 font-display text-2xl font-bold text-emeraldok tabular-nums">{{ stats.user_completed }}</p>
      </div>
      <div class="rounded-xl border border-stone-200 bg-white p-4">
        <p class="text-xs text-ink-muted">Plan to Watch</p>
        <p class="mt-1 font-display text-2xl font-bold text-ink-heading tabular-nums">{{ stats.user_plantowatch }}</p>
      </div>
      <div class="rounded-xl border border-stone-200 bg-white p-4">
        <p class="text-xs text-ink-muted">Rata-rata Skor</p>
        <p class="mt-1 font-display text-2xl font-bold text-ambercine tabular-nums">{{ stats.stats_mean_score || '-' }}</p>
      </div>
      <div class="col-span-2 rounded-xl border border-stone-200 bg-white p-4 sm:col-span-1">
        <p class="text-xs text-ink-muted">Total Episode</p>
        <p class="mt-1 font-display text-2xl font-bold text-ink-heading tabular-nums">{{ stats.stats_episodes }}</p>
      </div>
    </div>

    <!-- Tab Filter Status -->
    <div class="mb-5 flex flex-wrap gap-2">
      <button
        type="button"
        class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors"
        :class="statusFilter === 0 ? 'border-charcoal bg-charcoal text-white' : 'border-stone-200 bg-white text-ink-body hover:bg-subtle'"
        @click="statusFilter = 0"
      >
        Semua ({{ items.length }})
      </button>
      <button
        v-for="opt in statusOptions"
        :key="opt.id"
        type="button"
        class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors"
        :class="statusFilter === opt.id ? 'border-crimson bg-crimson text-white' : 'border-stone-200 bg-white text-ink-body hover:bg-subtle'"
        @click="statusFilter = opt.id"
      >
        {{ opt.label }}
      </button>
    </div>

    <div v-if="loading" class="space-y-3">
      <div v-for="n in 4" :key="n" class="h-24 animate-pulse rounded-2xl bg-stone-200/70" />
    </div>

    <div
      v-else-if="filteredItems.length === 0"
      class="rounded-2xl border border-dashed border-stone-300 bg-white p-12 text-center"
    >
      <Bookmark class="mx-auto h-10 w-10 text-stone-400" :stroke-width="1.5" />
      <p class="mt-3 font-display text-lg font-bold text-ink-heading">Daftar Tontonan Kosong</p>
      <p class="mt-1 text-sm text-ink-muted">Buka halaman detail anime untuk mulai menyimpan daftar tontonan pribadi.</p>
    </div>

    <div v-else class="divide-y divide-stone-200 overflow-hidden rounded-2xl border border-stone-200/90 bg-white">
      <div
        v-for="row in filteredItems"
        :key="row.anime_id"
        class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between"
      >
        <div class="flex min-w-0 items-center gap-3.5">
          <RouterLink :to="`/anime/${row.anime_id}`" class="shrink-0">
            <img
              :src="row.image_url || POSTER_FALLBACK_SVG"
              :alt="row.title"
              class="aspect-[2/3] w-14 rounded-lg object-cover"
              @error="onPosterError"
            />
          </RouterLink>
          <div class="min-w-0">
            <RouterLink :to="`/anime/${row.anime_id}`" class="font-display text-base font-bold text-ink-heading hover:text-crimson">
              {{ row.title }}
            </RouterLink>
            <p class="text-xs text-ink-muted tabular-nums">
              {{ row.type_name }} · Total {{ row.total_episodes || '?' }} eps · Skor Katalog {{ row.anime_score || '-' }}
            </p>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <select v-model.number="row.status_id" class="input-field !w-36 !py-1.5 text-xs">
            <option v-for="opt in statusOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
          </select>

          <div class="flex items-center gap-1 text-xs">
            <span class="text-ink-muted">Eps:</span>
            <input
              v-model.number="row.watched_episodes"
              type="number"
              min="0"
              :max="row.total_episodes > 0 ? row.total_episodes : undefined"
              class="input-field !w-20 !py-1.5 text-xs tabular-nums"
            />
          </div>

          <div class="flex items-center gap-1 text-xs">
            <span class="text-ink-muted">Skor:</span>
            <input
              v-model.number="row.score"
              type="number"
              min="0"
              max="10"
              class="input-field !w-16 !py-1.5 text-xs tabular-nums"
            />
          </div>

          <button
            type="button"
            class="btn-primary !px-3 !py-1.5 text-xs"
            :disabled="busyAnimeId === row.anime_id"
            @click="saveRow(row)"
          >
            <Save class="h-3.5 w-3.5" :stroke-width="1.75" />
            Simpan
          </button>

          <button
            type="button"
            class="btn-secondary !px-2.5 !py-1.5 text-xs text-crimson hover:bg-crimson-soft/40"
            :disabled="busyAnimeId === row.anime_id"
            aria-label="Hapus dari watchlist"
            @click="removeRow(row)"
          >
            <Trash2 class="h-3.5 w-3.5" :stroke-width="1.75" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
