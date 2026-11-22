import { defineStore } from 'pinia';
import { ref } from 'vue';

/**
 * Pinia Store Notifikasi Toast global.
 */
export const useToastStore = defineStore('toast', () => {
  const items = ref([]);
  let nextId = 1;

  function show(message, type = 'info', durationMs = 4000) {
    const id = nextId++;
    items.value.push({ id, message, type });

    if (durationMs > 0) {
      setTimeout(() => dismiss(id), durationMs);
    }
  }

  function success(message) {
    show(message, 'success', 4000);
  }

  function error(message) {
    show(message, 'error', 5500);
  }

  function info(message) {
    show(message, 'info', 4000);
  }

  function dismiss(id) {
    items.value = items.value.filter((item) => item.id !== id);
  }

  return { items, show, success, error, info, dismiss };
});
