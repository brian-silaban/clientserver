<script setup>
import { RouterLink } from 'vue-router';
import { Star, Film } from 'lucide-vue-next';
import { POSTER_FALLBACK_SVG, getScreeningBadge, onPosterError } from '@/utils/formatters';

defineProps({
  anime: {
    type: Object,
    required: true,
  },
});
</script>

<template>
  <RouterLink
    :to="`/anime/${anime.anime_id}`"
    class="group flex h-full flex-col justify-between overflow-hidden rounded-2xl border border-stone-200/90 bg-white transition-all hover:border-stone-300 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-crimson"
  >
    <!-- Poster Rasio 2:3 dengan Gradient Scrim -->
    <div class="relative aspect-[2/3] w-full overflow-hidden bg-charcoal">
      <img
        :src="anime.image_url || POSTER_FALLBACK_SVG"
        :alt="`Poster ${anime.title}`"
        loading="lazy"
        class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-105"
        @error="onPosterError"
      />
      <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/25 to-transparent" />

      <!-- Badge Atas -->
      <div class="absolute left-2.5 right-2.5 top-2.5 flex items-center justify-between gap-1.5">
        <span
          v-if="anime.is_theatrical"
          class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-semibold shadow-sm"
          :class="getScreeningBadge(anime.screening_status).className"
        >
          <Film class="h-3 w-3" :stroke-width="2" />
          {{ getScreeningBadge(anime.screening_status).label }}
        </span>
        <span
          v-else
          class="rounded-md bg-black/65 px-2 py-0.5 text-[11px] font-medium text-stone-100 backdrop-blur-sm"
        >
          {{ anime.type_name || 'Anime' }}
        </span>

        <span
          v-if="anime.rating_code"
          class="rounded-md bg-black/65 px-1.5 py-0.5 font-mono text-[10px] font-medium text-stone-200 backdrop-blur-sm"
        >
          {{ anime.rating_code }}
        </span>
      </div>

      <!-- Skor di Bawah Poster -->
      <div class="absolute bottom-2.5 left-2.5 right-2.5 flex items-center justify-between">
        <span class="inline-flex items-center gap-1 rounded-md bg-black/70 px-2 py-0.5 text-xs font-semibold text-amber-400 tabular-nums backdrop-blur-sm">
          <Star class="h-3 w-3 fill-amber-400 text-amber-400" :stroke-width="1.75" />
          {{ anime.score || '-' }}
        </span>
        <span
          v-if="anime.premiered_year"
          class="text-[11px] font-medium text-stone-200 tabular-nums"
        >
          {{ anime.premiered_year }}
        </span>
      </div>
    </div>

    <!-- Info Metadata Katalog -->
    <div class="flex flex-1 flex-col justify-between gap-2 p-3.5">
      <div>
        <h3 class="line-clamp-2 font-display text-sm font-bold leading-snug text-ink-heading group-hover:text-crimson">
          {{ anime.title }}
        </h3>
        <p class="mt-0.5 line-clamp-1 text-xs text-ink-muted">
          {{ anime.title_english || anime.title_japanese || 'Arsip db_anitrack' }}
        </p>
      </div>

      <div class="flex items-center justify-between border-t border-stone-100 pt-2 text-[11px] text-ink-muted tabular-nums">
        <span>
          {{ anime.duration_min ? `${anime.duration_min} mnt` : anime.episodes ? `${anime.episodes} eps` : 'Info' }}
        </span>
        <span class="font-semibold text-crimson">Lihat Detail</span>
      </div>
    </div>
  </RouterLink>
</template>
