<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { Ticket, CheckCircle2, XCircle, MapPin, Clock, Film } from 'lucide-vue-next';
import { http } from '@/api/http';
import { useToastStore } from '@/stores/toast';
import {
  POSTER_FALLBACK_SVG,
  formatRupiah,
  formatTimeWib,
  formatDateId,
  formatDateTimeId,
  getPaymentBadge,
  onPosterError,
} from '@/utils/formatters';

const toast = useToastStore();
const bookings = ref([]);
const loading = ref(true);
const busyId = ref(null);

async function loadBookings() {
  loading.value = true;
  try {
    const res = await http.get('/bookings');
    bookings.value = res.data || [];
  } catch (err) {
    toast.error(err.message || 'Gagal memuat riwayat tiket.');
  } finally {
    loading.value = false;
  }
}

async function handlePay(booking) {
  busyId.value = booking.booking_id;
  try {
    await http.post(`/bookings/${booking.booking_id}/pay`);
    toast.success(`Pembayaran tiket ${booking.booking_code} berhasil dikonfirmasi!`);
    await loadBookings();
  } catch (err) {
    toast.error(err.message || 'Gagal memproses pembayaran.');
  } finally {
    busyId.value = null;
  }
}

async function handleCancel(booking) {
  busyId.value = booking.booking_id;
  try {
    await http.post(`/bookings/${booking.booking_id}/cancel`);
    toast.info(`Pesanan ${booking.booking_code} dibatalkan dan kursi telah dilepaskan.`);
    await loadBookings();
  } catch (err) {
    toast.error(err.message || 'Gagal membatalkan pesanan.');
  } finally {
    busyId.value = null;
  }
}

onMounted(loadBookings);
</script>

<template>
  <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="mb-8 flex flex-col gap-2 border-b border-stone-200 pb-5 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="font-mono text-xs font-semibold uppercase tracking-wider text-crimson">
          E-Ticket Pribadi · Dilindungi RLS PostgreSQL
        </p>
        <h1 class="mt-1 font-display text-3xl font-bold text-ink-heading">Tiket Saya</h1>
      </div>
      <RouterLink to="/bioskop" class="btn-primary self-start sm:self-auto">
        <Film class="h-4 w-4" :stroke-width="1.75" />
        Pesan Tiket Lain
      </RouterLink>
    </div>

    <div v-if="loading" class="space-y-4">
      <div v-for="n in 3" :key="n" class="h-40 animate-pulse rounded-2xl bg-stone-200/70" />
    </div>

    <div
      v-else-if="bookings.length === 0"
      class="rounded-2xl border border-dashed border-stone-300 bg-white p-12 text-center"
    >
      <Ticket class="mx-auto h-10 w-10 text-stone-400" :stroke-width="1.5" />
      <p class="mt-3 font-display text-lg font-bold text-ink-heading">Belum Ada Riwayat Pemesanan Tiket</p>
      <p class="mt-1 text-sm text-ink-muted">
        Pilih film anime yang sedang tayang di bioskop Medan dan pesan kursi favoritmu.
      </p>
      <RouterLink to="/bioskop" class="btn-primary mt-5">
        Lihat Jadwal Bioskop Medan
      </RouterLink>
    </div>

    <div v-else class="space-y-5">
      <article
        v-for="item in bookings"
        :key="item.booking_id"
        class="overflow-hidden rounded-2xl border border-stone-200/90 bg-white"
      >
        <div class="grid md:grid-cols-12">
          <!-- Bagian Kiri: Info Tiket -->
          <div class="flex flex-col gap-4 p-5 sm:flex-row md:col-span-8 md:border-r md:border-dashed md:border-stone-300">
            <img
              :src="item.anime_image_url || POSTER_FALLBACK_SVG"
              :alt="item.anime_title"
              class="aspect-[2/3] w-24 shrink-0 self-start rounded-xl object-cover"
              @error="onPosterError"
            />
            <div class="min-w-0 flex-1 space-y-2">
              <div class="flex flex-wrap items-center gap-2">
                <span class="rounded bg-charcoal px-2 py-0.5 font-mono text-xs font-semibold text-white">
                  {{ item.booking_code }}
                </span>
                <span
                  class="rounded-md border px-2.5 py-0.5 text-xs font-semibold"
                  :class="getPaymentBadge(item.payment_status).className"
                >
                  {{ getPaymentBadge(item.payment_status).label }}
                </span>
              </div>

              <h2 class="font-display text-xl font-bold text-ink-heading">
                {{ item.anime_title }}
              </h2>

              <div class="space-y-1 text-xs text-ink-body">
                <p class="flex items-center gap-1.5">
                  <MapPin class="h-3.5 w-3.5 shrink-0 text-crimson" :stroke-width="1.75" />
                  <strong>{{ item.cinema_name }}</strong> · {{ item.studio_name }} ({{ item.studio_type }})
                </p>
                <p class="flex items-center gap-1.5 tabular-nums">
                  <Clock class="h-3.5 w-3.5 shrink-0 text-ambercine" :stroke-width="1.75" />
                  {{ formatDateId(item.show_date) }} · {{ formatTimeWib(item.start_time) }}
                </p>
              </div>

              <div class="flex flex-wrap items-center gap-3 pt-1 text-xs tabular-nums">
                <span class="rounded-lg bg-subtle px-2.5 py-1 font-mono font-semibold text-ink-heading">
                  Kursi: {{ item.seat_labels }} ({{ item.seat_count }} tiket)
                </span>
                <span class="text-ink-muted">
                  Dipesan: {{ formatDateTimeId(item.created_at) }}
                </span>
              </div>
            </div>
          </div>

          <!-- Bagian Kanan: Sobekan Harga & Aksi -->
          <div class="flex flex-col justify-between gap-4 bg-subtle/40 p-5 md:col-span-4">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Total Pembayaran</p>
              <p class="mt-1 font-mono text-2xl font-bold text-ink-heading tabular-nums">
                {{ formatRupiah(item.total_price) }}
              </p>
              <p v-if="item.paid_at" class="mt-1 text-[11px] text-emeraldok">
                Lunas pada {{ formatDateTimeId(item.paid_at) }}
              </p>
              <p v-else-if="item.payment_status === 'PENDING'" class="mt-1 text-[11px] text-ambercine">
                Selesaikan konfirmasi bayar untuk mengaktifkan e-ticket.
              </p>
            </div>

            <div v-if="item.payment_status === 'PENDING'" class="flex flex-wrap gap-2">
              <button
                type="button"
                class="btn-primary flex-1 text-xs"
                :disabled="busyId === item.booking_id"
                @click="handlePay(item)"
              >
                <CheckCircle2 class="h-4 w-4" :stroke-width="1.75" />
                Konfirmasi Bayar
              </button>
              <button
                type="button"
                class="btn-secondary text-xs text-crimson hover:bg-crimson-soft/40"
                :disabled="busyId === item.booking_id"
                @click="handleCancel(item)"
              >
                <XCircle class="h-4 w-4" :stroke-width="1.75" />
                Batal
              </button>
            </div>
          </div>
        </div>
      </article>
    </div>
  </div>
</template>
