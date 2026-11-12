<script setup>
import { onMounted, ref } from 'vue'
import AppLayout from '../components/AppLayout.vue'
import api from '../services/api'

const notificaciones = ref([])
const meta = ref({
  total: 0,
  pendientes: 0,
  vistas: 0,
  current_page: 1,
  last_page: 1,
})

const estadoFiltro = ref('')
const cargando = ref(false)
const mensajeExito = ref('')
const mensajeError = ref('')

const cargarNotificaciones = async (pagina = 1) => {
  cargando.value = true
  mensajeError.value = ''

  try {
    const { data } = await api.get('/notificaciones', {
      params: {
        estado: estadoFiltro.value,
        page: pagina,
        per_page: 15,
      },
    })
    notificaciones.value = data.data
    meta.value = data.meta
  } catch (error) {
    mensajeError.value = error.response?.data?.message || 'Error al cargar las alertas.'
  } finally {
    cargando.value = false
  }
}

const marcarVista = async (notificacion) => {
  mensajeExito.value = ''
  mensajeError.value = ''

  try {
    await api.patch(`/notificaciones/${notificacion.id}/vista`)
    mensajeExito.value = 'Alerta marcada como vista.'
    await cargarNotificaciones(meta.value.current_page)
  } catch (error) {
    mensajeError.value = error.response?.data?.message || 'No se pudo actualizar la alerta.'
  }
}

onMounted(() => cargarNotificaciones())
</script>

<template>
  <AppLayout>
    <div class="mb-6">
      <h1 class="text-xl font-semibold text-slate-900">Alertas de vencimiento</h1>
      <p class="text-sm text-slate-500">
        Constancia de notificaciones generadas automáticamente cuando vence un cobro sin pago
      </p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
      <div class="rounded-xl border border-slate-200 bg-[#f5f4ee]/60 p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total de alertas</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ meta.pendientes + meta.vistas }}</p>
      </div>
      <div class="rounded-xl border border-slate-200 bg-[#f5f4ee]/60 p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Pendientes por revisar</p>
        <p class="mt-1 text-2xl font-semibold text-rose-700">{{ meta.pendientes }}</p>
      </div>
      <div class="rounded-xl border border-slate-200 bg-[#f5f4ee]/60 p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Vistas</p>
        <p class="mt-1 text-2xl font-semibold text-emerald-700">{{ meta.vistas }}</p>
      </div>
    </div>

    <div
      v-if="mensajeExito"
      class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
    >
      {{ mensajeExito }}
    </div>
    <div
      v-if="mensajeError"
      class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
    >
      {{ mensajeError }}
    </div>

    <div class="mb-5 flex items-center gap-3">
      <select
        v-model="estadoFiltro"
        class="rounded-lg border border-slate-300 px-3.5 py-2 text-sm"
        @change="cargarNotificaciones(1)"
      >
        <option value="">Todas las alertas</option>
        <option value="pendiente">Pendientes</option>
        <option value="vista">Vistas</option>
      </select>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-[#f5f4ee] text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
          <tr>
            <th class="px-4 py-3">Fecha</th>
            <th class="px-4 py-3">Cliente / Contrato</th>
            <th class="px-4 py-3">Mensaje</th>
            <th class="px-4 py-3">Estado</th>
            <th class="px-4 py-3 text-right">Acción</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 bg-white">
          <tr v-if="cargando">
            <td colspan="5" class="px-4 py-8 text-center text-slate-500">Cargando alertas...</td>
          </tr>
          <tr v-else-if="notificaciones.length === 0">
            <td colspan="5" class="px-4 py-8 text-center text-slate-500">
              No hay alertas de vencimiento registradas.
            </td>
          </tr>
          <tr v-for="alerta in notificaciones" :key="alerta.id" class="hover:bg-slate-50/80">
            <td class="px-4 py-3 text-xs text-slate-600">
              {{ String(alerta.fecha_generacion).slice(0, 19).replace('T', ' ') }}
            </td>
            <td class="px-4 py-3">
              <div class="font-medium text-slate-900">{{ alerta.pago?.contrato?.cliente?.nombres }}</div>
              <div class="text-xs font-mono text-slate-500">
                {{ alerta.pago?.contrato?.codigo_contrato || `C-${alerta.pago?.contrato_id}` }}
              </div>
            </td>
            <td class="px-4 py-3 text-slate-700">{{ alerta.mensaje }}</td>
            <td class="px-4 py-3">
              <span
                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                :class="
                  alerta.estado === 'pendiente'
                    ? 'bg-rose-100 text-rose-800'
                    : 'bg-emerald-100 text-emerald-800'
                "
              >
                {{ alerta.estado }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <button
                v-if="alerta.estado === 'pendiente'"
                type="button"
                class="rounded-lg bg-[#1e3a5f] px-3 py-1.5 text-xs font-medium text-white hover:bg-[#172e4c] cursor-pointer"
                @click="marcarVista(alerta)"
              >
                Marcar vista
              </button>
              <span v-else class="text-xs text-slate-400">Revisada</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div
      v-if="meta.last_page > 1"
      class="mt-4 flex items-center justify-between text-sm text-slate-600"
    >
      <span>Página {{ meta.current_page }} de {{ meta.last_page }} ({{ meta.total }} alertas)</span>
      <div class="flex gap-2">
        <button
          type="button"
          :disabled="meta.current_page <= 1"
          class="rounded-lg border border-slate-300 px-3 py-1.5 disabled:opacity-40 cursor-pointer"
          @click="cargarNotificaciones(meta.current_page - 1)"
        >
          Anterior
        </button>
        <button
          type="button"
          :disabled="meta.current_page >= meta.last_page"
          class="rounded-lg border border-slate-300 px-3 py-1.5 disabled:opacity-40 cursor-pointer"
          @click="cargarNotificaciones(meta.current_page + 1)"
        >
          Siguiente
        </button>
      </div>
    </div>
  </AppLayout>
</template>
