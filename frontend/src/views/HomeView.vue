<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { Search, Film, Star, Clock, ArrowRight, Compass, Ticket } from 'lucide-vue-next';
import { http } from '@/api/http';
import AnimeCard from '@/components/AnimeCard.vue';
import {
  POSTER_FALLBACK_SVG,
  formatRupiah,
  formatTimeWib,
  getScreeningBadge,
  onPosterError,
} from '@/utils/formatters';

const router = useRouter();
const searchQuery = ref('');
const theatricalList = ref([]);
const topCatalog = ref([]);
const cinemaStatus = ref(null);
const loading = ref(true);

async function loadHomeData() {
  loading.value = true;
  try {
    const [theatricalRes, catalogRes, statusRes] = await Promise.all([
      http.get('/anime/theatrical'),
      http.get('/anime?page=1&per_page=10&sort=score'),
      http.get('/cinema/status'),
    ]);

    theatricalList.value = theatricalRes.data || [];
    topCatalog.value = catalogRes.data || [];
    cinemaStatus.value = statusRes.data || null;
  } catch (err) {
    console.error('Gagal memuat beranda:', err);
  } finally {
    loading.value = false;
  }
}

function submitQuickSearch() {
  const q = searchQuery.value.trim();
  router.push({ name: 'anime-catalog', query: q ? { q } : {} });
}

onMounted(loadHomeData);
</script>

<template>
  <div>
    <!-- Hero Theatrical Spotlight (Deep Theater Charcoal) -->
    <section class="bg-charcoal text-stone-100">
      <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
        <div class="grid gap-8 lg:grid-cols-12 lg:items-end">
          <div class="lg:col-span-7">
            <div class="mb-4 inline-flex items-center gap-2 rounded-lg border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-medium text-stone-200">
              <span class="h-2 w-2 rounded-full bg-emerald-500" />
              <span>Jadwal Bioskop Medan</span>
              <span v-if="cinemaStatus" class="border-l border-white/15 pl-2 font-mono text-[11px] text-stone-400">
                {{ cinemaStatus.mode }}
              </span>
            </div>

            <h1 class="font-display text-3xl font-bold tracking-tight text-white sm:text-5xl sm:leading-[1.12]">
              Arsip Katalog Anime &amp; Loket Tiket Bioskop Medan
            </h1>
            <p class="mt-4 max-w-2xl text-sm leading-relaxed text-stone-300 sm:text-base">
              Jelajahi ribuan judul anime dari <code class="rounded bg-white/10 px-1.5 py-0.5 font-mono text-xs text-stone-100">db_anitrack</code> tanpa perlu login, pantau khusus film anime yang sedang atau akan tayang di layar lebar Medan, lalu pesan kursi bioskop secara langsung.
            </p>

            <!-- Baris Pencarian Cepat Katalog -->
            <form class="mt-6 flex max-w-xl flex-col gap-2.5 sm:flex-row" @submit.prevent="submitQuickSearch">
              <div class="relative flex-1">
                <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" :stroke-width="1.75" />
                <input
                  v-model="searchQuery"
                  type="text"
                  placeholder="Cari judul anime (contoh: Kimi no Na wa, Naruto, Ghibli)..."
                  class="w-full rounded-lg border border-white/15 bg-charcoal-elevated py-2.5 pl-10 pr-4 text-sm text-white placeholder:text-stone-400 focus:border-crimson focus:outline-none focus:ring-2 focus:ring-crimson/30"
                />
              </div>
              <button type="submit" class="btn-primary shrink-0">
                Cari Katalog
              </button>
            </form>
          </div>

          <!-- Ringkasan Cepat Aksi -->
          <div class="flex flex-wrap gap-3 lg:col-span-5 lg:justify-end">
            <RouterLink to="/bioskop" class="btn-primary">
              <Ticket class="h-4 w-4" :stroke-width="1.75" />
              Lihat Anime Tayang di Medan
            </RouterLink>
            <RouterLink to="/anime" class="btn-ghost-dark">
              <Compass class="h-4 w-4" :stroke-width="1.75" />
              Semua Katalog Anime
            </RouterLink>
          </div>
        </div>

        <!-- Strip Anime Sedang & Akan Tayang di Bioskop Medan -->
        <div class="mt-10 border-t border-white/10 pt-8">
          <div class="mb-5 flex items-center justify-between gap-4">
            <div>
              <h2 class="font-display text-xl font-bold text-white">
                Anime Tayang &amp; Segera Hadir di Bioskop Medan
              </h2>
              <p class="mt-0.5 text-xs text-stone-400">
                Hanya menampilkan film anime yang dipetakan aktif (`NOW_SHOWING` &amp; `UPCOMING`).
              </p>
            </div>
            <RouterLink
              to="/bioskop"
              class="inline-flex items-center gap-1 text-xs font-semibold text-amber-400 hover:text-amber-300"
            >
              Semua Jadwal
              <ArrowRight class="h-3.5 w-3.5" :stroke-width="1.75" />
            </RouterLink>
          </div>

          <div v-if="loading" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div v-for="n in 5" :key="n" class="h-80 animate-pulse rounded-2xl bg-charcoal-elevated" />
          </div>

          <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <article
              v-for="item in theatricalList"
              :key="item.anime_id"
              class="group flex h-full flex-col justify-between overflow-hidden rounded-2xl border border-white/10 bg-charcoal-elevated transition-all hover:border-crimson/70"
            >
              <RouterLink :to="`/anime/${item.anime_id}`" class="relative block aspect-[2/3] overflow-hidden bg-black">
                <img
                  :src="item.image_url || POSTER_FALLBACK_SVG"
                  :alt="`Poster ${item.title}`"
                  class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-105"
                  @error="onPosterError"
                />
                <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/30 to-transparent" />

                <div class="absolute left-2.5 right-2.5 top-2.5 flex items-center justify-between gap-1">
                  <span
                    class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-semibold"
                    :class="getScreeningBadge(item.screening_status).className"
                  >
                    <Film class="h-3 w-3" :stroke-width="2" />
                    {{ getScreeningBadge(item.screening_status).label }}
                  </span>
                  <span class="rounded bg-black/70 px-1.5 py-0.5 font-mono text-[10px] text-stone-200">
                    {{ item.rating_code || 'PG-13' }}
                  </span>
                </div>

                <div class="absolute bottom-2.5 left-2.5 right-2.5">
                  <span class="inline-flex items-center gap-1 rounded bg-black/70 px-2 py-0.5 text-xs font-semibold text-amber-400 tabular-nums">
                    <Star class="h-3 w-3 fill-amber-400 text-amber-400" :stroke-width="1.75" />
                    {{ item.score || '-' }}
                  </span>
                  <h3 class="mt-1.5 line-clamp-2 font-display text-base font-bold leading-snug text-white">
                    {{ item.title }}
                  </h3>
                  <p class="mt-0.5 line-clamp-1 text-[11px] text-stone-300">
                    {{ item.studios || item.genres }}
                  </p>
                </div>
              </RouterLink>

              <!-- Info Jadwal Pertama & Akses Cepat -->
              <div class="flex flex-col gap-2.5 p-3.5 text-xs">
                <div
                  v-if="item.cinema_showtimes && item.cinema_showtimes.length > 0"
                  class="flex items-center justify-between text-stone-300 tabular-nums"
                >
                  <span class="inline-flex items-center gap-1 truncate">
                    <Clock class="h-3.5 w-3.5 shrink-0 text-amber-400" :stroke-width="1.75" />
                    {{ formatTimeWib(item.cinema_showtimes[0].start_time) }}
                  </span>
                  <span class="font-mono font-semibold text-white">
                    {{ formatRupiah(item.cinema_showtimes[0].ticket_price) }}
                  </span>
                </div>
                <div v-else class="text-stone-400">
                  {{ item.duration_min || '-' }} menit · Medan
                </div>

                <RouterLink
                  v-if="item.screening_status === 'NOW_SHOWING' && item.cinema_showtimes?.length"
                  :to="`/booking/${item.cinema_showtimes[0].showtime_id}`"
                  class="btn-primary w-full !py-2 text-xs"
                >
                  Pesan Kursi
                </RouterLink>
                <RouterLink
                  v-else
                  :to="`/anime/${item.anime_id}`"
                  class="btn-ghost-dark w-full !py-2 text-xs"
                >
                  Detail &amp; Jadwal
                </RouterLink>
              </div>
            </article>
          </div>
        </div>
      </div>
    </section>

    <!-- Pilihan Katalog Anime Terbaik (Warm Paper) -->
    <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
      <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p class="font-mono text-xs font-semibold uppercase tracking-wider text-crimson">
            Arsip Katalog · db_anitrack
          </p>
          <h2 class="mt-1 font-display text-2xl font-bold text-ink-heading sm:text-3xl">
            Anime Peringkat Tertinggi
          </h2>
        </div>
        <RouterLink to="/anime" class="btn-secondary self-start sm:self-auto">
          Jelajahi Seluruh Katalog
          <ArrowRight class="h-4 w-4" :stroke-width="1.75" />
        </RouterLink>
      </div>

      <div v-if="loading" class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-5">
        <div v-for="n in 10" :key="n" class="h-80 animate-pulse rounded-2xl bg-stone-200/70" />
      </div>

      <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-5">
        <AnimeCard v-for="anime in topCatalog" :key="anime.anime_id" :anime="anime" />
      </div>
    </section>
  </div>
</template>
