<script setup>
import { onMounted, ref } from 'vue'
import AppLayout from '../components/AppLayout.vue'
import BaseModal from '../components/BaseModal.vue'
import api from '../services/api'

const registros = ref([])
const meta = ref({
  total: 0,
  tablas: [],
  current_page: 1,
  last_page: 1,
})
const tablaFiltro = ref('')
const accionFiltro = ref('')
const fechaDesde = ref('')
const fechaHasta = ref('')
const buscar = ref('')
const cargando = ref(false)
const mensajeError = ref('')

const registroDetalle = ref(null)
const modalDetalleAbierto = ref(false)

const cargarAuditoria = async (pagina = 1) => {
  cargando.value = true
  mensajeError.value = ''

  try {
    const { data } = await api.get('/auditoria', {
      params: {
        tabla_afectada: tablaFiltro.value,
        accion: accionFiltro.value,
        fecha_desde: fechaDesde.value,
        fecha_hasta: fechaHasta.value,
        buscar: buscar.value.trim(),
        page: pagina,
        per_page: 20,
      },
    })
    registros.value = data.data
    meta.value = data.meta
  } catch (error) {
    mensajeError.value = error.response?.data?.message || 'Error al cargar la bitácora de auditoría.'
  } finally {
    cargando.value = false
  }
}

const abrirDetalle = (registro) => {
  registroDetalle.value = registro
  modalDetalleAbierto.value = true
}

const claseAccion = (accion) => {
  if (accion === 'crear') return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
  if (accion === 'actualizar') return 'bg-sky-50 text-sky-700 ring-sky-200'
  return 'bg-rose-50 text-rose-700 ring-rose-200'
}

const resumenCambios = (detalle) => {
  if (!detalle) return '—'
  if (detalle.despues && typeof detalle.despues === 'object') {
    return Object.keys(detalle.despues).slice(0, 4).join(', ')
  }
  return 'Detalle JSON'
}

onMounted(() => cargarAuditoria())
</script>

<template>
  <AppLayout>
    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Bitácora de auditoría</h1>
        <p class="text-sm text-slate-600 mt-1">
          Registro de solo lectura de operaciones críticas efectuadas por los usuarios del sistema.
        </p>
      </div>

      <div
        v-if="mensajeError"
        class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
      >
        {{ mensajeError }}
      </div>

      <form
        class="grid grid-cols-1 gap-3 sm:grid-cols-6 rounded-2xl border border-slate-200 bg-[#f5f4ee]/60 p-4"
        @submit.prevent="cargarAuditoria(1)"
      >
        <input
          v-model="buscar"
          type="text"
          placeholder="Buscar usuario o ID..."
          class="sm:col-span-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
        />
        <select
          v-model="tablaFiltro"
          class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
        >
          <option value="">Todas las tablas</option>
          <option v-for="t in meta.tablas" :key="t" :value="t">{{ t }}</option>
        </select>
        <select
          v-model="accionFiltro"
          class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
        >
          <option value="">Todas las acciones</option>
          <option value="crear">Crear</option>
          <option value="actualizar">Actualizar</option>
          <option value="anular">Anular</option>
        </select>
        <input
          v-model="fechaDesde"
          type="date"
          aria-label="Fecha desde"
          class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
        />
        <button
          type="submit"
          class="rounded-xl bg-[#1e3a5f] px-4 py-2 text-sm font-medium text-white hover:bg-[#162d4b] cursor-pointer"
        >
          Consultar
        </button>
      </form>

      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-[#f5f4ee] text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
              <tr>
                <th class="px-4 py-3.5">Fecha y hora</th>
                <th class="px-4 py-3.5">Usuario</th>
                <th class="px-4 py-3.5">Acción</th>
                <th class="px-4 py-3.5">Tabla / ID</th>
                <th class="px-4 py-3.5">Campos modificados</th>
                <th class="px-4 py-3.5 text-right">Detalle</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <tr v-if="cargando">
                <td colspan="6" class="px-4 py-8 text-center text-slate-500">Cargando bitácora...</td>
              </tr>
              <tr v-else-if="registros.length === 0">
                <td colspan="6" class="px-4 py-8 text-center text-slate-500">No hay eventos registrados con los filtros indicados.</td>
              </tr>
              <tr v-for="reg in registros" :key="reg.id" class="hover:bg-slate-50/70">
                <td class="px-4 py-3 whitespace-nowrap text-xs text-slate-700">
                  {{ reg.fecha?.slice(0, 19).replace('T', ' ') }}
                </td>
                <td class="px-4 py-3">
                  <div class="font-medium text-slate-900">{{ reg.usuario?.nombre || 'Sistema / CLI' }}</div>
                  <div v-if="reg.usuario?.rol" class="text-xs text-slate-500">{{ reg.usuario.rol }}</div>
                </td>
                <td class="px-4 py-3">
                  <span
                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset"
                    :class="claseAccion(reg.accion)"
                  >
                    {{ reg.accion }}
                  </span>
                </td>
                <td class="px-4 py-3 font-mono text-xs text-slate-800">
                  {{ reg.tabla_afectada }} #{{ reg.registro_id }}
                </td>
                <td class="px-4 py-3 text-xs text-slate-600">
                  {{ resumenCambios(reg.detalle) }}
                </td>
                <td class="px-4 py-3 text-right">
                  <button
                    type="button"
                    class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100 cursor-pointer"
                    @click="abrirDetalle(reg)"
                  >
                    Ver JSON
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div
          v-if="meta.last_page > 1"
          class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600"
        >
          <span>Total: {{ meta.total }} registros · Página {{ meta.current_page }} de {{ meta.last_page }}</span>
          <div class="flex gap-2">
            <button
              type="button"
              :disabled="meta.current_page <= 1"
              class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 disabled:opacity-40 cursor-pointer"
              @click="cargarAuditoria(meta.current_page - 1)"
            >
              Anterior
            </button>
            <button
              type="button"
              :disabled="meta.current_page >= meta.last_page"
              class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 disabled:opacity-40 cursor-pointer"
              @click="cargarAuditoria(meta.current_page + 1)"
            >
              Siguiente
            </button>
          </div>
        </div>
      </div>
    </div>

    <BaseModal :abierto="modalDetalleAbierto" titulo="Detalle de auditoría" @cerrar="modalDetalleAbierto = false">
      <div v-if="registroDetalle" class="space-y-4 text-xs">
        <div class="rounded-xl bg-[#f5f4ee] p-3 text-slate-800">
          <div><strong>Tabla:</strong> {{ registroDetalle.tabla_afectada }} (#{{ registroDetalle.registro_id }})</div>
          <div><strong>Acción:</strong> {{ registroDetalle.accion }}</div>
          <div><strong>Usuario:</strong> {{ registroDetalle.usuario?.nombre || 'Sistema / CLI' }}</div>
        </div>

        <div class="grid grid-cols-1 gap-3">
          <div>
            <p class="font-semibold text-slate-700 mb-1">Valores anteriores</p>
            <pre class="max-h-40 overflow-auto rounded-xl bg-slate-900 p-3 text-slate-100">{{ JSON.stringify(registroDetalle.detalle?.antes, null, 2) }}</pre>
          </div>
          <div>
            <p class="font-semibold text-slate-700 mb-1">Valores nuevos</p>
            <pre class="max-h-48 overflow-auto rounded-xl bg-slate-900 p-3 text-emerald-300">{{ JSON.stringify(registroDetalle.detalle?.despues, null, 2) }}</pre>
          </div>
        </div>
      </div>
    </BaseModal>
  </AppLayout>
</template>
