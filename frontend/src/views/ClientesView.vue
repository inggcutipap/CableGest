<script setup>
import { onMounted, reactive, ref } from 'vue'
import AppLayout from '../components/AppLayout.vue'
import BaseModal from '../components/BaseModal.vue'
import api from '../services/api'

const clientes = ref([])
const meta = ref({
  total: 0,
  total_general: 0,
  registrados_mes: 0,
  current_page: 1,
  last_page: 1,
})

const busqueda = ref('')
const cargando = ref(false)
const guardando = ref(false)
const mensajeExito = ref('')
const mensajeErrorGeneral = ref('')

const modalAbierto = ref(false)
const editandoId = ref(null)
const erroresFormulario = ref({})

const formulario = reactive({
  nombres: '',
  dni: '',
  direccion: '',
  telefono: '',
})

const cargarClientes = async (pagina = 1) => {
  cargando.value = true
  mensajeErrorGeneral.value = ''

  try {
    const { data } = await api.get('/clientes', {
      params: {
        buscar: busqueda.value.trim(),
        page: pagina,
        per_page: 15,
      },
    })
    clientes.value = data.data
    meta.value = data.meta
  } catch (error) {
    mensajeErrorGeneral.value = error.response?.data?.message || 'Error al cargar los clientes.'
  } finally {
    cargando.value = false
  }
}

const abrirNuevo = () => {
  editandoId.value = null
  erroresFormulario.value = {}
  formulario.nombres = ''
  formulario.dni = ''
  formulario.direccion = ''
  formulario.telefono = ''
  modalAbierto.value = true
}

const abrirEditar = (cliente) => {
  editandoId.value = cliente.id
  erroresFormulario.value = {}
  formulario.nombres = cliente.nombres
  formulario.dni = cliente.dni
  formulario.direccion = cliente.direccion
  formulario.telefono = cliente.telefono || ''
  modalAbierto.value = true
}

const guardarCliente = async () => {
  guardando.value = true
  erroresFormulario.value = {}
  mensajeExito.value = ''

  const carga = {
    nombres: formulario.nombres.trim(),
    dni: formulario.dni.trim(),
    direccion: formulario.direccion.trim(),
    telefono: formulario.telefono.trim() || null,
  }

  try {
    if (editandoId.value) {
      await api.put(`/clientes/${editandoId.value}`, carga)
      mensajeExito.value = 'Cliente actualizado correctamente.'
    } else {
      await api.post('/clientes', carga)
      mensajeExito.value = 'Cliente registrado correctamente.'
    }
    modalAbierto.value = false
    await cargarClientes(meta.value.current_page)
  } catch (error) {
    if (error.response?.status === 422 && error.response.data?.errors) {
      erroresFormulario.value = error.response.data.errors
    } else {
      erroresFormulario.value = {
        general: [error.response?.data?.message || 'No se pudo guardar el cliente.'],
      }
    }
  } finally {
    guardando.value = false
  }
}

const formatearCodigo = (id) => `C-${String(id).padStart(4, '0')}`

onMounted(() => cargarClientes())
</script>

<template>
  <AppLayout>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
      <div>
        <h1 class="text-xl font-semibold text-slate-900">Gestión de clientes</h1>
        <p class="text-sm text-slate-500">Consulta, registro y actualización con DNI único de 8 dígitos</p>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <form class="flex items-center gap-2" @submit.prevent="cargarClientes(1)">
          <input
            v-model="busqueda"
            type="search"
            placeholder="Buscar por DNI o nombre..."
            class="w-56 rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
          />
          <button
            type="submit"
            class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 cursor-pointer"
          >
            Buscar
          </button>
        </form>

        <button
          type="button"
          class="rounded-xl border border-[#1e3a5f] bg-[#1e3a5f] px-4 py-2 text-sm font-medium text-white hover:bg-[#162d4b] cursor-pointer"
          @click="abrirNuevo"
        >
          + Nuevo cliente
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
      <div class="rounded-2xl bg-[#f5f4ee] px-5 py-4 text-center">
        <div class="text-3xl font-bold text-slate-900">{{ meta.total_general }}</div>
        <div class="mt-1 text-xs font-medium text-slate-600">Total de clientes</div>
      </div>
      <div class="rounded-2xl bg-[#f5f4ee] px-5 py-4 text-center">
        <div class="text-3xl font-bold text-slate-900">{{ meta.registrados_mes }}</div>
        <div class="mt-1 text-xs font-medium text-slate-600">Registrados este mes</div>
      </div>
      <div class="rounded-2xl bg-[#f5f4ee] px-5 py-4 text-center">
        <div class="text-3xl font-bold text-slate-900">{{ meta.total }}</div>
        <div class="mt-1 text-xs font-medium text-slate-600">Resultados filtrados</div>
      </div>
    </div>

    <div v-if="mensajeExito" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
      {{ mensajeExito }}
    </div>

    <div v-if="mensajeErrorGeneral" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
      {{ mensajeErrorGeneral }}
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200">
      <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50/70 text-xs font-semibold uppercase text-slate-600">
          <tr>
            <th class="px-4 py-3">N.º</th>
            <th class="px-4 py-3">DNI</th>
            <th class="px-4 py-3">Nombres</th>
            <th class="px-4 py-3">Dirección</th>
            <th class="px-4 py-3">Teléfono</th>
            <th class="px-4 py-3">Fecha alta</th>
            <th class="px-4 py-3 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 bg-white">
          <tr v-if="cargando">
            <td colspan="7" class="px-4 py-8 text-center text-slate-500">Cargando clientes...</td>
          </tr>
          <tr v-else-if="clientes.length === 0">
            <td colspan="7" class="px-4 py-8 text-center text-slate-500">
              No se encontraron clientes registrados.
            </td>
          </tr>
          <tr v-for="cliente in clientes" v-else :key="cliente.id" class="hover:bg-slate-50/70">
            <td class="px-4 py-3 font-medium text-slate-700">{{ formatearCodigo(cliente.id) }}</td>
            <td class="px-4 py-3 font-mono text-slate-800">{{ cliente.dni }}</td>
            <td class="px-4 py-3 font-medium text-slate-900">{{ cliente.nombres }}</td>
            <td class="px-4 py-3 text-slate-600">{{ cliente.direccion }}</td>
            <td class="px-4 py-3 text-slate-600">{{ cliente.telefono || '—' }}</td>
            <td class="px-4 py-3 text-slate-600">{{ cliente.fecha_registro }}</td>
            <td class="px-4 py-3 text-right">
              <button
                type="button"
                class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100 cursor-pointer"
                @click="abrirEditar(cliente)"
              >
                Editar
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta.last_page > 1" class="mt-4 flex items-center justify-between text-sm text-slate-600">
      <span>Página {{ meta.current_page }} de {{ meta.last_page }}</span>
      <div class="flex gap-2">
        <button
          type="button"
          :disabled="meta.current_page <= 1"
          class="rounded-lg border border-slate-300 px-3 py-1 disabled:opacity-40 cursor-pointer"
          @click="cargarClientes(meta.current_page - 1)"
        >
          Anterior
        </button>
        <button
          type="button"
          :disabled="meta.current_page >= meta.last_page"
          class="rounded-lg border border-slate-300 px-3 py-1 disabled:opacity-40 cursor-pointer"
          @click="cargarClientes(meta.current_page + 1)"
        >
          Siguiente
        </button>
      </div>
    </div>

    <BaseModal
      :abierto="modalAbierto"
      :titulo="editandoId ? 'Editar cliente' : 'Nuevo cliente'"
      @cerrar="modalAbierto = false"
    >
      <div v-if="erroresFormulario.general" class="mb-4 rounded-xl bg-rose-50 border border-rose-200 p-3 text-sm text-rose-700">
        {{ erroresFormulario.general[0] }}
      </div>

      <form class="space-y-4" @submit.prevent="guardarCliente">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">DNI (8 dígitos)</label>
          <input
            v-model="formulario.dni"
            type="text"
            maxlength="8"
            pattern="\d{8}"
            required
            placeholder="00000000"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
          />
          <p v-if="erroresFormulario.dni" class="mt-1 text-xs text-rose-600">{{ erroresFormulario.dni[0] }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Nombres y apellidos</label>
          <input
            v-model="formulario.nombres"
            type="text"
            maxlength="150"
            required
            placeholder="Apellidos y nombres completos"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
          />
          <p v-if="erroresFormulario.nombres" class="mt-1 text-xs text-rose-600">{{ erroresFormulario.nombres[0] }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Dirección del servicio</label>
          <input
            v-model="formulario.direccion"
            type="text"
            maxlength="200"
            required
            placeholder="Calle, urbanización o referencia"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
          />
          <p v-if="erroresFormulario.direccion" class="mt-1 text-xs text-rose-600">{{ erroresFormulario.direccion[0] }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Teléfono (opcional)</label>
          <input
            v-model="formulario.telefono"
            type="text"
            maxlength="20"
            placeholder="959000000"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
          />
          <p v-if="erroresFormulario.telefono" class="mt-1 text-xs text-rose-600">{{ erroresFormulario.telefono[0] }}</p>
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
