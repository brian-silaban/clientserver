<script setup>
import { ref, onMounted } from 'vue';
import { RefreshCw } from 'lucide-vue-next';
import { http } from '@/api/http';
import { useToastStore } from '@/stores/toast';
import {
  formatRupiah,
  formatDateId,
  formatTimeWib,
  getPaymentBadge,
} from '@/utils/formatters';

const toast = useToastStore();
const bookings = ref([]);
const statusFilter = ref('');
const loading = ref(true);
const busyId = ref(null);

async function loadBookings() {
  loading.value = true;
  const query = statusFilter.value ? `?status=${statusFilter.value}` : '';
  try {
    const res = await http.get(`/admin/bookings${query}`);
    bookings.value = res.data || [];
  } catch (err) {
    toast.error(err.message || 'Gagal memuat daftar transaksi tiket.');
  } finally {
    loading.value = false;
  }
}

async function changeStatus(row, nextStatus) {
  busyId.value = row.booking_id;
  try {
    await http.patch(`/admin/bookings/${row.booking_id}/status`, {
      payment_status: nextStatus,
    });
    toast.success(`Status booking ${row.booking_code} diubah ke ${nextStatus}.`);
    await loadBookings();
  } catch (err) {
    toast.error(err.message || 'Gagal mengubah status booking.');
  } finally {
    busyId.value = null;
  }
}

onMounted(loadBookings);
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <p class="font-mono text-xs font-semibold uppercase tracking-wider text-crimson">
          Monitoring Transaksi · RLS Konteks Admin
        </p>
        <h1 class="mt-1 font-display text-2xl font-bold text-ink-heading">Transaksi Tiket Bioskop</h1>
      </div>

      <div class="flex items-center gap-2">
        <select v-model="statusFilter" class="input-field !w-44 !py-2 text-xs" @change="loadBookings">
          <option value="">Semua Status</option>
          <option value="PENDING">PENDING</option>
          <option value="PAID">PAID</option>
          <option value="CANCELLED">CANCELLED</option>
          <option value="EXPIRED">EXPIRED</option>
        </select>
        <button type="button" class="btn-secondary !py-2 text-xs" @click="loadBookings">
          <RefreshCw class="h-3.5 w-3.5" :stroke-width="1.75" />
        </button>
      </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-stone-200/90 bg-white">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-stone-200 text-left text-sm">
          <thead class="bg-subtle/60 text-xs font-semibold uppercase text-ink-muted">
            <tr>
              <th class="px-4 py-3">Kode</th>
              <th class="px-4 py-3">Pemesan</th>
              <th class="px-4 py-3">Anime &amp; Jadwal</th>
              <th class="px-4 py-3">Kursi</th>
              <th class="px-4 py-3">Total</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3 text-right">Ubah Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            <tr v-if="loading">
              <td colspan="7" class="px-4 py-8 text-center text-ink-muted">Memuat transaksi...</td>
            </tr>
            <tr v-else-if="bookings.length === 0">
              <td colspan="7" class="px-4 py-8 text-center text-ink-muted">Belum ada transaksi booking.</td>
            </tr>
            <tr v-for="row in bookings" v-else :key="row.booking_id">
              <td class="px-4 py-3 font-mono text-xs font-semibold text-ink-heading">{{ row.booking_code }}</td>
              <td class="px-4 py-3 text-xs">
                <p class="font-semibold text-ink-heading">{{ row.username }}</p>
                <p class="text-ink-muted">{{ row.email }}</p>
              </td>
              <td class="px-4 py-3 text-xs">
                <p class="font-semibold text-ink-heading">{{ row.anime_title }}</p>
                <p class="text-ink-muted tabular-nums">
                  {{ row.cinema_name }} · {{ formatDateId(row.show_date) }} {{ formatTimeWib(row.start_time) }}
                </p>
              </td>
              <td class="px-4 py-3 font-mono text-xs">{{ row.seat_labels }}</td>
              <td class="px-4 py-3 font-mono text-xs font-semibold tabular-nums">{{ formatRupiah(row.total_price) }}</td>
              <td class="px-4 py-3">
                <span
                  class="rounded-md border px-2 py-0.5 text-xs font-semibold"
                  :class="getPaymentBadge(row.payment_status).className"
                >
                  {{ getPaymentBadge(row.payment_status).label }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="inline-flex gap-1.5">
                  <button
                    v-if="row.payment_status !== 'PAID'"
                    type="button"
                    class="btn-primary !px-2.5 !py-1 text-xs"
                    :disabled="busyId === row.booking_id"
                    @click="changeStatus(row, 'PAID')"
                  >
                    Tandai Lunas
                  </button>
                  <button
                    v-if="row.payment_status !== 'CANCELLED'"
                    type="button"
                    class="btn-secondary !px-2.5 !py-1 text-xs text-crimson"
                    :disabled="busyId === row.booking_id"
                    @click="changeStatus(row, 'CANCELLED')"
                  >
                    Batalkan
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
