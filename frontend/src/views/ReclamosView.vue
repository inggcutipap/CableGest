<script setup>
import { onMounted, reactive, ref } from 'vue'
import AppLayout from '../components/AppLayout.vue'
import BaseModal from '../components/BaseModal.vue'
import api from '../services/api'

const reclamos = ref([])
const meta = ref({
  total: 0,
  abiertos: 0,
  en_proceso: 0,
  resueltos: 0,
  current_page: 1,
  last_page: 1,
})
const buscar = ref('')
const estadoFiltro = ref('')
const cargando = ref(false)

const tecnicos = ref([])
const clientesEncontrados = ref([])
const contratosCliente = ref([])
const buscarClienteTexto = ref('')

const modalNuevoAbierto = ref(false)
const modalAsignarAbierto = ref(false)
const modalDiagnosticoAbierto = ref(false)
const reclamoSeleccionado = ref(null)

const guardando = ref(false)
const mensajeExito = ref('')
const mensajeError = ref('')
const errores = ref({})

const formNuevo = reactive({
  cliente_id: '',
  contrato_id: '',
  motivo: '',
  observaciones: '',
})

const formAsignar = reactive({
  tecnico_id: '',
})

const formDiagnostico = reactive({
  diagnostico: 'falla_confirmada',
  observaciones: '',
  fecha_atencion: new Date().toISOString().slice(0, 10),
})

const cargarReclamos = async (pagina = 1) => {
  cargando.value = true
  mensajeError.value = ''

  try {
    const { data } = await api.get('/reclamos', {
      params: {
        buscar: buscar.value.trim(),
        estado: estadoFiltro.value,
        page: pagina,
        per_page: 15,
      },
    })
    reclamos.value = data.data
    meta.value = data.meta
  } catch (error) {
    mensajeError.value = error.response?.data?.message || 'Error al cargar los reclamos.'
  } finally {
    cargando.value = false
  }
}

const cargarTecnicos = async () => {
  try {
    const { data } = await api.get('/usuarios')
    tecnicos.value = data.data
  } catch {
    tecnicos.value = []
  }
}

const buscarClientes = async () => {
  try {
    const { data } = await api.get('/clientes', {
      params: { buscar: buscarClienteTexto.value.trim(), per_page: 15 },
    })
    clientesEncontrados.value = data.data
  } catch {
    clientesEncontrados.value = []
  }
}

const cargarContratosDelCliente = async () => {
  contratosCliente.value = []
  formNuevo.contrato_id = ''
  if (!formNuevo.cliente_id) return

  try {
    const { data } = await api.get('/contratos', {
      params: { cliente_id: formNuevo.cliente_id, per_page: 50 },
    })
    contratosCliente.value = data.data
  } catch {
    contratosCliente.value = []
  }
}

const abrirModalNuevo = async () => {
  errores.value = {}
  formNuevo.cliente_id = ''
  formNuevo.contrato_id = ''
  formNuevo.motivo = ''
  formNuevo.observaciones = ''
  buscarClienteTexto.value = ''
  contratosCliente.value = []
  modalNuevoAbierto.value = true
  await buscarClientes()
}

const registrarReclamo = async () => {
  guardando.value = true
  errores.value = {}
  mensajeExito.value = ''

  try {
    await api.post('/reclamos', {
      cliente_id: Number(formNuevo.cliente_id),
      contrato_id: formNuevo.contrato_id ? Number(formNuevo.contrato_id) : null,
      motivo: formNuevo.motivo,
      observaciones: formNuevo.observaciones || null,
    })
    modalNuevoAbierto.value = false
    mensajeExito.value = 'Reclamo registrado correctamente.'
    await cargarReclamos(1)
  } catch (error) {
    if (error.response?.status === 422) {
      errores.value = error.response.data.errors || {}
    } else {
      mensajeError.value = error.response?.data?.message || 'No se pudo registrar el reclamo.'
    }
  } finally {
    guardando.value = false
  }
}

const abrirModalAsignar = (reclamo) => {
  reclamoSeleccionado.value = reclamo
  errores.value = {}
  const preferido = tecnicos.value.find((t) => t.rol === 'tecnico') || tecnicos.value[0]
  formAsignar.tecnico_id = reclamo.tecnico_id || preferido?.id || ''
  modalAsignarAbierto.value = true
}

const confirmarAsignacion = async () => {
  guardando.value = true
  errores.value = {}
  mensajeExito.value = ''

  try {
    await api.patch(`/reclamos/${reclamoSeleccionado.value.id}/asignar`, {
      tecnico_id: Number(formAsignar.tecnico_id),
    })
    modalAsignarAbierto.value = false
    mensajeExito.value = 'Técnico asignado y reclamo en proceso.'
    await cargarReclamos(meta.value.current_page)
  } catch (error) {
    if (error.response?.status === 422) {
      errores.value = error.response.data.errors || {}
    } else {
      mensajeError.value = error.response?.data?.message || 'No se pudo asignar el técnico.'
    }
  } finally {
    guardando.value = false
  }
}

const abrirModalDiagnostico = (reclamo) => {
  reclamoSeleccionado.value = reclamo
  errores.value = {}
  formDiagnostico.diagnostico = 'falla_confirmada'
  formDiagnostico.observaciones = ''
  formDiagnostico.fecha_atencion = new Date().toISOString().slice(0, 10)
  modalDiagnosticoAbierto.value = true
}

const confirmarDiagnostico = async () => {
  guardando.value = true
  errores.value = {}
  mensajeExito.value = ''

  try {
    await api.patch(`/reclamos/${reclamoSeleccionado.value.id}/diagnostico`, {
      diagnostico: formDiagnostico.diagnostico,
      observaciones: formDiagnostico.observaciones,
      fecha_atencion: formDiagnostico.fecha_atencion,
    })
    modalDiagnosticoAbierto.value = false
    mensajeExito.value = 'Diagnóstico registrado y reclamo resuelto.'
    await cargarReclamos(meta.value.current_page)
  } catch (error) {
    if (error.response?.status === 422) {
      errores.value = error.response.data.errors || {}
    } else {
      mensajeError.value = error.response?.data?.message || 'No se pudo cerrar el reclamo.'
    }
  } finally {
    guardando.value = false
  }
}

const claseEstado = (estado) => {
  if (estado === 'abierto') return 'bg-rose-50 text-rose-700 ring-rose-200'
  if (estado === 'en_proceso') return 'bg-sky-50 text-sky-700 ring-sky-200'
  return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
}

const etiquetaEstado = (estado) => {
  if (estado === 'en_proceso') return 'En proceso'
  return estado.charAt(0).toUpperCase() + estado.slice(1)
}

const etiquetaDiagnostico = (diagnostico) => {
  if (diagnostico === 'falla_confirmada') return 'Falla confirmada'
  if (diagnostico === 'sin_falla') return 'Sin falla'
  return 'Pendiente'
}

onMounted(async () => {
  await Promise.all([cargarReclamos(), cargarTecnicos()])
})
</script>

<template>
  <AppLayout>
    <div class="space-y-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-slate-900">Reclamos e incidencias</h1>
          <p class="text-sm text-slate-600 mt-1">
            Registro de reclamos de clientes, asignación de técnicos y diagnóstico de atención.
          </p>
        </div>
        <button
          type="button"
          class="inline-flex items-center justify-center rounded-xl bg-[#1e3a5f] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#162d4b] transition-colors cursor-pointer"
          @click="abrirModalNuevo"
        >
          + Registrar reclamo
        </button>
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
          <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Abiertos</p>
          <p class="mt-1 text-2xl font-bold text-rose-700">{{ meta.abiertos }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
          <p class="text-xs font-medium uppercase tracking-wider text-slate-500">En proceso</p>
          <p class="mt-1 text-2xl font-bold text-sky-700">{{ meta.en_proceso }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
          <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Resueltos</p>
          <p class="mt-1 text-2xl font-bold text-emerald-700">{{ meta.resueltos }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
          <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Total listados</p>
          <p class="mt-1 text-2xl font-bold text-[#1e3a5f]">{{ meta.total }}</p>
        </div>
      </div>

      <div
        v-if="mensajeExito"
        class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
      >
        {{ mensajeExito }}
      </div>

      <div
        v-if="mensajeError"
        class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
      >
        {{ mensajeError }}
      </div>

      <form
        class="flex flex-col gap-3 sm:flex-row rounded-2xl border border-slate-200 bg-[#f5f4ee]/60 p-4"
        @submit.prevent="cargarReclamos(1)"
      >
        <input
          v-model="buscar"
          type="text"
          placeholder="Buscar por cliente, DNI, contrato o motivo..."
          class="flex-1 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm text-slate-900 focus:border-[#1e3a5f] focus:outline-none"
        />
        <select
          v-model="estadoFiltro"
          class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#1e3a5f] focus:outline-none"
        >
          <option value="">Todos los estados</option>
          <option value="abierto">Abierto</option>
          <option value="en_proceso">En proceso</option>
          <option value="resuelto">Resuelto</option>
        </select>
        <button
          type="submit"
          class="rounded-xl bg-[#1e3a5f] px-4 py-2 text-sm font-medium text-white hover:bg-[#162d4b] cursor-pointer"
        >
          Filtrar
        </button>
      </form>

      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-[#f5f4ee] text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
              <tr>
                <th class="px-4 py-3.5">Fecha</th>
                <th class="px-4 py-3.5">Cliente / Contrato</th>
                <th class="px-4 py-3.5">Motivo</th>
                <th class="px-4 py-3.5">Técnico</th>
                <th class="px-4 py-3.5">Diagnóstico</th>
                <th class="px-4 py-3.5">Estado</th>
                <th class="px-4 py-3.5 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <tr v-if="cargando">
                <td colspan="7" class="px-4 py-8 text-center text-slate-500">Cargando reclamos...</td>
              </tr>
              <tr v-else-if="reclamos.length === 0">
                <td colspan="7" class="px-4 py-8 text-center text-slate-500">No hay reclamos registrados.</td>
              </tr>
              <tr v-for="r in reclamos" :key="r.id" class="hover:bg-slate-50/70">
                <td class="px-4 py-3.5 whitespace-nowrap text-slate-700">{{ r.fecha_registro }}</td>
                <td class="px-4 py-3.5">
                  <div class="font-medium text-slate-900">{{ r.cliente?.nombres }}</div>
                  <div class="text-xs text-slate-500">
                    DNI {{ r.cliente?.dni }}
                    <span v-if="r.contrato"> · {{ r.contrato.codigo_contrato }}</span>
                  </div>
                </td>
                <td class="px-4 py-3.5 max-w-xs">
                  <div class="text-slate-800">{{ r.motivo }}</div>
                  <div v-if="r.observaciones" class="text-xs text-slate-500 mt-0.5">{{ r.observaciones }}</div>
                </td>
                <td class="px-4 py-3.5 text-slate-700">{{ r.tecnico?.nombre || 'Sin asignar' }}</td>
                <td class="px-4 py-3.5 text-xs">
                  <div class="font-medium text-slate-800">{{ etiquetaDiagnostico(r.diagnostico) }}</div>
                  <div v-if="r.fecha_atencion" class="text-slate-500">Atendido: {{ r.fecha_atencion }}</div>
                </td>
                <td class="px-4 py-3.5">
                  <span
                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset"
                    :class="claseEstado(r.estado)"
                  >
                    {{ etiquetaEstado(r.estado) }}
                  </span>
                </td>
                <td class="px-4 py-3.5 text-right whitespace-nowrap space-x-2">
                  <button
                    v-if="r.estado !== 'resuelto'"
                    type="button"
                    class="rounded-lg border border-sky-300 px-2.5 py-1.5 text-xs font-medium text-sky-700 hover:bg-sky-50 cursor-pointer"
                    @click="abrirModalAsignar(r)"
                  >
                    {{ r.tecnico_id ? 'Reasignar' : 'Asignar técnico' }}
                  </button>
                  <button
                    v-if="r.estado !== 'resuelto'"
                    type="button"
                    class="rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 cursor-pointer"
                    @click="abrirModalDiagnostico(r)"
                  >
                    Diagnóstico
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
          <span>Página {{ meta.current_page }} de {{ meta.last_page }}</span>
          <div class="flex gap-2">
            <button
              type="button"
              :disabled="meta.current_page <= 1"
              class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 disabled:opacity-40 cursor-pointer"
              @click="cargarReclamos(meta.current_page - 1)"
            >
              Anterior
            </button>
            <button
              type="button"
              :disabled="meta.current_page >= meta.last_page"
              class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 disabled:opacity-40 cursor-pointer"
              @click="cargarReclamos(meta.current_page + 1)"
            >
              Siguiente
            </button>
          </div>
        </div>
      </div>
    </div>

    <BaseModal :abierto="modalNuevoAbierto" titulo="Registrar reclamo de cliente" @cerrar="modalNuevoAbierto = false">
      <form class="space-y-4" @submit.prevent="registrarReclamo">
        <div class="flex gap-2">
          <input
            v-model="buscarClienteTexto"
            type="text"
            placeholder="Buscar cliente por DNI o nombre..."
            class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
          />
          <button
            type="button"
            class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-100 cursor-pointer"
            @click="buscarClientes"
          >
            Buscar
          </button>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Cliente *</label>
          <select
            v-model="formNuevo.cliente_id"
            required
            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
            @change="cargarContratosDelCliente"
          >
            <option value="">Selecciona un cliente...</option>
            <option v-for="c in clientesEncontrados" :key="c.id" :value="c.id">
              {{ c.dni }} — {{ c.nombres }}
            </option>
          </select>
          <p v-if="errores.cliente_id" class="mt-1 text-xs text-rose-600">{{ errores.cliente_id[0] }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Contrato asociado (opcional)</label>
          <select
            v-model="formNuevo.contrato_id"
            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
          >
            <option value="">Sin contrato específico</option>
            <option v-for="ct in contratosCliente" :key="ct.id" :value="ct.id">
              {{ ct.codigo_contrato }} — {{ ct.catalogo_servicio?.nombre }} ({{ ct.estado }})
            </option>
          </select>
          <p v-if="errores.contrato_id" class="mt-1 text-xs text-rose-600">{{ errores.contrato_id[0] }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Motivo del reclamo *</label>
          <textarea
            v-model="formNuevo.motivo"
            rows="3"
            required
            placeholder="Sin señal en domicilio / Baja velocidad / Corte de fibra"
            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
          />
          <p v-if="errores.motivo" class="mt-1 text-xs text-rose-600">{{ errores.motivo[0] }}</p>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button
            type="button"
            class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 cursor-pointer"
            @click="modalNuevoAbierto = false"
          >
            Cancelar
          </button>
          <button
            type="submit"
            :disabled="guardando"
            class="rounded-xl bg-[#1e3a5f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#162d4b] disabled:opacity-50 cursor-pointer"
          >
            {{ guardando ? 'Guardando...' : 'Registrar reclamo' }}
          </button>
        </div>
      </form>
    </BaseModal>

    <BaseModal :abierto="modalAsignarAbierto" titulo="Asignar técnico al reclamo" @cerrar="modalAsignarAbierto = false">
      <form v-if="reclamoSeleccionado" class="space-y-4" @submit.prevent="confirmarAsignacion">
        <div class="rounded-xl bg-[#f5f4ee] p-3 text-sm">
          <div class="font-semibold text-slate-900">{{ reclamoSeleccionado.cliente?.nombres }}</div>
          <div class="text-xs text-slate-600">{{ reclamoSeleccionado.motivo }}</div>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Técnico responsable *</label>
          <select
            v-model="formAsignar.tecnico_id"
            required
            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
          >
            <option v-for="t in tecnicos" :key="t.id" :value="t.id">
              {{ t.nombre }} ({{ t.rol }})
            </option>
          </select>
          <p v-if="errores.tecnico_id" class="mt-1 text-xs text-rose-600">{{ errores.tecnico_id[0] }}</p>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button
            type="button"
            class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 cursor-pointer"
            @click="modalAsignarAbierto = false"
          >
            Cancelar
          </button>
          <button
            type="submit"
            :disabled="guardando"
            class="rounded-xl bg-[#1e3a5f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#162d4b] disabled:opacity-50 cursor-pointer"
          >
            {{ guardando ? 'Asignando...' : 'Confirmar asignación' }}
          </button>
        </div>
      </form>
    </BaseModal>

    <BaseModal :abierto="modalDiagnosticoAbierto" titulo="Registrar diagnóstico y cierre" @cerrar="modalDiagnosticoAbierto = false">
      <form v-if="reclamoSeleccionado" class="space-y-4" @submit.prevent="confirmarDiagnostico">
        <div class="rounded-xl bg-[#f5f4ee] p-3 text-sm">
          <div class="font-semibold text-slate-900">{{ reclamoSeleccionado.cliente?.nombres }}</div>
          <div class="text-xs text-slate-600">{{ reclamoSeleccionado.motivo }}</div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Diagnóstico *</label>
            <select
              v-model="formDiagnostico.diagnostico"
              class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
            >
              <option value="falla_confirmada">Falla confirmada</option>
              <option value="sin_falla">Sin falla</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Fecha de atención *</label>
            <input
              v-model="formDiagnostico.fecha_atencion"
              type="date"
              required
              class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
            />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Observaciones técnicas *</label>
          <textarea
            v-model="formDiagnostico.observaciones"
            rows="3"
            required
            placeholder="Conector óptico reemplazado y señal verificada en -18 dBm"
            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
          />
          <p v-if="errores.observaciones" class="mt-1 text-xs text-rose-600">{{ errores.observaciones[0] }}</p>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button
            type="button"
            class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 cursor-pointer"
            @click="modalDiagnosticoAbierto = false"
          >
            Cancelar
          </button>
          <button
            type="submit"
            :disabled="guardando"
            class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50 cursor-pointer"
          >
            {{ guardando ? 'Cerrando...' : 'Cerrar reclamo' }}
          </button>
        </div>
      </form>
    </BaseModal>
  </AppLayout>
</template>
