<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import {
  ArrowLeft,
  Armchair,
  X,
  Clock,
  MapPin,
  Ticket,
  LogIn,
  RefreshCw,
} from 'lucide-vue-next';
import { http } from '@/api/http';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';
import { formatRupiah, formatTimeWib, formatDateId } from '@/utils/formatters';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const toast = useToastStore();

const showtime = ref(null);
const seats = ref([]);
const summary = ref({ total_seats: 0, booked_count: 0, available_count: 0 });
const selectedSeatIds = ref([]);
const loading = ref(true);
const submitting = ref(false);
const errorMsg = ref('');

// Kelompokkan kursi berdasarkan huruf baris (A, B, C, ...)
const seatRows = computed(() => {
  const grouped = new Map();
  for (const seat of seats.value) {
    const label = seat.row_label || 'A';
    if (!grouped.has(label)) {
      grouped.set(label, []);
    }
    grouped.get(label).push(seat);
  }
  return Array.from(grouped.entries()).map(([rowLabel, rowSeats]) => ({
    rowLabel,
    seats: rowSeats,
  }));
});

const selectedSeatObjects = computed(() => {
  const idSet = new Set(selectedSeatIds.value);
  return seats.value.filter((s) => idSet.has(s.seat_id));
});

const totalPrice = computed(() => {
  const unitPrice = Number(showtime.value?.ticket_price || 0);
  return unitPrice * selectedSeatIds.value.length;
});

async function loadSeats() {
  loading.value = true;
  errorMsg.value = '';
  try {
    const res = await http.get(`/cinema/showtimes/${route.params.showtimeId}/seats`);
    showtime.value = res.data.showtime;
    seats.value = res.data.seats || [];
    summary.value = res.data.summary || { total_seats: 0, booked_count: 0, available_count: 0 };

    // Bersihkan kursi terpilih jika ternyata sudah terisi saat refresh
    const availableSet = new Set(seats.value.filter((s) => !s.is_booked).map((s) => s.seat_id));
    selectedSeatIds.value = selectedSeatIds.value.filter((id) => availableSet.has(id));
  } catch (err) {
    errorMsg.value = err.message || 'Gagal memuat denah kursi studio.';
  } finally {
    loading.value = false;
  }
}

function toggleSeat(seat) {
  if (seat.is_booked) return;

  const idx = selectedSeatIds.value.indexOf(seat.seat_id);
  if (idx >= 0) {
    selectedSeatIds.value.splice(idx, 1);
    return;
  }

  if (selectedSeatIds.value.length >= 6) {
    toast.info('Maksimal 6 kursi per transaksi pemesanan.');
    return;
  }

  selectedSeatIds.value.push(seat.seat_id);
}

async function submitBooking() {
  if (!auth.isAuthenticated) {
    router.push({ name: 'login', query: { redirect: route.fullPath } });
    return;
  }

  if (selectedSeatIds.value.length === 0) {
    toast.info('Pilih minimal 1 kursi terlebih dahulu.');
    return;
  }

  submitting.value = true;
  try {
    const res = await http.post('/bookings', {
      showtime_id: Number(route.params.showtimeId),
      seat_ids: selectedSeatIds.value,
    });
    toast.success(`Pesanan ${res.data.booking_code} berhasil dibuat! Silakan konfirmasi pembayaran.`);
    router.push({ name: 'my-tickets' });
  } catch (err) {
    toast.error(err.message || 'Gagal memesan kursi.');
    // Jika terjadi konflik 409 (kursi baru saja diambil orang lain), muat ulang denah kursi
    if (err.status === 409) {
      await loadSeats();
    }
  } finally {
    submitting.value = false;
  }
}

onMounted(loadSeats);
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
      <RouterLink
        to="/bioskop"
        class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-muted hover:text-crimson"
      >
        <ArrowLeft class="h-4 w-4" :stroke-width="1.75" />
        Kembali ke Jadwal Bioskop
      </RouterLink>

      <button type="button" class="btn-secondary !py-1.5 text-xs" :disabled="loading" @click="loadSeats">
        <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': loading }" :stroke-width="1.75" />
        Segarkan Status Kursi
      </button>
    </div>

    <div v-if="loading && !showtime" class="grid gap-8 lg:grid-cols-12">
      <div class="h-96 animate-pulse rounded-2xl bg-stone-200/70 lg:col-span-8" />
      <div class="h-80 animate-pulse rounded-2xl bg-stone-200/70 lg:col-span-4" />
    </div>

    <div v-else-if="errorMsg" class="rounded-2xl border border-rose-200 bg-white p-8 text-center text-sm text-crimson">
      {{ errorMsg }}
    </div>

    <div v-else-if="showtime" class="grid gap-8 lg:grid-cols-12">
      <!-- Kolom Kiri: Denah Kursi Interaktif -->
      <div class="ticket-card p-5 sm:p-7 lg:col-span-8">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3 border-b border-stone-200 pb-4">
          <div>
            <p class="font-mono text-xs font-semibold uppercase tracking-wider text-crimson">
              Pilih Kursi Studio
            </p>
            <h1 class="mt-1 font-display text-2xl font-bold text-ink-heading">
              {{ showtime.anime_title || showtime.movie_title }}
            </h1>
            <p class="mt-1 flex flex-wrap items-center gap-3 text-xs text-ink-muted tabular-nums">
              <span class="inline-flex items-center gap-1">
                <MapPin class="h-3.5 w-3.5 text-crimson" :stroke-width="1.75" />
                {{ showtime.cinema_name }} · {{ showtime.studio_name }} ({{ showtime.studio_type }})
              </span>
              <span class="inline-flex items-center gap-1 font-semibold text-ink-heading">
                <Clock class="h-3.5 w-3.5 text-ambercine" :stroke-width="1.75" />
                {{ formatDateId(showtime.show_date) }} · {{ formatTimeWib(showtime.start_time) }}
              </span>
            </p>
          </div>
          <span class="rounded-lg bg-emeraldok-soft px-3 py-1.5 text-xs font-semibold text-emeraldok tabular-nums">
            {{ summary.available_count }} / {{ summary.total_seats }} Kursi Tersedia
          </span>
        </div>

        <!-- Visual Layar Bioskop -->
        <div class="mx-auto mb-8 max-w-lg text-center">
          <div class="mx-auto h-2.5 w-full rounded-t-full bg-charcoal shadow-sm" />
          <p class="mt-2 font-mono text-[11px] uppercase tracking-[0.25em] text-ink-muted">
            Layar Studio
          </p>
        </div>

        <!-- Grid Kursi per Baris -->
        <div class="overflow-x-auto pb-2">
          <div class="mx-auto flex min-w-[320px] max-w-xl flex-col gap-3">
            <div
              v-for="row in seatRows"
              :key="row.rowLabel"
              class="flex items-center justify-center gap-2 sm:gap-2.5"
            >
              <span class="w-6 shrink-0 text-center font-mono text-xs font-bold text-ink-muted">
                {{ row.rowLabel }}
              </span>

              <div class="flex flex-wrap items-center justify-center gap-2">
                <button
                  v-for="seat in row.seats"
                  :key="seat.seat_id"
                  type="button"
                  :disabled="seat.is_booked"
                  :aria-label="`Kursi ${seat.seat_code} ${seat.is_booked ? 'sudah terisi' : selectedSeatIds.includes(seat.seat_id) ? 'dipilih' : 'tersedia'}`"
                  class="flex h-10 w-10 items-center justify-center rounded-lg border font-mono text-xs font-semibold transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-crimson sm:h-11 sm:w-11"
                  :class="
                    seat.is_booked
                      ? 'cursor-not-allowed border-stone-200 bg-stone-200/80 text-stone-400'
                      : selectedSeatIds.includes(seat.seat_id)
                        ? 'border-crimson bg-crimson text-white shadow-sm'
                        : 'border-stone-300 bg-white text-ink-heading hover:border-crimson hover:bg-crimson-soft/40 active:scale-95'
                  "
                  @click="toggleSeat(seat)"
                >
                  <X v-if="seat.is_booked" class="h-4 w-4 text-stone-400" :stroke-width="2" />
                  <span v-else>{{ seat.seat_code }}</span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Legenda Status Kursi -->
        <div class="mt-8 flex flex-wrap items-center justify-center gap-6 border-t border-stone-200 pt-5 text-xs text-ink-body">
          <div class="flex items-center gap-2">
            <span class="inline-block h-5 w-5 rounded border border-stone-300 bg-white" />
            <span>Tersedia</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="inline-block h-5 w-5 rounded border border-crimson bg-crimson" />
            <span>Dipilih Anda</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="inline-flex h-5 w-5 items-center justify-center rounded border border-stone-200 bg-stone-200/80">
              <X class="h-3 w-3 text-stone-500" :stroke-width="2" />
            </span>
            <span>Sudah Terisi</span>
          </div>
        </div>
      </div>

      <!-- Kolom Kanan: Ringkasan Tiket & Checkout -->
      <div class="lg:col-span-4">
        <div class="ticket-card sticky top-24 overflow-hidden">
          <div class="border-b border-dashed border-stone-300 bg-charcoal px-5 py-4 text-white">
            <p class="font-mono text-[11px] uppercase tracking-wider text-amber-400">
              Ringkasan Pemesanan
            </p>
            <h2 class="mt-1 font-display text-lg font-bold text-white">
              {{ showtime.anime_title || showtime.movie_title }}
            </h2>
            <p class="mt-0.5 text-xs text-stone-300">
              {{ showtime.cinema_name }} · {{ showtime.studio_name }}
            </p>
          </div>

          <div class="space-y-4 p-5 text-sm">
            <dl class="space-y-2.5 text-xs">
              <div class="flex justify-between">
                <dt class="text-ink-muted">Tanggal &amp; Jam</dt>
                <dd class="font-semibold text-ink-heading tabular-nums">
                  {{ formatDateId(showtime.show_date) }}, {{ formatTimeWib(showtime.start_time) }}
                </dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-ink-muted">Harga per Kursi</dt>
                <dd class="font-mono font-semibold text-ink-heading tabular-nums">
                  {{ formatRupiah(showtime.ticket_price) }}
                </dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-ink-muted">Kursi Dipilih ({{ selectedSeatObjects.length }})</dt>
                <dd class="font-mono font-semibold text-crimson">
                  {{ selectedSeatObjects.length ? selectedSeatObjects.map((s) => s.seat_code).join(', ') : 'Belum dipilih' }}
                </dd>
              </div>
            </dl>

            <div class="border-t border-dashed border-stone-200 pt-4">
              <div class="flex items-baseline justify-between">
                <span class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Total Bayar</span>
                <span class="font-mono text-xl font-bold text-ink-heading tabular-nums">
                  {{ formatRupiah(totalPrice) }}
                </span>
              </div>
            </div>

            <!-- Banner Login untuk Guest -->
            <div
              v-if="!auth.isAuthenticated"
              class="rounded-xl border border-amber-200 bg-ambercine-soft/60 p-3.5 text-xs text-amber-950"
            >
              <p class="font-semibold">Perlu Akun untuk Memesan Tiket</p>
              <p class="mt-1 leading-relaxed">
                Anda dapat melihat ketersediaan kursi secara bebas. Untuk mengunci kursi dan menerbitkan e-ticket, silakan masuk terlebih dahulu.
              </p>
              <RouterLink
                :to="{ name: 'login', query: { redirect: route.fullPath } }"
                class="btn-primary mt-3 w-full text-xs"
              >
                <LogIn class="h-4 w-4" :stroke-width="1.75" />
                Masuk / Daftar untuk Pesan
              </RouterLink>
            </div>

            <!-- Tombol Checkout untuk Member -->
            <div v-else class="space-y-2.5">
              <button
                type="button"
                class="btn-primary w-full"
                :disabled="selectedSeatIds.length === 0 || submitting"
                @click="submitBooking"
              >
                <Ticket class="h-4 w-4" :stroke-width="1.75" />
                {{ submitting ? 'Mengunci Kursi (ACID)...' : 'Pesan Tiket Sekarang' }}
              </button>
              <p class="flex items-center justify-center gap-1.5 text-[11px] text-ink-muted">
                <Armchair class="h-3.5 w-3.5" :stroke-width="1.75" />
                Dilindungi transaksi ACID &amp; kunci kursi anti double-booking.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
