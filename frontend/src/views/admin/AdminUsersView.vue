<script setup>
import { ref, onMounted } from 'vue';
import { http } from '@/api/http';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';
import { formatDateId } from '@/utils/formatters';

const auth = useAuthStore();
const toast = useToastStore();
const users = ref([]);
const loading = ref(true);
const busyId = ref(null);

async function loadUsers() {
  loading.value = true;
  try {
    const res = await http.get('/admin/users');
    users.value = res.data || [];
  } catch (err) {
    toast.error(err.message || 'Gagal memuat daftar pengguna.');
  } finally {
    loading.value = false;
  }
}

async function toggleRole(row) {
  const nextRole = row.role === 'admin' ? 'user' : 'admin';
  busyId.value = row.user_id;
  try {
    await http.patch(`/admin/users/${row.user_id}`, { role: nextRole });
    toast.success(`Peran ${row.username} diubah menjadi ${nextRole}.`);
    await loadUsers();
  } catch (err) {
    toast.error(err.message || 'Gagal mengubah peran akun.');
  } finally {
    busyId.value = null;
  }
}

async function toggleActive(row) {
  busyId.value = row.user_id;
  try {
    await http.patch(`/admin/users/${row.user_id}`, { is_active: !row.is_active });
    toast.info(`Status aktif akun ${row.username} diperbarui.`);
    await loadUsers();
  } catch (err) {
    toast.error(err.message || 'Gagal mengubah status aktif.');
  } finally {
    busyId.value = null;
  }
}

onMounted(loadUsers);
</script>

<template>
  <div class="space-y-6">
    <div>
      <p class="font-mono text-xs font-semibold uppercase tracking-wider text-crimson">
        Manajemen RBAC · Function fn_hitung_umur()
      </p>
      <h1 class="mt-1 font-display text-2xl font-bold text-ink-heading">Kelola Akun Pengguna</h1>
    </div>

    <div class="overflow-hidden rounded-2xl border border-stone-200/90 bg-white">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-stone-200 text-left text-sm">
          <thead class="bg-subtle/60 text-xs font-semibold uppercase text-ink-muted">
            <tr>
              <th class="px-4 py-3">User</th>
              <th class="px-4 py-3">Role</th>
              <th class="px-4 py-3">Umur (PL/pgSQL)</th>
              <th class="px-4 py-3">Domisili</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            <tr v-if="loading">
              <td colspan="6" class="px-4 py-8 text-center text-ink-muted">Memuat pengguna...</td>
            </tr>
            <tr v-for="row in users" v-else :key="row.user_id">
              <td class="px-4 py-3">
                <p class="font-semibold text-ink-heading">{{ row.username }}</p>
                <p class="text-xs text-ink-muted">{{ row.email }} · #{{ row.user_id }}</p>
              </td>
              <td class="px-4 py-3">
                <span
                  class="rounded px-2 py-0.5 font-mono text-xs font-semibold uppercase"
                  :class="row.role === 'admin' ? 'bg-crimson-soft text-crimson' : 'bg-subtle text-ink-body'"
                >
                  {{ row.role }}
                </span>
              </td>
              <td class="px-4 py-3 text-xs tabular-nums">
                {{ row.age !== null ? `${row.age} tahun` : '-' }}
                <span v-if="row.birth_date" class="block text-[11px] text-ink-muted">
                  {{ formatDateId(row.birth_date) }}
                </span>
              </td>
              <td class="px-4 py-3 text-xs text-ink-muted">{{ row.location || '-' }}</td>
              <td class="px-4 py-3">
                <span
                  class="rounded px-2 py-0.5 text-xs font-semibold"
                  :class="row.is_active ? 'bg-emeraldok-soft text-emeraldok' : 'bg-stone-200 text-ink-muted'"
                >
                  {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <span v-if="row.user_id === auth.user?.user_id" class="text-xs italic text-ink-muted">
                  Akun Anda
                </span>
                <div v-else class="inline-flex gap-1.5">
                  <button
                    type="button"
                    class="btn-secondary !px-2.5 !py-1 text-xs"
                    :disabled="busyId === row.user_id"
                    @click="toggleRole(row)"
                  >
                    Ubah ke {{ row.role === 'admin' ? 'User' : 'Admin' }}
                  </button>
                  <button
                    type="button"
                    class="btn-secondary !px-2.5 !py-1 text-xs"
                    :disabled="busyId === row.user_id"
                    @click="toggleActive(row)"
                  >
                    {{ row.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
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
