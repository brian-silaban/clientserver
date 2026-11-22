<script setup>
import { ref, reactive, onMounted } from 'vue';
import { Plus, Trash2, Save } from 'lucide-vue-next';
import { http } from '@/api/http';
import { useToastStore } from '@/stores/toast';
import { getScreeningBadge } from '@/utils/formatters';

const toast = useToastStore();
const mappings = ref([]);
const loading = ref(true);
const creating = ref(false);
const busyMapId = ref(null);

const newForm = reactive({
  anime_id: '',
  cinema_movie_id: '',
  screening_status: 'NOW_SHOWING',
  is_featured: false,
  release_note: '',
});

async function loadMappings() {
  loading.value = true;
  try {
    const res = await http.get('/admin/theatrical');
    mappings.value = (res.data || []).map((row) => ({ ...row }));
  } catch (err) {
    toast.error(err.message || 'Gagal memuat pemetaan anime bioskop.');
  } finally {
    loading.value = false;
  }
}

async function createMapping() {
  creating.value = true;
  try {
    await http.post('/admin/theatrical', {
      anime_id: Number(newForm.anime_id),
      cinema_movie_id: Number(newForm.cinema_movie_id),
      screening_status: newForm.screening_status,
      is_featured: newForm.is_featured,
      release_note: newForm.release_note,
    });
    toast.success('Pemetaan anime ke bioskop berhasil ditambahkan.');
    Object.assign(newForm, {
      anime_id: '',
      cinema_movie_id: '',
      screening_status: 'NOW_SHOWING',
      is_featured: false,
      release_note: '',
    });
    await loadMappings();
  } catch (err) {
    toast.error(err.message || 'Gagal menambahkan pemetaan.');
  } finally {
    creating.value = false;
  }
}

async function updateMapping(row) {
  busyMapId.value = row.map_id;
  try {
    await http.put(`/admin/theatrical/${row.map_id}`, {
      screening_status: row.screening_status,
      is_featured: row.is_featured,
      release_note: row.release_note || '',
      version: row.version,
    });
    toast.success(`Pemetaan "${row.anime_title}" diperbarui.`);
    await loadMappings();
  } catch (err) {
    toast.error(err.message || 'Gagal memperbarui pemetaan.');
  } finally {
    busyMapId.value = null;
  }
}

async function deleteMapping(row) {
  if (!window.confirm(`Hapus pemetaan bioskop untuk "${row.anime_title}"?`)) return;
  busyMapId.value = row.map_id;
  try {
    await http.delete(`/admin/theatrical/${row.map_id}`);
    toast.info('Pemetaan berhasil dihapus.');
    await loadMappings();
  } catch (err) {
    toast.error(err.message || 'Gagal menghapus pemetaan.');
  } finally {
    busyMapId.value = null;
  }
}

onMounted(loadMappings);
</script>

<template>
  <div class="space-y-6">
    <div>
      <p class="font-mono text-xs font-semibold uppercase tracking-wider text-crimson">
        Tabel Dinamis anitrack.anime_cinema_map
      </p>
      <h1 class="mt-1 font-display text-2xl font-bold text-ink-heading">
        Pemetaan Anime Tayang di Bioskop Medan
      </h1>
      <p class="mt-1 text-sm text-ink-muted">
        Mengatur judul anime mana yang ditampilkan di halaman publik Bioskop Medan (`NOW_SHOWING` / `UPCOMING`) tanpa menyentuh database partner.
      </p>
    </div>

    <!-- Form Tambah Pemetaan -->
    <form class="ticket-card grid grid-cols-1 gap-3 p-5 sm:grid-cols-12" @submit.prevent="createMapping">
      <div class="sm:col-span-2">
        <label class="mb-1 block text-xs font-semibold text-ink-muted">Anime ID *</label>
        <input v-model="newForm.anime_id" type="number" required placeholder="32281" class="input-field tabular-nums" />
      </div>
      <div class="sm:col-span-3">
        <label class="mb-1 block text-xs font-semibold text-ink-muted">Cinema Movie ID Partner *</label>
        <input v-model="newForm.cinema_movie_id" type="number" required placeholder="372058" class="input-field tabular-nums" />
      </div>
      <div class="sm:col-span-2">
        <label class="mb-1 block text-xs font-semibold text-ink-muted">Status Tayang</label>
        <select v-model="newForm.screening_status" class="input-field">
          <option value="NOW_SHOWING">NOW_SHOWING</option>
          <option value="UPCOMING">UPCOMING</option>
          <option value="ENDED">ENDED</option>
        </select>
      </div>
      <div class="sm:col-span-3">
        <label class="mb-1 block text-xs font-semibold text-ink-muted">Catatan Penayangan</label>
        <input v-model="newForm.release_note" type="text" placeholder="Tayang di Delipark XXI..." class="input-field" />
      </div>
      <div class="flex items-end sm:col-span-2">
        <button type="submit" class="btn-primary w-full" :disabled="creating">
          <Plus class="h-4 w-4" :stroke-width="1.75" />
          Tambah
        </button>
      </div>
    </form>

    <!-- Daftar Pemetaan -->
    <div class="overflow-hidden rounded-2xl border border-stone-200/90 bg-white">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-stone-200 text-left text-sm">
          <thead class="bg-subtle/60 text-xs font-semibold uppercase text-ink-muted">
            <tr>
              <th class="px-4 py-3">Anime (db_anitrack)</th>
              <th class="px-4 py-3">Partner Movie ID</th>
              <th class="px-4 py-3">Status Tayang</th>
              <th class="px-4 py-3">Spotlight</th>
              <th class="px-4 py-3">Catatan</th>
              <th class="px-4 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            <tr v-if="loading">
              <td colspan="6" class="px-4 py-8 text-center text-ink-muted">Memuat pemetaan...</td>
            </tr>
            <tr v-for="row in mappings" v-else :key="row.map_id">
              <td class="px-4 py-3">
                <p class="font-semibold text-ink-heading">{{ row.anime_title }}</p>
                <p class="font-mono text-xs text-ink-muted">anime_id: #{{ row.anime_id }} · v{{ row.version }}</p>
              </td>
              <td class="px-4 py-3 font-mono text-xs tabular-nums">#{{ row.cinema_movie_id }}</td>
              <td class="px-4 py-3">
                <select v-model="row.screening_status" class="input-field !w-36 !py-1.5 text-xs">
                  <option value="NOW_SHOWING">NOW_SHOWING</option>
                  <option value="UPCOMING">UPCOMING</option>
                  <option value="ENDED">ENDED</option>
                </select>
                <span
                  class="mt-1 inline-block rounded px-1.5 py-0.5 text-[10px] font-semibold"
                  :class="getScreeningBadge(row.screening_status).className"
                >
                  {{ getScreeningBadge(row.screening_status).label }}
                </span>
              </td>
              <td class="px-4 py-3">
                <label class="inline-flex items-center gap-1.5 text-xs">
                  <input v-model="row.is_featured" type="checkbox" class="rounded border-stone-300 text-crimson focus:ring-crimson" />
                  Featured
                </label>
              </td>
              <td class="px-4 py-3">
                <input v-model="row.release_note" type="text" class="input-field !min-w-[180px] !py-1.5 text-xs" />
              </td>
              <td class="px-4 py-3 text-right">
                <div class="inline-flex gap-1.5">
                  <button
                    type="button"
                    class="btn-primary !px-2.5 !py-1.5 text-xs"
                    :disabled="busyMapId === row.map_id"
                    @click="updateMapping(row)"
                  >
                    <Save class="h-3.5 w-3.5" :stroke-width="1.75" />
                    Simpan
                  </button>
                  <button
                    type="button"
                    class="btn-secondary !px-2.5 !py-1.5 text-xs text-crimson hover:bg-crimson-soft/40"
                    :disabled="busyMapId === row.map_id"
                    @click="deleteMapping(row)"
                  >
                    <Trash2 class="h-3.5 w-3.5" :stroke-width="1.75" />
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
