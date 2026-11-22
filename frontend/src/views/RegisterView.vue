<script setup>
import { reactive, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { UserPlus } from 'lucide-vue-next';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';

const auth = useAuthStore();
const toast = useToastStore();
const route = useRoute();
const router = useRouter();

const form = reactive({
  username: '',
  email: '',
  password: '',
  gender: '',
  location: 'Medan, Sumatera Utara',
  birth_date: '',
});

const errorMsg = ref('');
const fieldErrors = ref({});

async function handleSubmit() {
  errorMsg.value = '';
  fieldErrors.value = {};

  try {
    const user = await auth.register({
      username: form.username,
      email: form.email,
      password: form.password,
      gender: form.gender || null,
      location: form.location || null,
      birth_date: form.birth_date || null,
    });
    toast.success(`Akun ${user.username} berhasil dibuat!`);

    const redirectPath = typeof route.query.redirect === 'string' ? route.query.redirect : null;
    router.push(redirectPath || { name: 'home' });
  } catch (err) {
    errorMsg.value = err.message || 'Gagal mendaftarkan akun.';
    fieldErrors.value = err.errors || {};
  }
}
</script>

<template>
  <div class="mx-auto max-w-lg px-4 py-12 sm:px-6">
    <div class="ticket-card p-6 sm:p-8">
      <p class="font-mono text-xs font-semibold uppercase tracking-wider text-crimson">
        Registrasi Member Baru
      </p>
      <h1 class="mt-1 font-display text-2xl font-bold text-ink-heading">
        Buat Akun AniCine
      </h1>
      <p class="mt-1 text-sm text-ink-muted">
        Daftar untuk memesan tiket bioskop anime di Medan dan menyimpan daftar tontonan pribadi.
      </p>

      <div
        v-if="errorMsg"
        class="mt-4 rounded-xl border border-rose-200 bg-crimson-soft/50 px-4 py-3 text-sm text-crimson"
      >
        {{ errorMsg }}
      </div>

      <form class="mt-6 space-y-4" @submit.prevent="handleSubmit">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label for="reg-username" class="mb-1.5 block text-xs font-semibold text-ink-heading">
              Username *
            </label>
            <input
              id="reg-username"
              v-model="form.username"
              type="text"
              required
              placeholder="contoh: anicine_medan"
              class="input-field"
            />
            <p v-if="fieldErrors.username" class="mt-1 text-xs text-crimson">{{ fieldErrors.username }}</p>
          </div>

          <div>
            <label for="reg-email" class="mb-1.5 block text-xs font-semibold text-ink-heading">
              Alamat Email *
            </label>
            <input
              id="reg-email"
              v-model="form.email"
              type="email"
              required
              placeholder="nama@email.com"
              class="input-field"
            />
            <p v-if="fieldErrors.email" class="mt-1 text-xs text-crimson">{{ fieldErrors.email }}</p>
          </div>
        </div>

        <div>
          <label for="reg-password" class="mb-1.5 block text-xs font-semibold text-ink-heading">
            Kata Sandi * (minimal 8 karakter)
          </label>
          <input
            id="reg-password"
            v-model="form.password"
            type="password"
            minlength="8"
            required
            placeholder="Minimal 8 karakter"
            class="input-field"
          />
          <p v-if="fieldErrors.password" class="mt-1 text-xs text-crimson">{{ fieldErrors.password }}</p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label for="reg-birthdate" class="mb-1.5 block text-xs font-semibold text-ink-heading">
              Tanggal Lahir (opsional)
            </label>
            <input id="reg-birthdate" v-model="form.birth_date" type="date" class="input-field tabular-nums" />
            <p v-if="fieldErrors.birth_date" class="mt-1 text-xs text-crimson">{{ fieldErrors.birth_date }}</p>
          </div>

          <div>
            <label for="reg-gender" class="mb-1.5 block text-xs font-semibold text-ink-heading">
              Gender (opsional)
            </label>
            <select id="reg-gender" v-model="form.gender" class="input-field">
              <option value="">Tidak disebutkan</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Non-Binary">Non-Binary</option>
            </select>
            <p v-if="fieldErrors.gender" class="mt-1 text-xs text-crimson">{{ fieldErrors.gender }}</p>
          </div>
        </div>

        <div>
          <label for="reg-location" class="mb-1.5 block text-xs font-semibold text-ink-heading">
            Kota / Domisili (opsional)
          </label>
          <input id="reg-location" v-model="form.location" type="text" class="input-field" />
        </div>

        <button type="submit" class="btn-primary w-full" :disabled="auth.loading">
          <UserPlus class="h-4 w-4" :stroke-width="1.75" />
          {{ auth.loading ? 'Mendaftarkan...' : 'Buat Akun Sekarang' }}
        </button>
      </form>

      <p class="mt-5 text-center text-sm text-ink-muted">
        Sudah memiliki akun?
        <RouterLink
          :to="{ name: 'login', query: route.query }"
          class="font-semibold text-crimson hover:underline"
        >
          Masuk di sini
        </RouterLink>
      </p>
    </div>
  </div>
</template>
