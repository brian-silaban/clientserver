<script setup>
import { ref, reactive, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Search, SlidersHorizontal, RotateCcw, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { http } from '@/api/http';
import AnimeCard from '@/components/AnimeCard.vue';

const route = useRoute();
const router = useRouter();

const items = ref([]);
const pager = ref({ current_page: 1, per_page: 15, total_data: 0, total_pages: 1 });
const meta = ref({ media_types: [], genres: [] });
const loading = ref(true);
const errorMsg = ref('');

const filters = reactive({
  q: String(route.query.q || ''),
  type_id: String(route.query.type_id || ''),
  genre_id: String(route.query.genre_id || ''),
  year: String(route.query.year || ''),
  sort: String(route.query.sort || 'score'),
  page: Math.max(1, Number(route.query.page || 1)),
});

async function loadMeta() {
  try {
    const res = await http.get('/anime/meta');
    meta.value = res.data || { media_types: [], genres: [] };
  } catch {
    // Dropdown opsional tetap aman bila gagal
  }
}

async function fetchCatalog() {
  loading.value = true;
  errorMsg.value = '';

  const params = new URLSearchParams();
  params.set('page', String(filters.page));
  params.set('per_page', '15');
  params.set('sort', filters.sort);

  if (filters.q.trim()) params.set('q', filters.q.trim());
  if (filters.type_id) params.set('type_id', filters.type_id);
  if (filters.genre_id) params.set('genre_id', filters.genre_id);
  if (filters.year) params.set('year', filters.year);

  try {
    const res = await http.get(`/anime?${params.toString()}`);
    items.value = res.data || [];
    pager.value = res.pager || { current_page: 1, per_page: 15, total_data: items.value.length, total_pages: 1 };
  } catch (err) {
    errorMsg.value = err.message || 'Gagal memuat daftar katalog anime.';
  } finally {
    loading.value = false;
  }
}

function syncUrlAndFetch(resetPage = true) {
  if (resetPage) filters.page = 1;

  const query = {};
  if (filters.q.trim()) query.q = filters.q.trim();
  if (filters.type_id) query.type_id = filters.type_id;
  if (filters.genre_id) query.genre_id = filters.genre_id;
  if (filters.year) query.year = filters.year;
  if (filters.sort !== 'score') query.sort = filters.sort;
  if (filters.page > 1) query.page = String(filters.page);

  router.replace({ query });
  fetchCatalog();
}

function resetFilters() {
  filters.q = '';
  filters.type_id = '';
  filters.genre_id = '';
  filters.year = '';
  filters.sort = 'score';
  syncUrlAndFetch(true);
}

function goToPage(newPage) {
  if (newPage < 1 || newPage > pager.value.total_pages) return;
  filters.page = newPage;
  syncUrlAndFetch(false);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

watch(
  () => route.query.q,
  (nextQ) => {
    const val = String(nextQ || '');
    if (val !== filters.q) {
      filters.q = val;
      filters.page = 1;
      fetchCatalog();
    }
  }
);

onMounted(() => {
  loadMeta();
  fetchCatalog();
});
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <!-- Header Katalog -->
    <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="font-mono text-xs font-semibold uppercase tracking-wider text-crimson">
          Katalog Publik · Tanpa Login
        </p>
        <h1 class="mt-1 font-display text-3xl font-bold text-ink-heading">
          Jelajahi Katalog Anime
        </h1>
      </div>
      <p class="text-sm text-ink-muted tabular-nums">
        Menampilkan <strong class="text-ink-heading">{{ Number(pager.total_data || 0).toLocaleString('id-ID') }}</strong> judul anime
      </p>
    </div>

    <!-- Bar Filter & Pencarian -->
    <form
      class="mb-8 rounded-2xl border border-stone-200/90 bg-white p-4 sm:p-5"
      @submit.prevent="syncUrlAndFetch(true)"
    >
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-12">
        <div class="relative lg:col-span-4">
          <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-muted" :stroke-width="1.75" />
          <input
            v-model="filters.q"
            type="text"
            placeholder="Cari judul Jepang, Inggris, atau Romaji..."
            class="input-field !pl-10"
          />
        </div>

        <div class="lg:col-span-2">
          <select v-model="filters.type_id" class="input-field" @change="syncUrlAndFetch(true)">
            <option value="">Semua Tipe</option>
            <option v-for="t in meta.media_types" :key="t.type_id" :value="String(t.type_id)">
              {{ t.type_name }}
            </option>
          </select>
        </div>

        <div class="lg:col-span-2">
          <select v-model="filters.genre_id" class="input-field" @change="syncUrlAndFetch(true)">
            <option value="">Semua Genre</option>
            <option v-for="g in meta.genres" :key="g.genre_id" :value="String(g.genre_id)">
              {{ g.genre_name }}
            </option>
          </select>
        </div>

        <div class="lg:col-span-1">
          <input
            v-model="filters.year"
            type="number"
            min="1960"
            max="2030"
            placeholder="Tahun"
            class="input-field tabular-nums"
          />
        </div>

        <div class="lg:col-span-2">
          <select v-model="filters.sort" class="input-field" @change="syncUrlAndFetch(true)">
            <option value="score">Skor Tertinggi</option>
            <option value="popularity">Terpopuler</option>
            <option value="newest">Terbaru</option>
            <option value="title">Abjad (A–Z)</option>
          </select>
        </div>

        <div class="flex items-center gap-2 lg:col-span-1">
          <button type="submit" class="btn-primary w-full !px-3" title="Terapkan filter">
            <SlidersHorizontal class="h-4 w-4" :stroke-width="1.75" />
          </button>
          <button
            type="button"
            class="btn-secondary !px-3"
            title="Reset filter"
            @click="resetFilters"
          >
            <RotateCcw class="h-4 w-4" :stroke-width="1.75" />
          </button>
        </div>
      </div>
    </form>

    <!-- Error State -->
    <div v-if="errorMsg" class="rounded-2xl border border-rose-200 bg-crimson-soft/40 p-6 text-center text-sm text-crimson">
      {{ errorMsg }}
    </div>

    <!-- Loading Skeleton -->
    <div v-else-if="loading" class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
      <div v-for="n in 15" :key="n" class="h-80 animate-pulse rounded-2xl bg-stone-200/70" />
    </div>

    <!-- Empty State -->
    <div
      v-else-if="items.length === 0"
      class="rounded-2xl border border-dashed border-stone-300 bg-white p-12 text-center"
    >
      <p class="font-display text-lg font-bold text-ink-heading">Tidak ada anime yang cocok</p>
      <p class="mt-1 text-sm text-ink-muted">Coba ubah kata kunci pencarian atau reset filter yang sedang aktif.</p>
      <button type="button" class="btn-secondary mt-4" @click="resetFilters">
        Reset Semua Filter
      </button>
    </div>

    <!-- Grid Poster Katalog -->
    <div v-else>
      <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
        <AnimeCard v-for="anime in items" :key="anime.anime_id" :anime="anime" />
      </div>

      <!-- Paginasi -->
      <div
        v-if="pager.total_pages > 1"
        class="mt-8 flex flex-col items-center justify-between gap-3 border-t border-stone-200 pt-6 sm:flex-row"
      >
        <p class="text-sm text-ink-muted tabular-nums">
          Halaman <strong class="text-ink-heading">{{ pager.current_page }}</strong> dari
          <strong class="text-ink-heading">{{ pager.total_pages }}</strong>
        </p>
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="btn-secondary"
            :disabled="pager.current_page <= 1"
            @click="goToPage(pager.current_page - 1)"
          >
            <ChevronLeft class="h-4 w-4" :stroke-width="1.75" />
            Sebelumnya
          </button>
          <button
            type="button"
            class="btn-secondary"
            :disabled="pager.current_page >= pager.total_pages"
            @click="goToPage(pager.current_page + 1)"
          >
            Berikutnya
            <ChevronRight class="h-4 w-4" :stroke-width="1.75" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
