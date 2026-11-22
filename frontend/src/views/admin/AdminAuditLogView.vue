<script setup>
import { ref, reactive, onMounted } from 'vue';
import { Eye, X, ChevronLeft, ChevronRight, RefreshCw } from 'lucide-vue-next';
import { http } from '@/api/http';
import { useToastStore } from '@/stores/toast';
import { formatDateTimeId } from '@/utils/formatters';

const toast = useToastStore();
const logs = ref([]);
const pager = ref({ current_page: 1, total_pages: 1, total_data: 0 });
const loading = ref(true);
const selectedLog = ref(null);

const filters = reactive({
  table_name: '',
  operation: '',
  app_user: '',
  page: 1,
});

async function loadLogs() {
  loading.value = true;
  const params = new URLSearchParams({
    page: String(filters.page),
    per_page: '20',
  });
  if (filters.table_name) params.set('table_name', filters.table_name);
  if (filters.operation) params.set('operation', filters.operation);
  if (filters.app_user.trim()) params.set('app_user', filters.app_user.trim());

  try {
    const res = await http.get(`/admin/audit-logs?${params.toString()}`);
    logs.value = res.data || [];
    pager.value = res.pager || { current_page: 1, total_pages: 1, total_data: 0 };
  } catch (err) {
    toast.error(err.message || 'Gagal memuat audit log.');
  } finally {
    loading.value = false;
  }
}

function opBadgeClass(op) {
  if (op === 'INSERT') return 'bg-emeraldok-soft text-emeraldok';
  if (op === 'UPDATE') return 'bg-ambercine-soft text-ambercine';
  return 'bg-crimson-soft text-crimson';
}

onMounted(loadLogs);
</script>

<template>
  <div class="space-y-6">
    <div>
      <p class="font-mono text-xs font-semibold uppercase tracking-wider text-crimson">
        Trigger Generik anitrack.fn_audit() · Read-Only
      </p>
      <h1 class="mt-1 font-display text-2xl font-bold text-ink-heading">Audit Log Database</h1>
      <p class="mt-1 text-sm text-ink-muted">
        Rekaman otomatis setiap operasi `INSERT`, `UPDATE`, dan `DELETE` pada tabel inti `db_anitrack` (tanpa hak hapus/ubah).
      </p>
    </div>

    <!-- Filter Audit Log -->
    <form class="ticket-card grid grid-cols-1 gap-3 p-4 sm:grid-cols-12" @submit.prevent="filters.page = 1; loadLogs()">
      <div class="sm:col-span-4">
        <select v-model="filters.table_name" class="input-field text-xs" @change="filters.page = 1; loadLogs()">
          <option value="">Semua Tabel</option>
          <option value="anime">anime</option>
          <option value="anime_cinema_map">anime_cinema_map</option>
          <option value="app_user">app_user</option>
          <option value="user_anime_list">user_anime_list</option>
          <option value="cinema_bookings">cinema_bookings</option>
          <option value="cinema_booking_seats">cinema_booking_seats</option>
        </select>
      </div>
      <div class="sm:col-span-3">
        <select v-model="filters.operation" class="input-field text-xs" @change="filters.page = 1; loadLogs()">
          <option value="">Semua Operasi</option>
          <option value="INSERT">INSERT</option>
          <option value="UPDATE">UPDATE</option>
          <option value="DELETE">DELETE</option>
        </select>
      </div>
      <div class="sm:col-span-3">
        <input v-model="filters.app_user" type="text" placeholder="Filter app_user..." class="input-field text-xs" />
      </div>
      <div class="flex gap-2 sm:col-span-2">
        <button type="submit" class="btn-primary flex-1 !py-2 text-xs">Filter</button>
        <button type="button" class="btn-secondary !px-2.5 !py-2 text-xs" @click="loadLogs">
          <RefreshCw class="h-3.5 w-3.5" />
        </button>
      </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-stone-200/90 bg-white">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-stone-200 text-left text-xs">
          <thead class="bg-subtle/60 font-semibold uppercase text-ink-muted">
            <tr>
              <th class="px-4 py-3">ID</th>
              <th class="px-4 py-3">Waktu</th>
              <th class="px-4 py-3">Tabel</th>
              <th class="px-4 py-3">Operasi</th>
              <th class="px-4 py-3">PK</th>
              <th class="px-4 py-3">App User / DB User</th>
              <th class="px-4 py-3 text-right">Diff JSON</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            <tr v-if="loading">
              <td colspan="7" class="px-4 py-8 text-center text-ink-muted">Memuat audit log...</td>
            </tr>
            <tr v-for="row in logs" v-else :key="row.id" class="hover:bg-subtle/30">
              <td class="px-4 py-3 font-mono text-ink-muted tabular-nums">#{{ row.id }}</td>
              <td class="px-4 py-3 tabular-nums">{{ formatDateTimeId(row.changed_at) }}</td>
              <td class="px-4 py-3 font-mono font-semibold text-ink-heading">{{ row.table_name }}</td>
              <td class="px-4 py-3">
                <span class="rounded px-2 py-0.5 font-mono text-[11px] font-semibold" :class="opBadgeClass(row.operation)">
                  {{ row.operation }}
                </span>
              </td>
              <td class="px-4 py-3 font-mono text-ink-body">{{ row.row_pk }}</td>
              <td class="px-4 py-3 font-mono">
                <span class="text-ink-heading">{{ row.app_user || '-' }}</span>
                <span class="block text-[11px] text-ink-muted">db: {{ row.changed_by }}</span>
              </td>
              <td class="px-4 py-3 text-right">
                <button type="button" class="btn-secondary !px-2.5 !py-1 text-xs" @click="selectedLog = row">
                  <Eye class="h-3.5 w-3.5" :stroke-width="1.75" />
                  Detail
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex items-center justify-between border-t border-stone-200 px-4 py-3 text-xs text-ink-muted tabular-nums">
        <span>Halaman {{ pager.current_page }} dari {{ pager.total_pages }} ({{ pager.total_data }} catatan)</span>
        <div class="flex gap-2">
          <button type="button" class="btn-secondary !px-3 !py-1.5" :disabled="filters.page <= 1" @click="filters.page--; loadLogs()">
            <ChevronLeft class="h-3.5 w-3.5" />
          </button>
          <button type="button" class="btn-secondary !px-3 !py-1.5" :disabled="filters.page >= pager.total_pages" @click="filters.page++; loadLogs()">
            <ChevronRight class="h-3.5 w-3.5" />
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Diff JSON old_data vs new_data -->
    <div v-if="selectedLog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
      <div class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-4 flex items-center justify-between border-b border-stone-200 pb-3">
          <div>
            <h2 class="font-display text-lg font-bold text-ink-heading">
              Detail Audit #{{ selectedLog.id }} — {{ selectedLog.table_name }} ({{ selectedLog.operation }})
            </h2>
            <p class="font-mono text-xs text-ink-muted">
              row_pk: {{ selectedLog.row_pk }} · app_user: {{ selectedLog.app_user || '-' }} · {{ formatDateTimeId(selectedLog.changed_at) }}
            </p>
          </div>
          <button type="button" class="rounded p-1 text-ink-muted hover:bg-subtle" @click="selectedLog = null">
            <X class="h-5 w-5" />
          </button>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <p class="mb-1.5 font-mono text-xs font-semibold uppercase text-crimson">old_data (Sebelum)</p>
            <pre class="max-h-96 overflow-auto rounded-xl bg-charcoal p-4 font-mono text-xs text-stone-200">{{ selectedLog.old_data ? JSON.stringify(selectedLog.old_data, null, 2) : 'null (INSERT baru)' }}</pre>
          </div>
          <div>
            <p class="mb-1.5 font-mono text-xs font-semibold uppercase text-emeraldok">new_data (Sesudah)</p>
            <pre class="max-h-96 overflow-auto rounded-xl bg-charcoal p-4 font-mono text-xs text-stone-200">{{ selectedLog.new_data ? JSON.stringify(selectedLog.new_data, null, 2) : 'null (DELETE)' }}</pre>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
