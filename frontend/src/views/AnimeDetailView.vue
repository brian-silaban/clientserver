<script setup>
import { ref, reactive, onMounted, watch } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import {
  ArrowLeft,
  Star,
  Film,
  Bookmark,
  Trash2,
  LogIn,
  Calendar,
  Clock,
  Tv,
} from 'lucide-vue-next';
import { http } from '@/api/http';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';
import ShowtimeTicketGroup from '@/components/ShowtimeTicketGroup.vue';
import { POSTER_FALLBACK_SVG, getScreeningBadge, onPosterError } from '@/utils/formatters';

const route = useRoute();
const auth = useAuthStore();
const toast = useToastStore();

const anime = ref(null);
const loading = ref(true);
const errorMsg = ref('');

// State Watchlist Pribadi
const inWatchlist = ref(false);
const savingWatchlist = ref(false);
const watchlistForm = reactive({
  status_id: 6,
  watched_episodes: 0,
  score: 0,
});

const statusOptions = [
  { id: 1, label: 'Watching' },
  { id: 2, label: 'Completed' },
  { id: 3, label: 'On Hold' },
  { id: 4, label: 'Dropped' },
  { id: 6, label: 'Plan to Watch' },
];

async function loadDetail() {
  loading.value = true;
  errorMsg.value = '';
  try {
    const res = await http.get(`/anime/${route.params.id}`);
    anime.value = res.data;

    if (auth.isAuthenticated) {
      await loadWatchlistEntry(Number(route.params.id));
    }
  } catch (err) {
    errorMsg.value = err.message || 'Gagal memuat detail anime.';
  } finally {
    loading.value = false;
  }
}

async function loadWatchlistEntry(animeId) {
  try {
    const res = await http.get('/watchlist');
    const found = (res.data?.items || []).find((i) => Number(i.anime_id) === animeId);
    if (found) {
      inWatchlist.value = true;
      watchlistForm.status_id = Number(found.status_id);
      watchlistForm.watched_episodes = Number(found.watched_episodes);
      watchlistForm.score = Number(found.score);
    } else {
      inWatchlist.value = false;
      watchlistForm.status_id = 6;
      watchlistForm.watched_episodes = 0;
      watchlistForm.score = 0;
    }
  } catch {
    // Abaikan jika gagal memuat watchlist
  }
}

async function saveWatchlist() {
  savingWatchlist.value = true;
  try {
    const res = await http.put(`/watchlist/${anime.value.anime_id}`, {
      status_id: Number(watchlistForm.status_id),
      watched_episodes: Number(watchlistForm.watched_episodes),
      score: Number(watchlistForm.score),
    });
    inWatchlist.value = true;
    watchlistForm.watched_episodes = Number(res.data?.watched_episodes ?? watchlistForm.watched_episodes);
    toast.success('Daftar tontonan berhasil disimpan.');
  } catch (err) {
    const detail = err.errors ? Object.values(err.errors).join(' ') : err.message;
    toast.error(detail || 'Gagal menyimpan watchlist.');
  } finally {
    savingWatchlist.value = false;
  }
}

async function removeWatchlist() {
  savingWatchlist.value = true;
  try {
    await http.delete(`/watchlist/${anime.value.anime_id}`);
    inWatchlist.value = false;
    watchlistForm.status_id = 6;
    watchlistForm.watched_episodes = 0;
    watchlistForm.score = 0;
    toast.info('Anime dihapus dari daftar tontonan Anda.');
  } catch (err) {
    toast.error(err.message || 'Gagal menghapus dari watchlist.');
  } finally {
    savingWatchlist.value = false;
  }
}

watch(() => route.params.id, loadDetail);
onMounted(loadDetail);
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="mb-6">
      <RouterLink
        to="/anime"
        class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-muted hover:text-crimson"
      >
        <ArrowLeft class="h-4 w-4" :stroke-width="1.75" />
        Kembali ke Katalog Anime
      </RouterLink>
    </div>

    <div v-if="loading" class="grid gap-8 lg:grid-cols-12">
      <div class="h-96 animate-pulse rounded-2xl bg-stone-200/70 lg:col-span-7" />
      <div class="h-96 animate-pulse rounded-2xl bg-stone-200/70 lg:col-span-5" />
    </div>

    <div v-else-if="errorMsg" class="rounded-2xl border border-rose-200 bg-white p-8 text-center text-sm text-crimson">
      {{ errorMsg }}
    </div>

    <!-- Layout 2 Kolom Editorial Asimetris -->
    <div v-else-if="anime" class="grid gap-8 lg:grid-cols-12">
      <!-- Kolom Kiri: Metadata Katalog db_anitrack + Watchlist -->
      <div class="space-y-6 lg:col-span-7">
        <div class="ticket-card p-5 sm:p-6">
          <div class="flex flex-col gap-6 sm:flex-row">
            <img
              :src="anime.image_url || POSTER_FALLBACK_SVG"
              :alt="`Poster ${anime.title}`"
              class="aspect-[2/3] w-44 shrink-0 self-center rounded-xl object-cover shadow-md sm:self-start"
              @error="onPosterError"
            />

            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <span
                  v-if="anime.is_theatrical"
                  class="inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-semibold"
                  :class="getScreeningBadge(anime.screening_status).className"
                >
                  <Film class="h-3.5 w-3.5" :stroke-width="1.75" />
                  {{ getScreeningBadge(anime.screening_status).label }} di Medan
                </span>
                <span class="rounded-md bg-subtle px-2.5 py-1 text-xs font-semibold text-ink-body">
                  {{ anime.type_name || 'Anime' }}
                </span>
                <span v-if="anime.rating_code" class="rounded-md border border-stone-200 px-2 py-1 font-mono text-xs text-ink-muted">
                  {{ anime.rating_code }}
                </span>
              </div>

              <h1 class="mt-3 font-display text-2xl font-bold leading-tight text-ink-heading sm:text-3xl">
                {{ anime.title }}
              </h1>
              <p class="mt-1 text-sm text-ink-muted">
                {{ [anime.title_english, anime.title_japanese].filter(Boolean).join(' · ') || 'Judul Orisinal' }}
              </p>

              <!-- Metrik Utama -->
              <div class="mt-4 flex flex-wrap gap-3 text-xs tabular-nums">
                <span class="inline-flex items-center gap-1 rounded-lg bg-ambercine-soft px-2.5 py-1.5 font-bold text-ambercine">
                  <Star class="h-3.5 w-3.5 fill-ambercine" :stroke-width="1.75" />
                  Skor {{ anime.score || '-' }}
                </span>
                <span class="inline-flex items-center gap-1 rounded-lg bg-subtle px-2.5 py-1.5 font-medium text-ink-heading">
                  <Tv class="h-3.5 w-3.5 text-ink-muted" :stroke-width="1.75" />
                  {{ anime.episodes || 0 }} Episode
                </span>
                <span v-if="anime.duration_min" class="inline-flex items-center gap-1 rounded-lg bg-subtle px-2.5 py-1.5 font-medium text-ink-heading">
                  <Clock class="h-3.5 w-3.5 text-ink-muted" :stroke-width="1.75" />
                  {{ anime.duration_min }} menit
                </span>
                <span v-if="anime.premiered_year" class="inline-flex items-center gap-1 rounded-lg bg-subtle px-2.5 py-1.5 font-medium text-ink-heading">
                  <Calendar class="h-3.5 w-3.5 text-ink-muted" :stroke-width="1.75" />
                  {{ [anime.premiered_season, anime.premiered_year].filter(Boolean).join(' ') }}
                </span>
              </div>

              <!-- Genre & Studio -->
              <dl class="mt-5 grid grid-cols-1 gap-3 border-t border-stone-100 pt-4 text-sm sm:grid-cols-2">
                <div>
                  <dt class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Genre</dt>
                  <dd class="mt-0.5 text-ink-heading">{{ anime.genres || '-' }}</dd>
                </div>
                <div>
                  <dt class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Studio Produksi</dt>
                  <dd class="mt-0.5 text-ink-heading">{{ anime.studios || '-' }}</dd>
                </div>
              </dl>
            </div>
          </div>

          <div class="mt-6 border-t border-stone-100 pt-5">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Catatan &amp; Sinopsis Katalog</h2>
            <p class="mt-2 text-sm leading-relaxed text-ink-body">
              {{ anime.background || 'Data metadata katalog ini bersumber dari skema anitrack.anime beserta relasi genre, studio produksi, tipe media, dan klasifikasi usia.' }}
            </p>
          </div>
        </div>

        <!-- Panel Interaksi Watchlist (Wajib Login) -->
        <div class="ticket-card p-5 sm:p-6">
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
              <Bookmark class="h-4 w-4 text-crimson" :stroke-width="1.75" />
              <h2 class="font-display text-lg font-bold text-ink-heading">My Anime List / Watchlist</h2>
            </div>
            <span v-if="inWatchlist" class="rounded-md bg-emeraldok-soft px-2.5 py-1 text-xs font-semibold text-emeraldok">
              Tersimpan di Daftar Anda
            </span>
          </div>

          <div v-if="!auth.isAuthenticated" class="mt-4 flex flex-col items-start justify-between gap-3 rounded-xl bg-subtle/70 p-4 sm:flex-row sm:items-center">
            <p class="text-sm text-ink-body">
              Masuk atau daftar akun terlebih dahulu untuk mencatat progres episode dan skor pribadi anime ini.
            </p>
            <RouterLink
              :to="{ name: 'login', query: { redirect: route.fullPath } }"
              class="btn-primary shrink-0"
            >
              <LogIn class="h-4 w-4" :stroke-width="1.75" />
              Masuk Akun
            </RouterLink>
          </div>

          <form v-else class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-12" @submit.prevent="saveWatchlist">
            <div class="sm:col-span-5">
              <label class="mb-1 block text-xs font-semibold text-ink-muted">Status Tontonan</label>
              <select v-model.number="watchlistForm.status_id" class="input-field">
                <option v-for="opt in statusOptions" :key="opt.id" :value="opt.id">
                  {{ opt.label }}
                </option>
              </select>
            </div>

            <div class="sm:col-span-3">
              <label class="mb-1 block text-xs font-semibold text-ink-muted">
                Episode (maks {{ anime.episodes || '∞' }})
              </label>
              <input
                v-model.number="watchlistForm.watched_episodes"
                type="number"
                min="0"
                :max="anime.episodes > 0 ? anime.episodes : undefined"
                class="input-field tabular-nums"
              />
            </div>

            <div class="sm:col-span-4">
              <label class="mb-1 block text-xs font-semibold text-ink-muted">Skor Pribadi (0–10)</label>
              <input
                v-model.number="watchlistForm.score"
                type="number"
                min="0"
                max="10"
                class="input-field tabular-nums"
              />
            </div>

            <div class="flex flex-wrap items-center gap-2 pt-1 sm:col-span-12">
              <button type="submit" class="btn-primary" :disabled="savingWatchlist">
                <Bookmark class="h-4 w-4" :stroke-width="1.75" />
                {{ inWatchlist ? 'Perbarui Watchlist' : 'Simpan ke Watchlist' }}
              </button>
              <button
                v-if="inWatchlist"
                type="button"
                class="btn-secondary text-crimson hover:bg-crimson-soft/40"
                :disabled="savingWatchlist"
                @click="removeWatchlist"
              >
                <Trash2 class="h-4 w-4" :stroke-width="1.75" />
                Hapus
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Kolom Kanan: Panel Jadwal Bioskop Medan -->
      <div class="lg:col-span-5">
        <div class="mb-4 flex items-center justify-between">
          <div>
            <p class="font-mono text-xs font-semibold uppercase tracking-wider text-crimson">
              Layanan Bioskop Medan
            </p>
            <h2 class="mt-0.5 font-display text-xl font-bold text-ink-heading">
              Jadwal Penayangan &amp; Tiket
            </h2>
          </div>
          <span
            v-if="anime.is_theatrical"
            class="rounded-md bg-emeraldok-soft px-2.5 py-1 text-xs font-semibold text-emeraldok"
          >
            Aktif di Medan
          </span>
        </div>

        <div
          v-if="!anime.is_theatrical"
          class="rounded-2xl border border-dashed border-stone-300 bg-white p-6 text-sm text-ink-body"
        >
          <p class="font-display text-base font-bold text-ink-heading">Tidak Sedang Tayang di Bioskop</p>
          <p class="mt-1.5 leading-relaxed text-ink-muted">
            <strong>{{ anime.title }}</strong> saat ini hanya tersedia sebagai entri arsip katalog (`{{ anime.type_name }}`). Lihat daftar film anime yang sedang atau akan tayang di layar lebar Medan.
          </p>
          <RouterLink to="/bioskop" class="btn-secondary mt-4">
            <Film class="h-4 w-4" :stroke-width="1.75" />
            Lihat Anime di Bioskop Medan
          </RouterLink>
        </div>

        <div v-else class="space-y-4">
          <p v-if="anime.release_note" class="rounded-xl border border-amber-200 bg-ambercine-soft/60 px-4 py-3 text-xs font-medium text-amber-900">
            {{ anime.release_note }}
          </p>
          <ShowtimeTicketGroup
            :showtimes="anime.cinema_showtimes || []"
            :screening-status="anime.screening_status"
          />
        </div>
      </div>
    </div>
  </div>
</template>
