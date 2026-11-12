<script setup>
import { onMounted, ref } from 'vue'
import AppLayout from '../components/AppLayout.vue'
import api from '../services/api'

const contratos = ref([])
const meta = ref({
  total: 0,
  total_general: 0,
  activos: 0,
  suspendidos: 0,
  baja: 0,
  current_page: 1,
  last_page: 1,
})

const busqueda = ref('')
const estadoFiltro = ref('')
const cargando = ref(false)
const mensajeExito = ref('')
const mensajeError = ref('')

const cargarContratos = async (pagina = 1) => {
  cargando.value = true
  mensajeError.value = ''

  try {
    const { data } = await api.get('/contratos', {
      params: {
        buscar: busqueda.value.trim(),
        estado: estadoFiltro.value,
        page: pagina,
        per_page: 15,
      },
    })
    contratos.value = data.data
    meta.value = data.meta
  } catch (error) {
    mensajeError.value = error.response?.data?.message || 'Error al cargar los contratos.'
  } finally {
    cargando.value = false
  }
}

const cambiarEstado = async (contrato, nuevoEstado) => {
  mensajeExito.value = ''
  mensajeError.value = ''

  try {
    await api.patch(`/contratos/${contrato.id}/estado`, { estado: nuevoEstado })
    mensajeExito.value = `Contrato ${contrato.codigo_contrato || contrato.id} actualizado a "${nuevoEstado}".`
    await cargarContratos(meta.value.current_page)
  } catch (error) {
    mensajeError.value = error.response?.data?.message || 'No se pudo actualizar el estado del contrato.'
  }
}

const claseEstado = (estado) => {
  if (estado === 'activo') return 'bg-emerald-100 text-emerald-800'
  if (estado === 'suspendido') return 'bg-amber-100 text-amber-800'
  return 'bg-rose-100 text-rose-800'
}

onMounted(() => cargarContratos())
</script>

<template>
  <AppLayout>
    <div class="mb-6">
      <h1 class="text-xl font-semibold text-slate-900">Contratos de servicio</h1>
      <p class="text-sm text-slate-500">
        Listado de contratos activos, suspendidos y de baja (originados en ventas o migrados del sistema anterior)
      </p>
    </div>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-6">
      <div class="rounded-xl border border-slate-200 bg-[#f5f4ee]/60 p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total contratos</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ meta.total_general }}</p>
      </div>
      <div class="rounded-xl border border-slate-200 bg-[#f5f4ee]/60 p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Activos</p>
        <p class="mt-1 text-2xl font-semibold text-emerald-700">{{ meta.activos }}</p>
      </div>
      <div class="rounded-xl border border-slate-200 bg-[#f5f4ee]/60 p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Suspendidos</p>
        <p class="mt-1 text-2xl font-semibold text-amber-700">{{ meta.suspendidos }}</p>
      </div>
      <div class="rounded-xl border border-slate-200 bg-[#f5f4ee]/60 p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">De baja</p>
        <p class="mt-1 text-2xl font-semibold text-rose-700">{{ meta.baja }}</p>
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

    <form class="mb-5 flex flex-col gap-3 sm:flex-row" @submit.prevent="cargarContratos(1)">
      <input
        v-model="busqueda"
        type="search"
        placeholder="Buscar por código de contrato, DNI, nombre o plan..."
        class="flex-1 rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-[#1e3a5f] focus:outline-none focus:ring-2 focus:ring-[#1e3a5f]/20"
      />
      <select
        v-model="estadoFiltro"
        class="rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-[#1e3a5f] focus:outline-none"
        @change="cargarContratos(1)"
      >
        <option value="">Todos los estados</option>
        <option value="activo">Activo</option>
        <option value="suspendido">Suspendido</option>
        <option value="baja">Baja</option>
      </select>
      <button
        type="submit"
        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 cursor-pointer"
      >
        Buscar
      </button>
    </form>

    <div class="overflow-x-auto rounded-xl border border-slate-200">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-[#f5f4ee] text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
          <tr>
            <th class="px-4 py-3">Código</th>
            <th class="px-4 py-3">Cliente</th>
            <th class="px-4 py-3">Plan / Servicio</th>
            <th class="px-4 py-3">Inicio</th>
            <th class="px-4 py-3">Estado</th>
            <th class="px-4 py-3 text-right">Cambiar estado</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 bg-white">
          <tr v-if="cargando">
            <td colspan="6" class="px-4 py-8 text-center text-slate-500">Cargando contratos...</td>
          </tr>
          <tr v-else-if="contratos.length === 0">
            <td colspan="6" class="px-4 py-8 text-center text-slate-500">
              No se encontraron contratos.
            </td>
          </tr>
          <tr v-for="contrato in contratos" :key="contrato.id" class="hover:bg-slate-50/80">
            <td class="px-4 py-3 font-mono text-xs text-slate-700">
              {{ contrato.codigo_contrato || `C-${contrato.id}` }}
            </td>
            <td class="px-4 py-3">
              <div class="font-medium text-slate-900">{{ contrato.cliente?.nombres }}</div>
              <div class="text-xs font-mono text-slate-500">DNI {{ contrato.cliente?.dni }}</div>
            </td>
            <td class="px-4 py-3">
              <div class="text-slate-900">{{ contrato.catalogo_servicio?.nombre }}</div>
              <div class="text-xs text-slate-500">
                S/ {{ Number(contrato.catalogo_servicio?.precio || 0).toFixed(2) }}
              </div>
            </td>
            <td class="px-4 py-3 text-slate-600">{{ contrato.fecha_inicio }}</td>
            <td class="px-4 py-3">
              <span
                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                :class="claseEstado(contrato.estado)"
              >
                {{ contrato.estado }}
              </span>
            </td>
            <td class="px-4 py-3 text-right space-x-2 text-xs">
              <button
                v-if="contrato.estado !== 'activo'"
                type="button"
                class="font-medium text-emerald-700 hover:underline cursor-pointer"
                @click="cambiarEstado(contrato, 'activo')"
              >
                Activar
              </button>
              <button
                v-if="contrato.estado === 'activo'"
                type="button"
                class="font-medium text-amber-700 hover:underline cursor-pointer"
                @click="cambiarEstado(contrato, 'suspendido')"
              >
                Suspender
              </button>
              <button
                v-if="contrato.estado !== 'baja'"
                type="button"
                class="font-medium text-rose-700 hover:underline cursor-pointer"
                @click="cambiarEstado(contrato, 'baja')"
              >
                Dar de baja
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div
      v-if="meta.last_page > 1"
      class="mt-4 flex items-center justify-between text-sm text-slate-600"
    >
      <span>Página {{ meta.current_page }} de {{ meta.last_page }} ({{ meta.total }} resultados)</span>
      <div class="flex gap-2">
        <button
          type="button"
          :disabled="meta.current_page <= 1"
          class="rounded-lg border border-slate-300 px-3 py-1.5 disabled:opacity-40 cursor-pointer"
          @click="cargarContratos(meta.current_page - 1)"
        >
          Anterior
        </button>
        <button
          type="button"
          :disabled="meta.current_page >= meta.last_page"
          class="rounded-lg border border-slate-300 px-3 py-1.5 disabled:opacity-40 cursor-pointer"
          @click="cargarContratos(meta.current_page + 1)"
        >
          Siguiente
        </button>
      </div>
    </div>
  </AppLayout>
</template>
