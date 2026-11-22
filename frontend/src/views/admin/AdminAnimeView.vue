<script setup>
import { ref, reactive, onMounted } from 'vue';
import { Search, Plus, Pencil, Trash2, X, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { http } from '@/api/http';
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();

const items = ref([]);
const pager = ref({ current_page: 1, total_pages: 1, total_data: 0 });
const meta = ref({ media_types: [], age_ratings: [] });
const search = ref('');
const page = ref(1);
const loading = ref(true);

const modalOpen = ref(false);
const editingId = ref(null);
const saving = ref(false);
const formErrors = ref({});

const form = reactive({
  title: '',
  title_english: '',
  title_japanese: '',
  image_url: '',
  type_id: 2,
  episodes: 1,
  status: 'Finished Airing',
  duration_min: 100,
  rating_id: 3,
  score: '',
  premiered_year: 2026,
  background: '',
  version: 1,
});

async function loadMeta() {
  try {
    const res = await http.get('/anime/meta');
    meta.value = res.data || { media_types: [], age_ratings: [] };
  } catch {
    // Abaikan jika gagal
  }
}

async function loadAnime() {
  loading.value = true;
  const params = new URLSearchParams({
    page: String(page.value),
    per_page: '12',
    sort: 'id',
  });
  if (search.value.trim()) params.set('q', search.value.trim());

  try {
    const res = await http.get(`/anime?${params.toString()}`);
    items.value = res.data || [];
    pager.value = res.pager || { current_page: 1, total_pages: 1, total_data: 0 };
  } catch (err) {
    toast.error(err.message || 'Gagal memuat daftar anime.');
  } finally {
    loading.value = false;
  }
}

function openCreateModal() {
  editingId.value = null;
  formErrors.value = {};
  Object.assign(form, {
    title: '',
    title_english: '',
    title_japanese: '',
    image_url: '',
    type_id: 2,
    episodes: 1,
    status: 'Finished Airing',
    duration_min: 100,
    rating_id: 3,
    score: '8.50',
    premiered_year: 2026,
    background: '',
    version: 1,
  });
  modalOpen.value = true;
}

async function openEditModal(row) {
  editingId.value = row.anime_id;
  formErrors.value = {};
  try {
    const res = await http.get(`/anime/${row.anime_id}`);
    const detail = res.data;
    Object.assign(form, {
      title: detail.title || '',
      title_english: detail.title_english || '',
      title_japanese: detail.title_japanese || '',
      image_url: detail.image_url || '',
      type_id: Number(detail.type_id || 1),
      episodes: Number(detail.episodes || 0),
      status: detail.status || 'Finished Airing',
      duration_min: detail.duration_min ? Number(detail.duration_min) : '',
      rating_id: detail.rating_id ? Number(detail.rating_id) : '',
      score: detail.score ?? '',
      premiered_year: detail.premiered_year || '',
      background: detail.background || '',
      version: Number(detail.version || 1),
    });
    modalOpen.value = true;
  } catch (err) {
    toast.error(err.message || 'Gagal memuat detail anime.');
  }
}

async function submitForm() {
  saving.value = true;
  formErrors.value = {};

  const payload = {
    title: form.title,
    title_english: form.title_english,
    title_japanese: form.title_japanese,
    image_url: form.image_url,
    type_id: Number(form.type_id),
    episodes: Number(form.episodes || 0),
    status: form.status,
    duration_min: form.duration_min === '' ? '' : Number(form.duration_min),
    rating_id: form.rating_id === '' ? '' : Number(form.rating_id),
    score: form.score === '' ? '' : String(form.score),
    premiered_year: form.premiered_year === '' ? '' : Number(form.premiered_year),
    background: form.background,
  };

  if (editingId.value !== null) {
    payload.version = form.version;
  }

  try {
    if (editingId.value === null) {
      await http.post('/anime', payload);
      toast.success('Anime baru berhasil ditambahkan ke katalog.');
    } else {
      await http.put(`/anime/${editingId.value}`, payload);
      toast.success('Data anime berhasil diperbarui.');
    }
    modalOpen.value = false;
    await loadAnime();
  } catch (err) {
    formErrors.value = err.errors || {};
    toast.error(err.message || 'Gagal menyimpan data anime.');
  } finally {
    saving.value = false;
  }
}

async function deleteAnime(row) {
  if (!window.confirm(`Hapus anime "${row.title}" (#${row.anime_id})?`)) return;
  try {
    await http.delete(`/anime/${row.anime_id}`);
    toast.info(`Anime #${row.anime_id} berhasil dihapus.`);
    await loadAnime();
  } catch (err) {
    toast.error(err.message || 'Gagal menghapus anime.');
  }
}

onMounted(() => {
  loadMeta();
  loadAnime();
});
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <p class="font-mono text-xs font-semibold uppercase tracking-wider text-crimson">
          CRUD Katalog · Optimistic Locking &amp; Audit Trigger
        </p>
        <h1 class="mt-1 font-display text-2xl font-bold text-ink-heading">Kelola Katalog Anime</h1>
      </div>
      <button type="button" class="btn-primary" @click="openCreateModal">
        <Plus class="h-4 w-4" :stroke-width="1.75" />
        Tambah Anime
      </button>
    </div>

    <form class="flex flex-wrap gap-2" @submit.prevent="page = 1; loadAnime()">
      <div class="relative flex-1 min-w-[220px]">
        <Search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-muted" :stroke-width="1.75" />
        <input v-model="search" type="text" placeholder="Cari judul anime..." class="input-field !pl-10" />
      </div>
      <button type="submit" class="btn-secondary">Cari</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-stone-200/90 bg-white">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-stone-200 text-left text-sm">
          <thead class="bg-subtle/60 text-xs font-semibold uppercase text-ink-muted">
            <tr>
              <th class="px-4 py-3">ID</th>
              <th class="px-4 py-3">Judul</th>
              <th class="px-4 py-3">Tipe</th>
              <th class="px-4 py-3">Skor</th>
              <th class="px-4 py-3">Tahun</th>
              <th class="px-4 py-3">Ver</th>
              <th class="px-4 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            <tr v-if="loading">
              <td colspan="7" class="px-4 py-8 text-center text-ink-muted">Memuat katalog...</td>
            </tr>
            <tr v-for="row in items" v-else :key="row.anime_id" class="hover:bg-subtle/30">
              <td class="px-4 py-3 font-mono text-xs text-ink-muted tabular-nums">#{{ row.anime_id }}</td>
              <td class="px-4 py-3">
                <p class="font-semibold text-ink-heading">{{ row.title }}</p>
                <p class="text-xs text-ink-muted">{{ row.title_english || row.status }}</p>
              </td>
              <td class="px-4 py-3 text-xs">{{ row.type_name }}</td>
              <td class="px-4 py-3 font-mono text-xs font-semibold text-ambercine tabular-nums">{{ row.score || '-' }}</td>
              <td class="px-4 py-3 text-xs tabular-nums">{{ row.premiered_year || '-' }}</td>
              <td class="px-4 py-3 font-mono text-xs text-ink-muted tabular-nums">v{{ row.version }}</td>
              <td class="px-4 py-3 text-right">
                <div class="inline-flex gap-1.5">
                  <button type="button" class="btn-secondary !px-2.5 !py-1.5 text-xs" @click="openEditModal(row)">
                    <Pencil class="h-3.5 w-3.5" :stroke-width="1.75" />
                    Edit
                  </button>
                  <button
                    type="button"
                    class="btn-secondary !px-2.5 !py-1.5 text-xs text-crimson hover:bg-crimson-soft/40"
                    @click="deleteAnime(row)"
                  >
                    <Trash2 class="h-3.5 w-3.5" :stroke-width="1.75" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex items-center justify-between border-t border-stone-200 px-4 py-3 text-xs text-ink-muted tabular-nums">
        <span>Halaman {{ pager.current_page }} dari {{ pager.total_pages }} ({{ Number(pager.total_data).toLocaleString('id-ID') }} judul)</span>
        <div class="flex gap-2">
          <button
            type="button"
            class="btn-secondary !px-3 !py-1.5 text-xs"
            :disabled="page <= 1"
            @click="page--; loadAnime()"
          >
            <ChevronLeft class="h-3.5 w-3.5" />
          </button>
          <button
            type="button"
            class="btn-secondary !px-3 !py-1.5 text-xs"
            :disabled="page >= pager.total_pages"
            @click="page++; loadAnime()"
          >
            <ChevronRight class="h-3.5 w-3.5" />
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Tambah / Edit Anime -->
    <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
      <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-4 flex items-center justify-between border-b border-stone-200 pb-3">
          <h2 class="font-display text-xl font-bold text-ink-heading">
            {{ editingId ? `Edit Anime #${editingId} (v${form.version})` : 'Tambah Anime Baru' }}
          </h2>
          <button type="button" class="rounded p-1 text-ink-muted hover:bg-subtle" @click="modalOpen = false">
            <X class="h-5 w-5" />
          </button>
        </div>

        <form class="space-y-4" @submit.prevent="submitForm">
          <div>
            <label class="mb-1 block text-xs font-semibold text-ink-heading">Judul Utama *</label>
            <input v-model="form.title" type="text" required class="input-field" />
            <p v-if="formErrors.title" class="mt-1 text-xs text-crimson">{{ formErrors.title }}</p>
          </div>

          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs font-semibold text-ink-heading">Judul Bahasa Inggris</label>
              <input v-model="form.title_english" type="text" class="input-field" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold text-ink-heading">Judul Bahasa Jepang</label>
              <input v-model="form.title_japanese" type="text" class="input-field" />
            </div>
          </div>

          <div>
            <label class="mb-1 block text-xs font-semibold text-ink-heading">URL Poster (https://...)</label>
            <input v-model="form.image_url" type="url" class="input-field" />
            <p v-if="formErrors.image_url" class="mt-1 text-xs text-crimson">{{ formErrors.image_url }}</p>
          </div>

          <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div>
              <label class="mb-1 block text-xs font-semibold text-ink-heading">Tipe *</label>
              <select v-model.number="form.type_id" class="input-field">
                <option v-for="t in meta.media_types" :key="t.type_id" :value="Number(t.type_id)">
                  {{ t.type_name }}
                </option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold text-ink-heading">Episode</label>
              <input v-model.number="form.episodes" type="number" min="0" class="input-field tabular-nums" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold text-ink-heading">Durasi (mnt)</label>
              <input v-model="form.duration_min" type="number" min="1" class="input-field tabular-nums" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold text-ink-heading">Skor (0–10)</label>
              <input v-model="form.score" type="text" placeholder="8.75" class="input-field tabular-nums" />
            </div>
          </div>

          <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
              <label class="mb-1 block text-xs font-semibold text-ink-heading">Status Rilis</label>
              <select v-model="form.status" class="input-field">
                <option value="Finished Airing">Finished Airing</option>
                <option value="Currently Airing">Currently Airing</option>
                <option value="Not yet aired">Not yet aired</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold text-ink-heading">Rating Usia</label>
              <select v-model="form.rating_id" class="input-field">
                <option value="">Tidak ditentukan</option>
                <option v-for="r in meta.age_ratings" :key="r.rating_id" :value="Number(r.rating_id)">
                  {{ r.rating_code }}
                </option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold text-ink-heading">Tahun Rilis</label>
              <input v-model="form.premiered_year" type="number" min="1900" max="2100" class="input-field tabular-nums" />
            </div>
          </div>

          <div>
            <label class="mb-1 block text-xs font-semibold text-ink-heading">Sinopsis / Catatan Katalog</label>
            <textarea v-model="form.background" rows="3" class="input-field" />
          </div>

          <div class="flex justify-end gap-2 border-t border-stone-200 pt-4">
            <button type="button" class="btn-secondary" @click="modalOpen = false">Batal</button>
            <button type="submit" class="btn-primary" :disabled="saving">
              {{ saving ? 'Menyimpan...' : 'Simpan Data' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
