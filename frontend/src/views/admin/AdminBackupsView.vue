<script setup>
import { ref, onMounted } from 'vue';
import { HardDrive, ShieldCheck, CheckCircle2, AlertTriangle, Plus } from 'lucide-vue-next';
import { http } from '@/api/http';
import { useToastStore } from '@/stores/toast';
import { formatDateTimeId } from '@/utils/formatters';

const toast = useToastStore();
const backups = ref([]);
const loading = ref(true);
const runningBackup = ref(false);
const verifyingFile = ref('');
const verifyResults = ref({});

async function loadBackups() {
  loading.value = true;
  try {
    const res = await http.get('/backups');
    backups.value = res.data || [];
  } catch (err) {
    toast.error(err.message || 'Gagal memuat daftar arsip backup.');
  } finally {
    loading.value = false;
  }
}

async function triggerBackup() {
  runningBackup.value = true;
  try {
    const res = await http.post('/backups');
    toast.success(`Backup ${res.data.file} (${res.data.size_human}) berhasil dibuat!`);
    await loadBackups();
  } catch (err) {
    toast.error(err.message || 'Gagal menjalankan pg_dump.');
  } finally {
    runningBackup.value = false;
  }
}

async function verifyBackup(fileName) {
  verifyingFile.value = fileName;
  try {
    const res = await http.get(`/backups/${encodeURIComponent(fileName)}/verify`);
    verifyResults.value[fileName] = res.data;
    if (res.data.checksum && res.data.readable) {
      toast.success(`Verifikasi ${fileName} LULUS: SHA-256 cocok & ${res.data.toc_entries} objek TOC terbaca.`);
    } else {
      toast.error(`Peringatan: Verifikasi ${fileName} mendeteksi ketidaksesuaian.`);
    }
  } catch (err) {
    toast.error(err.message || 'Gagal memverifikasi arsip backup.');
  } finally {
    verifyingFile.value = '';
  }
}

onMounted(loadBackups);
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <p class="font-mono text-xs font-semibold uppercase tracking-wider text-crimson">
          BackupService · pg_dump -Fc via Akun Read-Only r_backup
        </p>
        <h1 class="mt-1 font-display text-2xl font-bold text-ink-heading">
          Backup &amp; Verifikasi Database
        </h1>
      </div>

      <button type="button" class="btn-primary" :disabled="runningBackup" @click="triggerBackup">
        <Plus class="h-4 w-4" :stroke-width="1.75" />
        {{ runningBackup ? 'Menjalankan pg_dump...' : 'Buat Backup Sekarang' }}
      </button>
    </div>

    <!-- Catatan Keamanan Standar Praktikum -->
    <div class="rounded-2xl border border-stone-200 bg-subtle/60 p-4 text-xs leading-relaxed text-ink-body">
      <p class="font-semibold text-ink-heading">Kebijakan Keamanan Backup (Standar PLMKB502)</p>
      <p class="mt-1">
        Arsip disimpan di <code class="font-mono">backend/writable/backups/</code> (di luar <code class="font-mono">public/</code>) memakai akun read-only <code class="font-mono">r_backup</code>. Endpoint <strong>restore</strong> dan <strong>unduh publik</strong> sengaja tidak dibuka lewat HTTP; uji restore penuh tingkat 4 dijalankan oleh DBA melalui <code class="font-mono">scripts/verify.sh</code> dan cron <code class="font-mono">php spark backup:run</code>.
      </p>
    </div>

    <div class="overflow-hidden rounded-2xl border border-stone-200/90 bg-white">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-stone-200 text-left text-sm">
          <thead class="bg-subtle/60 text-xs font-semibold uppercase text-ink-muted">
            <tr>
              <th class="px-4 py-3">Nama File Arsip</th>
              <th class="px-4 py-3">Ukuran</th>
              <th class="px-4 py-3">Waktu Dibuat</th>
              <th class="px-4 py-3">Hasil Verifikasi (SHA-256 &amp; TOC)</th>
              <th class="px-4 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            <tr v-if="loading">
              <td colspan="5" class="px-4 py-8 text-center text-ink-muted">Memuat daftar arsip...</td>
            </tr>
            <tr v-else-if="backups.length === 0">
              <td colspan="5" class="px-4 py-10 text-center text-ink-muted">
                <HardDrive class="mx-auto mb-2 h-8 w-8 text-stone-400" :stroke-width="1.5" />
                Belum ada file backup. Klik "Buat Backup Sekarang" untuk membuat snapshot pertama.
              </td>
            </tr>
            <tr v-for="item in backups" v-else :key="item.file">
              <td class="px-4 py-3 font-mono text-xs font-semibold text-ink-heading">{{ item.file }}</td>
              <td class="px-4 py-3 font-mono text-xs tabular-nums">{{ item.size_human }}</td>
              <td class="px-4 py-3 text-xs text-ink-muted tabular-nums">{{ formatDateTimeId(item.time) }}</td>
              <td class="px-4 py-3 text-xs">
                <div v-if="verifyResults[item.file]" class="flex flex-wrap items-center gap-2">
                  <span
                    class="inline-flex items-center gap-1 rounded px-2 py-0.5 font-semibold"
                    :class="verifyResults[item.file].checksum ? 'bg-emeraldok-soft text-emeraldok' : 'bg-crimson-soft text-crimson'"
                  >
                    <CheckCircle2 v-if="verifyResults[item.file].checksum" class="h-3.5 w-3.5" />
                    <AlertTriangle v-else class="h-3.5 w-3.5" />
                    SHA-256 {{ verifyResults[item.file].checksum ? 'OK' : 'GAGAL' }}
                  </span>
                  <span
                    class="inline-flex items-center gap-1 rounded px-2 py-0.5 font-semibold tabular-nums"
                    :class="verifyResults[item.file].readable ? 'bg-emeraldok-soft text-emeraldok' : 'bg-crimson-soft text-crimson'"
                  >
                    TOC {{ verifyResults[item.file].readable ? `${verifyResults[item.file].toc_entries} objek` : 'RUSAK' }}
                  </span>
                </div>
                <span v-else class="text-ink-muted">Belum diverifikasi di sesi ini</span>
              </td>
              <td class="px-4 py-3 text-right">
                <button
                  type="button"
                  class="btn-secondary !px-3 !py-1.5 text-xs"
                  :disabled="verifyingFile === item.file"
                  @click="verifyBackup(item.file)"
                >
                  <ShieldCheck class="h-3.5 w-3.5 text-emeraldok" :stroke-width="1.75" />
                  {{ verifyingFile === item.file ? 'Memeriksa...' : 'Verifikasi' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
