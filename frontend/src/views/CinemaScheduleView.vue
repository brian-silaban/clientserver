<script setup>
import { ref, computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { Film, Star, Clock, MapPin } from 'lucide-vue-next';
import { http } from '@/api/http';
import ShowtimeTicketGroup from '@/components/ShowtimeTicketGroup.vue';
import { POSTER_FALLBACK_SVG, getScreeningBadge, onPosterError } from '@/utils/formatters';

const movies = ref([]);
const statusFilter = ref('ALL');
const loading = ref(true);
const errorMsg = ref('');

const filteredMovies = computed(() => {
  if (statusFilter.value === 'ALL') return movies.value;
  return movies.value.filter((item) => item.screening_status === statusFilter.value);
});

async function loadTheatrical() {
  loading.value = true;
  errorMsg.value = '';
  try {
    const res = await http.get('/anime/theatrical');
    movies.value = res.data || [];
  } catch (err) {
    errorMsg.value = err.message || 'Gagal memuat jadwal anime bioskop Medan.';
  } finally {
    loading.value = false;
  }
}

onMounted(loadTheatrical);
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <!-- Header Halaman -->
    <div class="mb-8 flex flex-col gap-4 border-b border-stone-200 pb-6 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="font-mono text-xs font-semibold uppercase tracking-wider text-crimson">
          Khusus Film Anime · Kota Medan
        </p>
        <h1 class="mt-1 font-display text-3xl font-bold text-ink-heading">
          Sedang &amp; Akan Tayang di Bioskop
        </h1>
        <p class="mt-1.5 text-sm text-ink-muted">
          Daftar film non-anime dari layanan bioskop disaring otomatis; pilih jadwal tayang untuk melihat peta kursi dan memesan tiket.
        </p>
      </div>

      <!-- Tab Filter Status Tayang -->
      <div class="inline-flex rounded-xl border border-stone-200 bg-white p-1 text-xs font-semibold">
        <button
          type="button"
          class="rounded-lg px-3.5 py-2 transition-colors"
          :class="statusFilter === 'ALL' ? 'bg-charcoal text-white' : 'text-ink-muted hover:text-ink-heading'"
          @click="statusFilter = 'ALL'"
        >
          Semua ({{ movies.length }})
        </button>
        <button
          type="button"
          class="rounded-lg px-3.5 py-2 transition-colors"
          :class="statusFilter === 'NOW_SHOWING' ? 'bg-crimson text-white' : 'text-ink-muted hover:text-ink-heading'"
          @click="statusFilter = 'NOW_SHOWING'"
        >
          Sedang Tayang
        </button>
        <button
          type="button"
          class="rounded-lg px-3.5 py-2 transition-colors"
          :class="statusFilter === 'UPCOMING' ? 'bg-ambercine text-white' : 'text-ink-muted hover:text-ink-heading'"
          @click="statusFilter = 'UPCOMING'"
        >
          Akan Tayang
        </button>
      </div>
    </div>

    <div v-if="errorMsg" class="rounded-2xl border border-rose-200 bg-white p-6 text-center text-sm text-crimson">
      {{ errorMsg }}
    </div>

    <div v-else-if="loading" class="space-y-6">
      <div v-for="n in 3" :key="n" class="h-64 animate-pulse rounded-2xl bg-stone-200/70" />
    </div>

    <div
      v-else-if="filteredMovies.length === 0"
      class="rounded-2xl border border-dashed border-stone-300 bg-white p-12 text-center text-sm text-ink-muted"
    >
      Belum ada film anime pada kategori penayangan ini.
    </div>

    <div v-else class="space-y-8">
      <section
        v-for="movie in filteredMovies"
        :key="movie.anime_id"
        class="grid gap-6 rounded-2xl border border-stone-200/90 bg-white p-5 sm:p-6 lg:grid-cols-12"
      >
        <!-- Info Film Anime -->
        <div class="flex flex-col gap-5 sm:flex-row lg:col-span-5">
          <RouterLink :to="`/anime/${movie.anime_id}`" class="shrink-0 self-center sm:self-start">
            <img
              :src="movie.image_url || POSTER_FALLBACK_SVG"
              :alt="`Poster ${movie.title}`"
              class="aspect-[2/3] w-36 rounded-xl object-cover shadow-sm"
              @error="onPosterError"
            />
          </RouterLink>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <span
                class="inline-flex items-center gap-1 rounded-md px-2.5 py-0.5 text-xs font-semibold"
                :class="getScreeningBadge(movie.screening_status).className"
              >
                <Film class="h-3 w-3" :stroke-width="2" />
                {{ getScreeningBadge(movie.screening_status).label }}
              </span>
              <span class="rounded border border-stone-200 px-2 py-0.5 font-mono text-[11px] text-ink-muted">
                {{ movie.rating_code || 'PG-13' }}
              </span>
            </div>

            <RouterLink :to="`/anime/${movie.anime_id}`">
              <h2 class="mt-2 font-display text-2xl font-bold text-ink-heading hover:text-crimson">
                {{ movie.title }}
              </h2>
            </RouterLink>
            <p class="mt-0.5 text-xs text-ink-muted">
              {{ movie.title_japanese || movie.title_english }}
            </p>

            <div class="mt-3 flex flex-wrap items-center gap-3 text-xs tabular-nums">
              <span class="inline-flex items-center gap-1 font-bold text-ambercine">
                <Star class="h-3.5 w-3.5 fill-ambercine" :stroke-width="1.75" />
                {{ movie.score || '-' }}
              </span>
              <span class="inline-flex items-center gap-1 text-ink-muted">
                <Clock class="h-3.5 w-3.5" :stroke-width="1.75" />
                {{ movie.duration_min || '-' }} menit
              </span>
              <span class="inline-flex items-center gap-1 text-ink-muted">
                <MapPin class="h-3.5 w-3.5" :stroke-width="1.75" />
                {{ (movie.cinema_showtimes || []).length }} Jadwal
              </span>
            </div>

            <p class="mt-3 text-xs leading-relaxed text-ink-body">
              {{ movie.release_note || movie.genres }}
            </p>
          </div>
        </div>

        <!-- Panel Jadwal Bioskop Medan -->
        <div class="lg:col-span-7">
          <ShowtimeTicketGroup
            :showtimes="movie.cinema_showtimes || []"
            :screening-status="movie.screening_status"
          />
        </div>
      </section>
    </div>
  </div>
</template>
