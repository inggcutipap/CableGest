<script setup>
defineProps({
  abierto: {
    type: Boolean,
    default: false,
  },
  titulo: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['cerrar'])
</script>

<template>
  <div
    v-if="abierto"
    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
    role="dialog"
    aria-modal="true"
  >
    <div class="w-full max-w-lg rounded-2xl bg-white shadow-xl border border-slate-200 overflow-hidden">
      <div class="flex items-center justify-between border-b border-slate-200 bg-[#f5f4ee] px-6 py-4">
        <h3 class="text-base font-semibold text-slate-900">{{ titulo }}</h3>
        <button
          type="button"
          class="rounded-lg p-1 text-slate-500 hover:bg-slate-200/70 hover:text-slate-800 transition-colors"
          aria-label="Cerrar ventana"
          @click="emit('cerrar')"
        >
          ✕
        </button>
      </div>
      <div class="p-6">
        <slot />
      </div>
    </div>
  </div>
</template>
