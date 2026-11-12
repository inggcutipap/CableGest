<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import AppLayout from '../components/AppLayout.vue'
import BaseModal from '../components/BaseModal.vue'
import api from '../services/api'

const items = ref([])
const meta = ref({
  total: 0,
  activos: 0,
  inactivos: 0,
  planes_recurrentes: 0,
  cargos_unicos: 0,
  equipos: 0,
})

const filtroTipo = ref('')
const filtroActivo = ref('')
const busqueda = ref('')
const cargando = ref(false)
const guardando = ref(false)
const mensajeExito = ref('')
const mensajeError = ref('')

const modalAbierto = ref(false)
const editandoId = ref(null)
const erroresFormulario = ref({})

const formulario = reactive({
  codigo_interno: '',
  nombre: '',
  tipo: 'plan_internet',
  segmento: 'residencial',
  velocidad_mbps: '',
  precio: '',
  activo: true,
})

const etiquetasTipo = {
  plan_internet: 'Plan internet',
  plan_tv: 'Plan TV',
  plan_combo: 'Plan combo',
  cargo_unico: 'Cargo único',
  equipo: 'Equipo',
}

const esPlan = computed(() =>
  ['plan_internet', 'plan_tv', 'plan_combo'].includes(formulario.tipo)
)

const cargarCatalogo = async () => {
  cargando.value = true
  mensajeError.value = ''

  try {
    const params = {}
    if (filtroTipo.value) params.tipo = filtroTipo.value
    if (filtroActivo.value !== '') params.activo = filtroActivo.value
    if (busqueda.value.trim()) params.buscar = busqueda.value.trim()

    const { data } = await api.get('/catalogo', { params })
    items.value = data.data
    meta.value = data.meta
  } catch (error) {
    mensajeError.value = error.response?.data?.message || 'Error al cargar el catálogo.'
  } finally {
    cargando.value = false
  }
}

const abrirNuevo = () => {
  editandoId.value = null
  erroresFormulario.value = {}
  formulario.codigo_interno = ''
  formulario.nombre = ''
  formulario.tipo = 'plan_internet'
  formulario.segmento = 'residencial'
  formulario.velocidad_mbps = ''
  formulario.precio = ''
  formulario.activo = true
  modalAbierto.value = true
}

const abrirEditar = (item) => {
  editandoId.value = item.id
  erroresFormulario.value = {}
  formulario.codigo_interno = item.codigo_interno
  formulario.nombre = item.nombre
  formulario.tipo = item.tipo
  formulario.segmento = item.segmento || 'residencial'
  formulario.velocidad_mbps = item.velocidad_mbps ?? ''
  formulario.precio = item.precio
  formulario.activo = Boolean(item.activo)
  modalAbierto.value = true
}

const guardarItem = async () => {
  guardando.value = true
  erroresFormulario.value = {}
  mensajeExito.value = ''

  const carga = {
    codigo_interno: formulario.codigo_interno.trim(),
    nombre: formulario.nombre.trim(),
    tipo: formulario.tipo,
    segmento: esPlan.value ? formulario.segmento : null,
    velocidad_mbps:
      esPlan.value && formulario.velocidad_mbps !== '' ? Number(formulario.velocidad_mbps) : null,
    precio: Number(formulario.precio),
    activo: formulario.activo,
  }

  try {
    if (editandoId.value) {
      await api.put(`/catalogo/${editandoId.value}`, carga)
      mensajeExito.value = 'Ítem del catálogo actualizado correctamente.'
    } else {
      await api.post('/catalogo', carga)
      mensajeExito.value = 'Ítem agregado al catálogo correctamente.'
    }
    modalAbierto.value = false
    await cargarCatalogo()
  } catch (error) {
    if (error.response?.status === 422 && error.response.data?.errors) {
      erroresFormulario.value = error.response.data.errors
    } else {
      erroresFormulario.value = {
        general: [error.response?.data?.message || 'No se pudo guardar el ítem.'],
      }
    }
  } finally {
    guardando.value = false
  }
}

const desactivarItem = async (item) => {
  if (!window.confirm(`¿Deseas dar de baja lógica al ítem "${item.nombre}" (${item.codigo_interno})?`)) {
    return
  }

  mensajeExito.value = ''
  mensajeError.value = ''

  try {
    await api.patch(`/catalogo/${item.id}/desactivar`)
    mensajeExito.value = 'Ítem desactivado correctamente.'
    await cargarCatalogo()
  } catch (error) {
    mensajeError.value = error.response?.data?.message || 'No se pudo desactivar el ítem.'
  }
}

onMounted(() => cargarCatalogo())
</script>

<template>
  <AppLayout>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
      <div>
        <h1 class="text-xl font-semibold text-slate-900">Catálogo de servicios</h1>
        <p class="text-sm text-slate-500">Lista de precios unificada · {{ meta.total }} ítems registrados</p>
      </div>

      <button
        type="button"
        class="self-start rounded-xl border border-[#1e3a5f] bg-[#1e3a5f] px-4 py-2 text-sm font-medium text-white hover:bg-[#162d4b] cursor-pointer"
        @click="abrirNuevo"
      >
        + Nuevo ítem
      </button>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="rounded-2xl bg-[#f5f4ee] px-5 py-4 text-center">
        <div class="text-3xl font-bold text-slate-900">{{ meta.planes_recurrentes }}</div>
        <div class="mt-1 text-xs font-medium text-slate-600">Planes recurrentes</div>
      </div>
      <div class="rounded-2xl bg-[#f5f4ee] px-5 py-4 text-center">
        <div class="text-3xl font-bold text-slate-900">{{ meta.cargos_unicos }}</div>
        <div class="mt-1 text-xs font-medium text-slate-600">Cargos únicos</div>
      </div>
      <div class="rounded-2xl bg-[#f5f4ee] px-5 py-4 text-center">
        <div class="text-3xl font-bold text-slate-900">{{ meta.equipos }}</div>
        <div class="mt-1 text-xs font-medium text-slate-600">Equipos</div>
      </div>
      <div class="rounded-2xl bg-[#f5f4ee] px-5 py-4 text-center">
        <div class="text-3xl font-bold text-rose-700">{{ meta.inactivos }}</div>
        <div class="mt-1 text-xs font-medium text-slate-600">Desactivados</div>
      </div>
    </div>

    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <div class="flex flex-wrap gap-2">
        <button
          v-for="tab in [
            { id: '', label: 'Todos' },
            { id: 'planes', label: 'Planes' },
            { id: 'cargo_unico', label: 'Cargos únicos' },
            { id: 'equipo', label: 'Equipos' },
          ]"
          :key="tab.id"
          type="button"
          class="rounded-xl px-3.5 py-1.5 text-sm font-medium border cursor-pointer transition-colors"
          :class="
            filtroTipo === tab.id
              ? 'bg-[#1e3a5f] text-white border-[#1e3a5f]'
              : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'
          "
          @click="
            filtroTipo = tab.id;
            cargarCatalogo()
          "
        >
          {{ tab.label }}
        </button>
      </div>

      <form class="flex flex-wrap items-center gap-2" @submit.prevent="cargarCatalogo">
        <select
          v-model="filtroActivo"
          class="rounded-xl border border-slate-300 px-3 py-1.5 text-sm text-slate-700"
          @change="cargarCatalogo"
        >
          <option value="">Todos los estados</option>
          <option value="1">Solo activos</option>
          <option value="0">Solo inactivos</option>
        </select>
        <input
          v-model="busqueda"
          type="search"
          placeholder="Código o descripción..."
          class="w-48 rounded-xl border border-slate-300 px-3 py-1.5 text-sm focus:border-[#1e3a5f] focus:outline-none"
        />
        <button
          type="submit"
          class="rounded-xl border border-slate-300 px-3.5 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 cursor-pointer"
        >
          Filtrar
        </button>
      </form>
    </div>

    <div v-if="mensajeExito" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
      {{ mensajeExito }}
    </div>

    <div v-if="mensajeError" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
      {{ mensajeError }}
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200">
      <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50/70 text-xs font-semibold uppercase text-slate-600">
          <tr>
            <th class="px-4 py-3">Código</th>
            <th class="px-4 py-3">Servicio</th>
            <th class="px-4 py-3">Tipo</th>
            <th class="px-4 py-3">Segmento / Vel.</th>
            <th class="px-4 py-3 text-right">Precio (S/)</th>
            <th class="px-4 py-3">Estado</th>
            <th class="px-4 py-3 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 bg-white">
          <tr v-if="cargando">
            <td colspan="7" class="px-4 py-8 text-center text-slate-500">Cargando catálogo...</td>
          </tr>
          <tr v-else-if="items.length === 0">
            <td colspan="7" class="px-4 py-8 text-center text-slate-500">
              No hay ítems registrados en el catálogo.
            </td>
          </tr>
          <tr v-for="item in items" v-else :key="item.id" class="hover:bg-slate-50/70">
            <td class="px-4 py-3 font-mono text-slate-800">{{ item.codigo_interno }}</td>
            <td class="px-4 py-3 font-medium text-slate-900">{{ item.nombre }}</td>
            <td class="px-4 py-3 text-slate-600">{{ etiquetasTipo[item.tipo] || item.tipo }}</td>
            <td class="px-4 py-3 text-slate-600">
              <span v-if="item.segmento" class="capitalize">{{ item.segmento }}</span>
              <span v-if="item.velocidad_mbps"> · {{ item.velocidad_mbps }} Mbps</span>
              <span v-if="!item.segmento && !item.velocidad_mbps">—</span>
            </td>
            <td class="px-4 py-3 text-right font-medium text-slate-900">
              {{ Number(item.precio).toFixed(2) }}
            </td>
            <td class="px-4 py-3">
              <span
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="item.activo ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
              >
                {{ item.activo ? 'Activo' : 'Inactivo' }}
              </span>
            </td>
            <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
              <button
                type="button"
                class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100 cursor-pointer"
                @click="abrirEditar(item)"
              >
                Editar
              </button>
              <button
                v-if="item.activo"
                type="button"
                class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700 hover:bg-rose-100 cursor-pointer"
                @click="desactivarItem(item)"
              >
                Desactivar
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <BaseModal
      :abierto="modalAbierto"
      :titulo="editandoId ? 'Editar ítem del catálogo' : 'Nuevo ítem del catálogo'"
      @cerrar="modalAbierto = false"
    >
      <div v-if="erroresFormulario.general" class="mb-4 rounded-xl bg-rose-50 border border-rose-200 p-3 text-sm text-rose-700">
        {{ erroresFormulario.general[0] }}
      </div>

      <form class="space-y-4" @submit.prevent="guardarItem">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Código interno</label>
            <input
              v-model="formulario.codigo_interno"
              type="text"
              maxlength="10"
              required
              placeholder="00001"
              class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-mono focus:border-[#1e3a5f] focus:outline-none"
            />
            <p v-if="erroresFormulario.codigo_interno" class="mt-1 text-xs text-rose-600">
              {{ erroresFormulario.codigo_interno[0] }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Precio (S/)</label>
            <input
              v-model="formulario.precio"
              type="number"
              step="0.01"
              min="0"
              required
              placeholder="0.00"
              class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
            />
            <p v-if="erroresFormulario.precio" class="mt-1 text-xs text-rose-600">{{ erroresFormulario.precio[0] }}</p>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Descripción comercial</label>
          <input
            v-model="formulario.nombre"
            type="text"
            maxlength="200"
            required
            placeholder="Ej. SERVICIO DE INTERNET DE 100 MBPS"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
          />
          <p v-if="erroresFormulario.nombre" class="mt-1 text-xs text-rose-600">{{ erroresFormulario.nombre[0] }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Tipo</label>
          <select
            v-model="formulario.tipo"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
          >
            <option value="plan_internet">Plan internet</option>
            <option value="plan_tv">Plan TV</option>
            <option value="plan_combo">Plan combo</option>
            <option value="cargo_unico">Cargo único</option>
            <option value="equipo">Equipo</option>
          </select>
        </div>

        <div v-if="esPlan" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Segmento</label>
            <select
              v-model="formulario.segmento"
              class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
            >
              <option value="residencial">Residencial</option>
              <option value="comercial">Comercial</option>
              <option value="empresarial">Empresarial</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Velocidad (Mbps)</label>
            <input
              v-model="formulario.velocidad_mbps"
              type="number"
              min="1"
              placeholder="Ej. 100"
              class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
            />
          </div>
        </div>

        <div v-if="editandoId" class="flex items-center gap-2">
          <input id="activo" v-model="formulario.activo" type="checkbox" class="rounded border-slate-300" />
          <label for="activo" class="text-sm text-slate-700">Ítem activo para nuevas ventas</label>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <button
            type="button"
            class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 cursor-pointer"
            @click="modalAbierto = false"
          >
            Cancelar
          </button>
          <button
            type="submit"
            :disabled="guardando"
            class="rounded-xl bg-[#1e3a5f] px-4 py-2 text-sm font-medium text-white hover:bg-[#162d4b] disabled:opacity-60 cursor-pointer"
          >
            {{ guardando ? 'Guardando...' : 'Guardar' }}
          </button>
        </div>
      </form>
    </BaseModal>
  </AppLayout>
</template>
