import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { setUnauthorizedHandler } from '@/api/http';
import { useAuthStore } from '@/stores/auth';
import './assets/main.css';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

// Sinkronisasi sesi bila token JWT kedaluwarsa (401 dari server)
const auth = useAuthStore(pinia);
setUnauthorizedHandler(() => {
  auth.logout();
  if (router.currentRoute.value.meta?.requiresAuth) {
    router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } });
  }
});

app.mount('#app');
