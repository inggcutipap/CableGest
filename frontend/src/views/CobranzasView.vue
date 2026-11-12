<script setup>
import { onMounted, reactive, ref } from 'vue'
import AppLayout from '../components/AppLayout.vue'
import BaseModal from '../components/BaseModal.vue'
import api from '../services/api'

const pestana = ref('pagos')

const pagos = ref([])
const metaPagos = ref({
  total: 0,
  pendientes: 0,
  vencidos: 0,
  pagados: 0,
  recaudado_mes: 0,
  current_page: 1,
  last_page: 1,
})
const buscarPago = ref('')
const estadoPagoFiltro = ref('')
const cargandoPagos = ref(false)

const morosos = ref([])
const metaMorosidad = ref({
  total: 0,
  total_contratos_morosos: 0,
  monto_total_adeudado: 0,
  total_pagos_vencidos: 0,
  current_page: 1,
  last_page: 1,
})
const buscarMorosidad = ref('')
const cargandoMorosidad = ref(false)

const buscarClienteCuenta = ref('')
const clientesEncontrados = ref([])
const estadoCuenta = ref(null)
const cargandoCuenta = ref(false)

const modalCobroAbierto = ref(false)
const pagoSeleccionado = ref(null)
const guardando = ref(false)
const mensajeExito = ref('')
const mensajeError = ref('')
const erroresCobro = ref({})

const formCobro = reactive({
  fecha_pago: new Date().toISOString().slice(0, 10),
  metodo_pago: 'efectivo',
  observaciones: '',
})

const cargarPagos = async (pagina = 1) => {
  cargandoPagos.value = true
  mensajeError.value = ''

  try {
    const { data } = await api.get('/pagos', {
      params: {
        buscar: buscarPago.value.trim(),
        estado: estadoPagoFiltro.value,
        page: pagina,
        per_page: 15,
      },
    })
    pagos.value = data.data
    metaPagos.value = data.meta
  } catch (error) {
    mensajeError.value = error.response?.data?.message || 'Error al cargar los pagos.'
  } finally {
    cargandoPagos.value = false
  }
}

const cargarMorosidad = async (pagina = 1) => {
  cargandoMorosidad.value = true
  mensajeError.value = ''

  try {
    const { data } = await api.get('/reportes/morosidad', {
      params: {
        buscar: buscarMorosidad.value.trim(),
        page: pagina,
        per_page: 15,
      },
    })
    morosos.value = data.data
    metaMorosidad.value = data.meta
  } catch (error) {
    mensajeError.value = error.response?.data?.message || 'Error al cargar el reporte de morosidad.'
  } finally {
    cargandoMorosidad.value = false
  }
}

const buscarClientesParaCuenta = async () => {
  const { data } = await api.get('/clientes', {
    params: { buscar: buscarClienteCuenta.value.trim(), per_page: 10 },
  })
  clientesEncontrados.value = data.data
}

const verEstadoCuenta = async (clienteId) => {
  cargandoCuenta.value = true
  mensajeError.value = ''
  try {
    const { data } = await api.get(`/clientes/${clienteId}/estado-cuenta`)
    estadoCuenta.value = data.data
  } catch (error) {
    mensajeError.value = error.response?.data?.message || 'No se pudo cargar el estado de cuenta.'
  } finally {
    cargandoCuenta.value = false
  }
}

const abrirModalCobro = (pago) => {
  pagoSeleccionado.value = pago
  erroresCobro.value = {}
  formCobro.fecha_pago = new Date().toISOString().slice(0, 10)
  formCobro.metodo_pago = 'efectivo'
  formCobro.observaciones = pago.observaciones || ''
  modalCobroAbierto.value = true
}

const confirmarCobro = async () => {
  if (!pagoSeleccionado.value) return
  guardando.value = true
  erroresCobro.value = {}
  mensajeExito.value = ''

  try {
    await api.post(`/pagos/${pagoSeleccionado.value.id}/registrar`, {
      fecha_pago: formCobro.fecha_pago,
      metodo_pago: formCobro.metodo_pago,
      observaciones: formCobro.observaciones.trim() || null,
    })
    mensajeExito.value = 'Pago registrado correctamente.'
    modalCobroAbierto.value = false
    await Promise.all([
      cargarPagos(metaPagos.value.current_page),
      cargarMorosidad(metaMorosidad.value.current_page),
      estadoCuenta.value ? verEstadoCuenta(estadoCuenta.value.cliente.id) : Promise.resolve(),
    ])
  } catch (error) {
    if (error.response?.status === 422 && error.response.data?.errors) {
      erroresCobro.value = error.response.data.errors
    } else {
      erroresCobro.value = {
        general: [error.response?.data?.message || 'No se pudo registrar el cobro.'],
      }
    }
  } finally {
    guardando.value = false
  }
}

const cambiarPestana = async (nueva) => {
  pestana.value = nueva
  if (nueva === 'morosidad' && morosos.value.length === 0) {
    await cargarMorosidad(1)
  } else if (nueva === 'estado_cuenta' && clientesEncontrados.value.length === 0) {
    await buscarClientesParaCuenta()
  }
}

const claseEstado = (estado) => {
  if (estado === 'pagado') return 'bg-emerald-100 text-emerald-800'
  if (estado === 'vencido') return 'bg-rose-100 text-rose-800'
  if (estado === 'pendiente') return 'bg-amber-100 text-amber-800'
  return 'bg-slate-200 text-slate-700'
}

const formatearMoneda = (valor) => `S/ ${Number(valor || 0).toFixed(2)}`

onMounted(async () => {
  await Promise.all([cargarPagos(1), cargarMorosidad(1)])
})
</script>

<template>
  <AppLayout>
    <div class="mb-6">
      <h1 class="text-xl font-semibold text-slate-900">Cobranzas y cuentas por cobrar</h1>
      <p class="text-sm text-slate-500">
        Registro de pagos, estado de cuenta por cliente y reporte de contratos morosos
      </p>
    </div>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-6">
      <div class="rounded-xl border border-slate-200 bg-[#f5f4ee]/60 p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Recaudado del mes</p>
        <p class="mt-1 text-2xl font-semibold text-emerald-700">
          {{ formatearMoneda(metaPagos.recaudado_mes) }}
        </p>
      </div>
      <div class="rounded-xl border border-slate-200 bg-[#f5f4ee]/60 p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Pagos pendientes</p>
        <p class="mt-1 text-2xl font-semibold text-amber-700">{{ metaPagos.pendientes }}</p>
      </div>
      <div class="rounded-xl border border-slate-200 bg-[#f5f4ee]/60 p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Pagos vencidos</p>
        <p class="mt-1 text-2xl font-semibold text-rose-700">{{ metaPagos.vencidos }}</p>
      </div>
      <div class="rounded-xl border border-slate-200 bg-[#f5f4ee]/60 p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Deuda vencida total</p>
        <p class="mt-1 text-2xl font-semibold text-rose-800">
          {{ formatearMoneda(metaMorosidad.monto_total_adeudado) }}
        </p>
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

    <div class="mb-6 flex flex-wrap gap-2 border-b border-slate-200 pb-3">
      <button
        type="button"
        class="rounded-lg px-4 py-2 text-sm font-medium transition-colors cursor-pointer"
        :class="pestana === 'pagos' ? 'bg-[#1e3a5f] text-white' : 'bg-[#f5f4ee] text-slate-700 hover:bg-slate-200'"
        @click="cambiarPestana('pagos')"
      >
        Pagos y cobros
      </button>
      <button
        type="button"
        class="rounded-lg px-4 py-2 text-sm font-medium transition-colors cursor-pointer"
        :class="
          pestana === 'estado_cuenta'
            ? 'bg-[#1e3a5f] text-white'
            : 'bg-[#f5f4ee] text-slate-700 hover:bg-slate-200'
        "
        @click="cambiarPestana('estado_cuenta')"
      >
        Estado de cuenta por cliente
      </button>
      <button
        type="button"
        class="rounded-lg px-4 py-2 text-sm font-medium transition-colors cursor-pointer"
        :class="
          pestana === 'morosidad'
            ? 'bg-[#1e3a5f] text-white'
            : 'bg-[#f5f4ee] text-slate-700 hover:bg-slate-200'
        "
        @click="cambiarPestana('morosidad')"
      >
        Reporte de morosidad ({{ metaMorosidad.total_contratos_morosos }})
      </button>
    </div>

    <!-- Pestana 1: Pagos -->
    <div v-if="pestana === 'pagos'">
      <form class="mb-5 flex flex-col gap-3 sm:flex-row" @submit.prevent="cargarPagos(1)">
        <input
          v-model="buscarPago"
          type="search"
          placeholder="Buscar por contrato, DNI, nombre o recibo..."
          class="flex-1 rounded-lg border border-slate-300 px-3.5 py-2 text-sm"
        />
        <select
          v-model="estadoPagoFiltro"
          class="rounded-lg border border-slate-300 px-3.5 py-2 text-sm"
          @change="cargarPagos(1)"
        >
          <option value="">Todos los estados</option>
          <option value="vencido">Vencidos</option>
          <option value="pendiente">Pendientes</option>
          <option value="pagado">Pagados</option>
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
              <th class="px-4 py-3">Contrato</th>
              <th class="px-4 py-3">Cliente</th>
              <th class="px-4 py-3">Periodo / Detalle</th>
              <th class="px-4 py-3">Vencimiento</th>
              <th class="px-4 py-3 text-right">Monto</th>
              <th class="px-4 py-3">Estado</th>
              <th class="px-4 py-3 text-right">Acción</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 bg-white">
            <tr v-if="cargandoPagos">
              <td colspan="7" class="px-4 py-8 text-center text-slate-500">Cargando pagos...</td>
            </tr>
            <tr v-else-if="pagos.length === 0">
              <td colspan="7" class="px-4 py-8 text-center text-slate-500">No se encontraron pagos.</td>
            </tr>
            <tr v-for="pago in pagos" :key="pago.id" class="hover:bg-slate-50/80">
              <td class="px-4 py-3 font-mono text-xs text-slate-700">
                {{ pago.contrato?.codigo_contrato || `C-${pago.contrato_id}` }}
              </td>
              <td class="px-4 py-3">
                <div class="font-medium text-slate-900">{{ pago.contrato?.cliente?.nombres }}</div>
                <div class="text-xs font-mono text-slate-500">DNI {{ pago.contrato?.cliente?.dni }}</div>
              </td>
              <td class="px-4 py-3 text-xs text-slate-700">
                <div class="font-medium">{{ pago.periodo || 'Cargo adicional' }}</div>
                <div v-if="pago.observaciones" class="text-slate-500 truncate max-w-xs">
                  {{ pago.observaciones }}
                </div>
              </td>
              <td class="px-4 py-3 text-slate-600">{{ pago.fecha_vencimiento }}</td>
              <td class="px-4 py-3 text-right font-semibold text-slate-900">
                {{ formatearMoneda(pago.monto) }}
              </td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                  :class="claseEstado(pago.estado)"
                >
                  {{ pago.estado }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <button
                  v-if="pago.estado === 'pendiente' || pago.estado === 'vencido'"
                  type="button"
                  class="rounded-lg bg-[#1e3a5f] px-3 py-1.5 text-xs font-medium text-white hover:bg-[#172e4c] cursor-pointer"
                  @click="abrirModalCobro(pago)"
                >
                  Registrar cobro
                </button>
                <span v-else class="text-xs text-slate-400">
                  {{ pago.fecha_pago || '—' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div
        v-if="metaPagos.last_page > 1"
        class="mt-4 flex items-center justify-between text-sm text-slate-600"
      >
        <span>Página {{ metaPagos.current_page }} de {{ metaPagos.last_page }} ({{ metaPagos.total }} registros)</span>
        <div class="flex gap-2">
          <button
            type="button"
            :disabled="metaPagos.current_page <= 1"
            class="rounded-lg border border-slate-300 px-3 py-1.5 disabled:opacity-40 cursor-pointer"
            @click="cargarPagos(metaPagos.current_page - 1)"
          >
            Anterior
          </button>
          <button
            type="button"
            :disabled="metaPagos.current_page >= metaPagos.last_page"
            class="rounded-lg border border-slate-300 px-3 py-1.5 disabled:opacity-40 cursor-pointer"
            @click="cargarPagos(metaPagos.current_page + 1)"
          >
            Siguiente
          </button>
        </div>
      </div>
    </div>

    <!-- Pestana 2: Estado de cuenta -->
    <div v-else-if="pestana === 'estado_cuenta'" class="space-y-5">
      <form class="flex gap-3" @submit.prevent="buscarClientesParaCuenta">
        <input
          v-model="buscarClienteCuenta"
          type="search"
          placeholder="Buscar cliente por DNI o nombre..."
          class="flex-1 rounded-lg border border-slate-300 px-3.5 py-2 text-sm"
        />
        <button
          type="submit"
          class="rounded-lg bg-[#1e3a5f] px-4 py-2 text-sm font-medium text-white hover:bg-[#172e4c] cursor-pointer"
        >
          Buscar cliente
        </button>
      </form>

      <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
        <button
          v-for="cli in clientesEncontrados"
          :key="cli.id"
          type="button"
          class="rounded-xl border border-slate-200 bg-white p-3 text-left hover:border-[#1e3a5f] hover:bg-[#ebf2fb]/40 transition-colors cursor-pointer"
          @click="verEstadoCuenta(cli.id)"
        >
          <div class="text-sm font-medium text-slate-900 truncate">{{ cli.nombres }}</div>
          <div class="text-xs font-mono text-slate-500">DNI {{ cli.dni }}</div>
        </button>
      </div>

      <div v-if="cargandoCuenta" class="py-6 text-center text-sm text-slate-500">
        Consultando estado de cuenta...
      </div>

      <div v-else-if="estadoCuenta" class="space-y-4 rounded-xl border border-slate-200 p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-4">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">{{ estadoCuenta.cliente.nombres }}</h2>
            <p class="text-xs text-slate-500">
              DNI {{ estadoCuenta.cliente.dni }} · {{ estadoCuenta.cliente.direccion }}
            </p>
          </div>
          <div class="text-right">
            <p class="text-xs uppercase text-slate-500">Saldo pendiente total</p>
            <p class="text-2xl font-bold text-rose-700">
              {{ formatearMoneda(estadoCuenta.resumen.saldo_pendiente) }}
            </p>
          </div>
        </div>

        <div class="grid grid-cols-3 gap-3 text-center text-xs">
          <div class="rounded-lg bg-amber-50 p-3">
            <div class="text-amber-700">Pendiente</div>
            <div class="mt-1 text-base font-semibold text-amber-900">
              {{ formatearMoneda(estadoCuenta.resumen.monto_pendiente) }}
            </div>
          </div>
          <div class="rounded-lg bg-rose-50 p-3">
            <div class="text-rose-700">Vencido</div>
            <div class="mt-1 text-base font-semibold text-rose-900">
              {{ formatearMoneda(estadoCuenta.resumen.monto_vencido) }}
            </div>
          </div>
          <div class="rounded-lg bg-emerald-50 p-3">
            <div class="text-emerald-700">Pagado</div>
            <div class="mt-1 text-base font-semibold text-emerald-900">
              {{ formatearMoneda(estadoCuenta.resumen.monto_pagado) }}
            </div>
          </div>
        </div>

        <div class="overflow-x-auto rounded-lg border border-slate-200">
          <table class="min-w-full divide-y divide-slate-200 text-xs">
            <thead class="bg-[#f5f4ee] text-left font-semibold uppercase text-slate-600">
              <tr>
                <th class="px-3 py-2">Contrato / Plan</th>
                <th class="px-3 py-2">Periodo</th>
                <th class="px-3 py-2">Vencimiento</th>
                <th class="px-3 py-2 text-right">Monto</th>
                <th class="px-3 py-2">Estado</th>
                <th class="px-3 py-2 text-right">Acción</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
              <tr v-if="estadoCuenta.pagos.length === 0">
                <td colspan="6" class="px-3 py-6 text-center text-slate-500">
                  Este cliente no registra cobros.
                </td>
              </tr>
              <tr v-for="pago in estadoCuenta.pagos" :key="pago.id">
                <td class="px-3 py-2">
                  <span class="font-mono">{{ pago.contrato?.codigo_contrato || `C-${pago.contrato_id}` }}</span>
                  · {{ pago.contrato?.catalogo_servicio?.nombre }}
                </td>
                <td class="px-3 py-2">{{ pago.periodo || '—' }}</td>
                <td class="px-3 py-2">{{ pago.fecha_vencimiento }}</td>
                <td class="px-3 py-2 text-right font-semibold">{{ formatearMoneda(pago.monto) }}</td>
                <td class="px-3 py-2">
                  <span class="rounded-full px-2 py-0.5 font-medium capitalize" :class="claseEstado(pago.estado)">
                    {{ pago.estado }}
                  </span>
                </td>
                <td class="px-3 py-2 text-right">
                  <button
                    v-if="pago.estado === 'pendiente' || pago.estado === 'vencido'"
                    type="button"
                    class="text-[#1e3a5f] font-medium hover:underline cursor-pointer"
                    @click="abrirModalCobro(pago)"
                  >
                    Cobrar
                  </button>
                  <span v-else class="text-slate-400">{{ pago.fecha_pago || '—' }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Pestana 3: Morosidad -->
    <div v-else>
      <form class="mb-5 flex gap-3" @submit.prevent="cargarMorosidad(1)">
        <input
          v-model="buscarMorosidad"
          type="search"
          placeholder="Filtrar morosos por contrato, DNI o nombre..."
          class="flex-1 rounded-lg border border-slate-300 px-3.5 py-2 text-sm"
        />
        <button
          type="submit"
          class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 cursor-pointer"
        >
          Filtrar
        </button>
      </form>

      <div class="overflow-x-auto rounded-xl border border-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
          <thead class="bg-[#f5f4ee] text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
            <tr>
              <th class="px-4 py-3">Contrato</th>
              <th class="px-4 py-3">Cliente</th>
              <th class="px-4 py-3">Plan</th>
              <th class="px-4 py-3 text-center">Cuotas vencidas</th>
              <th class="px-4 py-3">Vencimiento más antiguo</th>
              <th class="px-4 py-3 text-right">Días mora</th>
              <th class="px-4 py-3 text-right">Adeudado</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 bg-white">
            <tr v-if="cargandoMorosidad">
              <td colspan="7" class="px-4 py-8 text-center text-slate-500">Calculando morosidad...</td>
            </tr>
            <tr v-else-if="morosos.length === 0">
              <td colspan="7" class="px-4 py-8 text-center text-slate-500">
                No existen contratos con pagos vencidos.
              </td>
            </tr>
            <tr v-for="item in morosos" :key="item.contrato_id" class="hover:bg-slate-50/80">
              <td class="px-4 py-3 font-mono text-xs text-slate-700">{{ item.codigo_contrato }}</td>
              <td class="px-4 py-3">
                <div class="font-medium text-slate-900">{{ item.cliente?.nombres }}</div>
                <div class="text-xs font-mono text-slate-500">DNI {{ item.cliente?.dni }}</div>
              </td>
              <td class="px-4 py-3 text-slate-700">{{ item.plan?.nombre }}</td>
              <td class="px-4 py-3 text-center font-semibold text-rose-700">
                {{ item.pagos_vencidos_count }}
              </td>
              <td class="px-4 py-3 text-slate-600">{{ item.vencimiento_mas_antiguo }}</td>
              <td class="px-4 py-3 text-right font-mono text-xs text-rose-700">
                {{ item.dias_vencidos }} d
              </td>
              <td class="px-4 py-3 text-right font-semibold text-rose-800">
                {{ formatearMoneda(item.monto_adeudado) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div
        v-if="metaMorosidad.last_page > 1"
        class="mt-4 flex items-center justify-between text-sm text-slate-600"
      >
        <span>
          Página {{ metaMorosidad.current_page }} de {{ metaMorosidad.last_page }} ({{ metaMorosidad.total }} contratos)
        </span>
        <div class="flex gap-2">
          <button
            type="button"
            :disabled="metaMorosidad.current_page <= 1"
            class="rounded-lg border border-slate-300 px-3 py-1.5 disabled:opacity-40 cursor-pointer"
            @click="cargarMorosidad(metaMorosidad.current_page - 1)"
          >
            Anterior
          </button>
          <button
            type="button"
            :disabled="metaMorosidad.current_page >= metaMorosidad.last_page"
            class="rounded-lg border border-slate-300 px-3 py-1.5 disabled:opacity-40 cursor-pointer"
            @click="cargarMorosidad(metaMorosidad.current_page + 1)"
          >
            Siguiente
          </button>
        </div>
      </div>
    </div>

    <BaseModal :abierto="modalCobroAbierto" titulo="Registrar cobro" @cerrar="modalCobroAbierto = false">
      <form v-if="pagoSeleccionado" class="space-y-4" @submit.prevent="confirmarCobro">
        <div
          v-if="erroresCobro.general || erroresCobro.estado"
          class="rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700"
        >
          {{ erroresCobro.general?.[0] || erroresCobro.estado?.[0] }}
        </div>

        <div class="rounded-lg bg-[#f5f4ee] p-3 text-sm">
          <p class="text-slate-600">
            Monto a cobrar: <strong class="text-slate-900">{{ formatearMoneda(pagoSeleccionado.monto) }}</strong>
          </p>
          <p class="text-xs text-slate-500">
            Vencimiento: {{ pagoSeleccionado.fecha_vencimiento }} · Periodo: {{ pagoSeleccionado.periodo || '—' }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Fecha de pago</label>
          <input
            v-model="formCobro.fecha_pago"
            type="date"
            required
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Método de pago</label>
          <select
            v-model="formCobro.metodo_pago"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
          >
            <option value="efectivo">Efectivo</option>
            <option value="yape">Yape</option>
            <option value="plin">Plin</option>
            <option value="transferencia">Transferencia bancaria</option>
            <option value="tarjeta">Tarjeta</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Observaciones</label>
          <input
            v-model="formCobro.observaciones"
            type="text"
            maxlength="255"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
          />
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button
            type="button"
            class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 cursor-pointer"
            @click="modalCobroAbierto = false"
          >
            Cancelar
          </button>
          <button
            type="submit"
            :disabled="guardando"
            class="rounded-lg bg-[#1e3a5f] px-4 py-2 text-sm font-medium text-white hover:bg-[#172e4c] disabled:opacity-60 cursor-pointer"
          >
            {{ guardando ? 'Registrando...' : 'Confirmar pago' }}
          </button>
        </div>
      </form>
    </BaseModal>
  </AppLayout>
</template>
