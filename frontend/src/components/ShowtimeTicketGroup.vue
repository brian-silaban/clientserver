<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { MapPin, Clock, Armchair, ArrowRight } from 'lucide-vue-next';
import { formatRupiah, formatTimeWib, formatDateId } from '@/utils/formatters';

const props = defineProps({
  showtimes: {
    type: Array,
    default: () => [],
  },
  screeningStatus: {
    type: String,
    default: 'NOW_SHOWING',
  },
});

const groupedVenues = computed(() => {
  const groups = new Map();

  for (const st of props.showtimes) {
    const key = st.cinema_name || 'Bioskop Medan';
    if (!groups.has(key)) {
      groups.set(key, {
        cinemaName: st.cinema_name || 'Bioskop Medan',
        kecamatan: st.kecamatan || 'Medan',
        address: st.address || 'Kota Medan, Sumatera Utara',
        items: [],
      });
    }
    groups.get(key).items.push(st);
  }

  return Array.from(groups.values());
});
</script>

<template>
  <div v-if="groupedVenues.length === 0" class="rounded-xl border border-dashed border-stone-300 bg-subtle/50 p-6 text-center text-sm text-ink-muted">
    Belum ada jadwal tayang bioskop Medan yang terdaftar untuk film ini.
  </div>

  <div v-else class="space-y-4">
    <div
      v-for="venue in groupedVenues"
      :key="venue.cinemaName"
      class="overflow-hidden rounded-2xl border border-stone-200/90 bg-white"
    >
      <!-- Header Lokasi Bioskop -->
      <div class="flex flex-wrap items-start justify-between gap-2 border-b border-dashed border-stone-200 bg-subtle/40 px-4 py-3.5">
        <div>
          <h4 class="font-display text-base font-bold text-ink-heading">{{ venue.cinemaName }}</h4>
          <p class="mt-0.5 flex items-center gap-1 text-xs text-ink-muted">
            <MapPin class="h-3.5 w-3.5 shrink-0 text-crimson" :stroke-width="1.75" />
            {{ venue.address }}
          </p>
        </div>
        <span class="rounded-md border border-stone-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-ink-body">
          {{ venue.kecamatan }}
        </span>
      </div>

      <!-- Baris Jam Tayang Bergaya Ticket Stub -->
      <div class="divide-y divide-stone-100">
        <div
          v-for="item in venue.items"
          :key="item.showtime_id"
          class="flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between"
        >
          <div class="space-y-1">
            <div class="flex flex-wrap items-center gap-2">
              <span class="text-sm font-semibold text-ink-heading">{{ item.studio_name }}</span>
              <span class="rounded bg-subtle px-2 py-0.5 text-[11px] font-medium text-ink-body">
                {{ item.studio_type }}
              </span>
            </div>
            <div class="flex flex-wrap items-center gap-3 text-xs text-ink-muted tabular-nums">
              <span>{{ formatDateId(item.show_date) }}</span>
              <span class="inline-flex items-center gap-1 font-semibold text-ink-heading">
                <Clock class="h-3.5 w-3.5 text-ambercine" :stroke-width="1.75" />
                {{ formatTimeWib(item.start_time) }}
              </span>
              <span class="inline-flex items-center gap-1 text-emeraldok">
                <Armchair class="h-3.5 w-3.5" :stroke-width="1.75" />
                Tersedia {{ item.seats_available }}/{{ item.total_capacity }}
              </span>
            </div>
          </div>

          <div class="flex items-center justify-between gap-3 sm:justify-end">
            <span class="font-mono text-sm font-semibold text-ink-heading tabular-nums">
              {{ formatRupiah(item.ticket_price) }}
            </span>

            <span
              v-if="screeningStatus === 'UPCOMING' || item.screening_status === 'UPCOMING'"
              class="rounded-lg border border-amber-200 bg-ambercine-soft px-3 py-1.5 text-xs font-semibold text-ambercine"
            >
              Akan Tayang
            </span>
            <RouterLink
              v-else
              :to="`/booking/${item.showtime_id}`"
              class="btn-primary !px-3.5 !py-2 text-xs"
            >
              Pilih Kursi
              <ArrowRight class="h-3.5 w-3.5" :stroke-width="1.75" />
            </RouterLink>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
