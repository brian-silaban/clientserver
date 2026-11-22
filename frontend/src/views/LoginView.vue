<script setup>
import { reactive, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { LogIn, ShieldCheck } from 'lucide-vue-next';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';

const auth = useAuthStore();
const toast = useToastStore();
const route = useRoute();
const router = useRouter();

const form = reactive({
  identifier: '',
  password: '',
});

const errorMsg = ref('');
const fieldErrors = ref({});

async function handleSubmit() {
  errorMsg.value = '';
  fieldErrors.value = {};

  try {
    const user = await auth.login(form.identifier, form.password);
    toast.success(`Selamat datang kembali, ${user.username}!`);

    const redirectPath = typeof route.query.redirect === 'string' ? route.query.redirect : null;
    if (redirectPath) {
      router.push(redirectPath);
    } else if (user.role === 'admin') {
      router.push({ name: 'admin-dashboard' });
    } else {
      router.push({ name: 'home' });
    }
  } catch (err) {
    errorMsg.value = err.message || 'Gagal masuk ke akun.';
    fieldErrors.value = err.errors || {};
  }
}

function fillDemo(type) {
  if (type === 'admin') {
    form.identifier = 'admin@anicine.local';
    form.password = 'Admin#AniCine2026';
  } else {
    form.identifier = 'user@anicine.local';
    form.password = 'User#AniCine2026';
  }
}
</script>

<template>
  <div class="mx-auto max-w-md px-4 py-12 sm:px-6">
    <div class="ticket-card p-6 sm:p-8">
      <p class="font-mono text-xs font-semibold uppercase tracking-wider text-crimson">
        Autentikasi JWT · AniCine
      </p>
      <h1 class="mt-1 font-display text-2xl font-bold text-ink-heading">
        Masuk ke Akun Anda
      </h1>
      <p class="mt-1 text-sm text-ink-muted">
        Masuk untuk memesan kursi bioskop anime dan mengelola daftar tontonan pribadi.
      </p>

      <div
        v-if="errorMsg"
        class="mt-4 rounded-xl border border-rose-200 bg-crimson-soft/50 px-4 py-3 text-sm text-crimson"
      >
        {{ errorMsg }}
      </div>

      <form class="mt-6 space-y-4" @submit.prevent="handleSubmit">
        <div>
          <label for="login-identifier" class="mb-1.5 block text-xs font-semibold text-ink-heading">
            Email atau Username
          </label>
          <input
            id="login-identifier"
            v-model="form.identifier"
            type="text"
            autocomplete="username"
            required
            placeholder="nama@email.com atau username"
            class="input-field"
          />
          <p v-if="fieldErrors.identifier" class="mt-1 text-xs text-crimson">{{ fieldErrors.identifier }}</p>
        </div>

        <div>
          <label for="login-password" class="mb-1.5 block text-xs font-semibold text-ink-heading">
            Kata Sandi
          </label>
          <input
            id="login-password"
            v-model="form.password"
            type="password"
            autocomplete="current-password"
            required
            placeholder="Masukkan kata sandi"
            class="input-field"
          />
          <p v-if="fieldErrors.password" class="mt-1 text-xs text-crimson">{{ fieldErrors.password }}</p>
        </div>

        <button type="submit" class="btn-primary w-full" :disabled="auth.loading">
          <LogIn class="h-4 w-4" :stroke-width="1.75" />
          {{ auth.loading ? 'Memverifikasi...' : 'Masuk' }}
        </button>
      </form>

      <p class="mt-5 text-center text-sm text-ink-muted">
        Belum punya akun?
        <RouterLink
          :to="{ name: 'register', query: route.query }"
          class="font-semibold text-crimson hover:underline"
        >
          Daftar sekarang
        </RouterLink>
      </p>

      <!-- Bantuan Pengujian Cepat Akun Demo -->
      <div class="mt-6 border-t border-dashed border-stone-200 pt-5">
        <p class="flex items-center gap-1.5 text-xs font-semibold text-ink-muted">
          <ShieldCheck class="h-3.5 w-3.5 text-crimson" :stroke-width="1.75" />
          Akses Cepat Akun Demo Pengujian
        </p>
        <div class="mt-2.5 grid grid-cols-2 gap-2">
          <button type="button" class="btn-secondary !py-1.5 text-xs" @click="fillDemo('user')">
            Isi Member Demo
          </button>
          <button type="button" class="btn-secondary !py-1.5 text-xs" @click="fillDemo('admin')">
            Isi Admin Demo
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
