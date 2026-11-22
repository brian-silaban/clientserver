<script setup>
import { RouterView } from 'vue-router';
import { CheckCircle2, AlertCircle, Info, X } from 'lucide-vue-next';
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();
</script>

<template>
  <RouterView />

  <!-- Wadah Notifikasi Toast Global -->
  <div
    aria-live="polite"
    class="pointer-events-none fixed bottom-4 right-4 z-50 flex w-full max-w-sm flex-col gap-2 px-4 sm:px-0"
  >
    <div
      v-for="item in toast.items"
      :key="item.id"
      class="pointer-events-auto flex items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-lg"
      :class="{
        'border-emerald-200 bg-white text-ink-heading': item.type === 'success',
        'border-rose-200 bg-white text-ink-heading': item.type === 'error',
        'border-stone-200 bg-white text-ink-heading': item.type === 'info',
      }"
    >
      <CheckCircle2
        v-if="item.type === 'success'"
        class="mt-0.5 h-4 w-4 shrink-0 text-emeraldok"
        :stroke-width="1.75"
      />
      <AlertCircle
        v-else-if="item.type === 'error'"
        class="mt-0.5 h-4 w-4 shrink-0 text-crimson"
        :stroke-width="1.75"
      />
      <Info v-else class="mt-0.5 h-4 w-4 shrink-0 text-ambercine" :stroke-width="1.75" />

      <p class="flex-1 leading-snug">{{ item.message }}</p>

      <button
        type="button"
        class="rounded p-0.5 text-ink-muted hover:bg-subtle hover:text-ink-heading"
        aria-label="Tutup notifikasi"
        @click="toast.dismiss(item.id)"
      >
        <X class="h-4 w-4" :stroke-width="1.75" />
      </button>
    </div>
  </div>
</template>
