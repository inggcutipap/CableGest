<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import AppLayout from '../components/AppLayout.vue'
import BaseModal from '../components/BaseModal.vue'
import api from '../services/api'

const ventas = ref([])
const meta = ref({
  total: 0,
  total_general: 0,
  registradas: 0,
  anuladas: 0,
  monto_mes: 0,
  current_page: 1,
  last_page: 1,
})

const busqueda = ref('')
const estadoFiltro = ref('')
const cargando = ref(false)
const guardando = ref(false)
const mensajeExito = ref('')
const mensajeErrorGeneral = ref('')

const modalNuevaAbierto = ref(false)
const modalDetalleAbierto = ref(false)
const modalAnularAbierto = ref(false)
const ventaSeleccionada = ref(null)

const catalogoActivo = ref([])
const buscarClienteTexto = ref('')
const clientesOpciones = ref([])
const buscandoClientes = ref(false)
const erroresFormulario = ref({})
const motivoAnulacion = ref('')

const formulario = reactive({
  cliente_id: '',
  cliente_etiqueta: '',
  fecha_venta: new Date().toISOString().slice(0, 10),
  lineas: [{ catalogo_id: '', cantidad: 1 }],
})

const catalogoPorId = computed(() => {
  const mapa = new Map()
  for (const item of catalogoActivo.value) {
    mapa.set(Number(item.id), item)
  }
  return mapa
})

const tiposPlan = new Set(['plan_internet', 'plan_tv', 'plan_combo'])

const calcularSubtotalLinea = (linea) => {
  const item = catalogoPorId.value.get(Number(linea.catalogo_id))
  if (!item) return 0
  return Number(item.precio) * Math.max(1, Number(linea.cantidad) || 1)
}

const totalCalculado = computed(() =>
  formulario.lineas.reduce((acc, linea) => acc + calcularSubtotalLinea(linea), 0)
)

const cantidadContratosAGenerar = computed(
  () =>
    formulario.lineas.filter((linea) => {
      const item = catalogoPorId.value.get(Number(linea.catalogo_id))
      return item && tiposPlan.has(item.tipo)
    }).length
)

const cargarVentas = async (pagina = 1) => {
  cargando.value = true
  mensajeErrorGeneral.value = ''

  try {
    const { data } = await api.get('/ventas', {
      params: {
        buscar: busqueda.value.trim(),
        estado: estadoFiltro.value,
        page: pagina,
        per_page: 15,
      },
    })
    ventas.value = data.data
    meta.value = data.meta
  } catch (error) {
    mensajeErrorGeneral.value = error.response?.data?.message || 'Error al cargar las ventas.'
  } finally {
    cargando.value = false
  }
}

const cargarCatalogoActivo = async () => {
  const { data } = await api.get('/catalogo', { params: { activo: 1 } })
  catalogoActivo.value = data.data
}

const buscarClientes = async () => {
  buscandoClientes.value = true
  try {
    const { data } = await api.get('/clientes', {
      params: {
        buscar: buscarClienteTexto.value.trim(),
        per_page: 10,
      },
    })
    clientesOpciones.value = data.data
  } finally {
    buscandoClientes.value = false
  }
}

const seleccionarCliente = (cliente) => {
  formulario.cliente_id = cliente.id
  formulario.cliente_etiqueta = `${cliente.dni} · ${cliente.nombres}`
}

const abrirNuevaVenta = async () => {
  erroresFormulario.value = {}
  formulario.cliente_id = ''
  formulario.cliente_etiqueta = ''
  formulario.fecha_venta = new Date().toISOString().slice(0, 10)
  formulario.lineas = [{ catalogo_id: '', cantidad: 1 }]
  buscarClienteTexto.value = ''
  modalNuevaAbierto.value = true

  await Promise.all([
    catalogoActivo.value.length === 0 ? cargarCatalogoActivo() : Promise.resolve(),
    buscarClientes(),
  ])
}

const agregarLinea = () => {
  formulario.lineas.push({ catalogo_id: '', cantidad: 1 })
}

const quitarLinea = (indice) => {
  if (formulario.lineas.length > 1) {
    formulario.lineas.splice(indice, 1)
  }
}

const registrarVenta = async () => {
  guardando.value = true
  erroresFormulario.value = {}
  mensajeExito.value = ''

  try {
    const { data } = await api.post('/ventas', {
      cliente_id: Number(formulario.cliente_id) || null,
      fecha_venta: formulario.fecha_venta,
      lineas: formulario.lineas.map((linea) => ({
        catalogo_id: Number(linea.catalogo_id) || null,
        cantidad: Number(linea.cantidad) || 1,
      })),
    })

    const contratos = (data.data?.detalles || []).filter((d) => d.contrato).length
    mensajeExito.value =
      contratos > 0
        ? `Venta #${data.data.id} registrada correctamente (${contratos} contrato(s) generado(s)).`
        : `Venta #${data.data.id} registrada correctamente.`

    modalNuevaAbierto.value = false
    await cargarVentas(1)
  } catch (error) {
    if (error.response?.status === 422 && error.response.data?.errors) {
      erroresFormulario.value = error.response.data.errors
    } else {
      erroresFormulario.value = {
        general: [error.response?.data?.message || 'No se pudo registrar la venta.'],
      }
    }
  } finally {
    guardando.value = false
  }
}

const verDetalle = async (venta) => {
  const { data } = await api.get(`/ventas/${venta.id}`)
  ventaSeleccionada.value = data.data
  modalDetalleAbierto.value = true
}

const abrirAnular = (venta) => {
  ventaSeleccionada.value = venta
  motivoAnulacion.value = ''
  erroresFormulario.value = {}
  modalAnularAbierto.value = true
}

const confirmarAnulacion = async () => {
  if (!ventaSeleccionada.value) return
  guardando.value = true
  erroresFormulario.value = {}
  mensajeExito.value = ''

  try {
    await api.patch(`/ventas/${ventaSeleccionada.value.id}/anular`, {
      motivo_anulacion: motivoAnulacion.value.trim() || null,
    })
    mensajeExito.value = `Venta #${ventaSeleccionada.value.id} anulada correctamente.`
    modalAnularAbierto.value = false
    await cargarVentas(meta.value.current_page)
  } catch (error) {
    if (error.response?.status === 422 && error.response.data?.errors) {
      erroresFormulario.value = error.response.data.errors
    } else {
      erroresFormulario.value = {
        general: [error.response?.data?.message || 'No se pudo anular la venta.'],
      }
    }
  } finally {
    guardando.value = false
  }
}

const formatearMoneda = (valor) => `S/ ${Number(valor || 0).toFixed(2)}`

onMounted(() => cargarVentas())
</script>

<template>
  <AppLayout>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
      <div>
        <h1 class="text-xl font-semibold text-slate-900">Gestión de ventas</h1>
        <p class="text-sm text-slate-500">
          Registro transaccional multilínea con generación automática de contratos de planes
        </p>
      </div>
      <button
        type="button"
        class="inline-flex items-center justify-center rounded-lg bg-[#1e3a5f] px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-[#172e4c] transition-colors cursor-pointer"
        @click="abrirNuevaVenta"
      >
        + Nueva venta
      </button>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
      <div class="rounded-xl border border-slate-200 bg-[#f5f4ee]/60 p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Ventas registradas</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ meta.registradas }}</p>
      </div>
      <div class="rounded-xl border border-slate-200 bg-[#f5f4ee]/60 p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Facturado en el mes</p>
        <p class="mt-1 text-2xl font-semibold text-emerald-700">{{ formatearMoneda(meta.monto_mes) }}</p>
      </div>
      <div class="rounded-xl border border-slate-200 bg-[#f5f4ee]/60 p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Ventas anuladas</p>
        <p class="mt-1 text-2xl font-semibold text-rose-700">{{ meta.anuladas }}</p>
      </div>
    </div>

    <div
      v-if="mensajeExito"
      class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
    >
      {{ mensajeExito }}
    </div>
    <div
      v-if="mensajeErrorGeneral"
      class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
    >
      {{ mensajeErrorGeneral }}
    </div>

    <form class="mb-5 flex flex-col gap-3 sm:flex-row" @submit.prevent="cargarVentas(1)">
      <input
        v-model="busqueda"
        type="search"
        placeholder="Buscar por # venta, DNI o nombre del cliente..."
        class="flex-1 rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-[#1e3a5f] focus:outline-none focus:ring-2 focus:ring-[#1e3a5f]/20"
      />
      <select
        v-model="estadoFiltro"
        class="rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-[#1e3a5f] focus:outline-none"
        @change="cargarVentas(1)"
      >
        <option value="">Todos los estados</option>
        <option value="registrada">Registradas</option>
        <option value="anulada">Anuladas</option>
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
            <th class="px-4 py-3">#</th>
            <th class="px-4 py-3">Fecha</th>
            <th class="px-4 py-3">Cliente</th>
            <th class="px-4 py-3">Ítems / Contratos</th>
            <th class="px-4 py-3 text-right">Total</th>
            <th class="px-4 py-3">Estado</th>
            <th class="px-4 py-3 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 bg-white">
          <tr v-if="cargando">
            <td colspan="7" class="px-4 py-8 text-center text-slate-500">Cargando ventas...</td>
          </tr>
          <tr v-else-if="ventas.length === 0">
            <td colspan="7" class="px-4 py-8 text-center text-slate-500">
              No se encontraron ventas registradas.
            </td>
          </tr>
          <tr v-for="venta in ventas" :key="venta.id" class="hover:bg-slate-50/80">
            <td class="px-4 py-3 font-mono text-xs text-slate-600">V-{{ String(venta.id).padStart(5, '0') }}</td>
            <td class="px-4 py-3 text-slate-700">{{ venta.fecha_venta }}</td>
            <td class="px-4 py-3">
              <div class="font-medium text-slate-900">{{ venta.cliente?.nombres }}</div>
              <div class="text-xs font-mono text-slate-500">DNI {{ venta.cliente?.dni }}</div>
            </td>
            <td class="px-4 py-3 text-slate-700">
              <div class="text-xs">{{ (venta.detalles || []).length }} línea(s)</div>
              <div
                v-for="det in (venta.detalles || []).filter((d) => d.contrato)"
                :key="det.id"
                class="text-xs text-[#1e3a5f] font-medium"
              >
                Contrato {{ det.contrato.codigo_contrato }}
              </div>
            </td>
            <td class="px-4 py-3 text-right font-semibold text-slate-900">
              {{ formatearMoneda(venta.monto_total) }}
            </td>
            <td class="px-4 py-3">
              <span
                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="
                  venta.estado === 'registrada'
                    ? 'bg-emerald-100 text-emerald-800'
                    : 'bg-rose-100 text-rose-800'
                "
              >
                {{ venta.estado === 'registrada' ? 'Registrada' : 'Anulada' }}
              </span>
            </td>
            <td class="px-4 py-3 text-right space-x-3">
              <button
                type="button"
                class="text-sm font-medium text-[#1e3a5f] hover:underline cursor-pointer"
                @click="verDetalle(venta)"
              >
                Detalle
              </button>
              <button
                v-if="venta.estado === 'registrada'"
                type="button"
                class="text-sm font-medium text-rose-700 hover:underline cursor-pointer"
                @click="abrirAnular(venta)"
              >
                Anular
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
          @click="cargarVentas(meta.current_page - 1)"
        >
          Anterior
        </button>
        <button
          type="button"
          :disabled="meta.current_page >= meta.last_page"
          class="rounded-lg border border-slate-300 px-3 py-1.5 disabled:opacity-40 cursor-pointer"
          @click="cargarVentas(meta.current_page + 1)"
        >
          Siguiente
        </button>
      </div>
    </div>

    <BaseModal :abierto="modalNuevaAbierto" titulo="Registrar nueva venta" @cerrar="modalNuevaAbierto = false">
      <form class="space-y-4" @submit.prevent="registrarVenta">
        <div
          v-if="erroresFormulario.general"
          class="rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700"
        >
          {{ erroresFormulario.general[0] }}
        </div>

        <div class="space-y-2">
          <label class="block text-sm font-medium text-slate-700">Cliente</label>
          <div class="flex gap-2">
            <input
              v-model="buscarClienteTexto"
              type="search"
              placeholder="Filtrar por DNI o nombre..."
              class="flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm"
              @keyup.enter.prevent="buscarClientes"
            />
            <button
              type="button"
              class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 cursor-pointer"
              @click="buscarClientes"
            >
              {{ buscandoClientes ? 'Buscando...' : 'Buscar' }}
            </button>
          </div>
          <div
            v-if="formulario.cliente_id"
            class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800"
          >
            Seleccionado: <strong>{{ formulario.cliente_etiqueta }}</strong>
          </div>
          <div class="max-h-32 overflow-y-auto rounded-lg border border-slate-200 divide-y divide-slate-100">
            <button
              v-for="cliente in clientesOpciones"
              :key="cliente.id"
              type="button"
              class="w-full px-3 py-1.5 text-left text-xs hover:bg-slate-50 flex justify-between cursor-pointer"
              @click="seleccionarCliente(cliente)"
            >
              <span class="font-medium text-slate-800">{{ cliente.nombres }}</span>
              <span class="font-mono text-slate-500">{{ cliente.dni }}</span>
            </button>
            <div v-if="clientesOpciones.length === 0" class="px-3 py-2 text-xs text-slate-400">
              Sin coincidencias.
            </div>
          </div>
          <p v-if="erroresFormulario.cliente_id" class="text-xs text-rose-600">
            {{ erroresFormulario.cliente_id[0] }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Fecha de venta</label>
          <input
            v-model="formulario.fecha_venta"
            type="date"
            required
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
          />
        </div>

        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <label class="text-sm font-medium text-slate-700">Líneas de venta</label>
            <button
              type="button"
              class="text-xs font-medium text-[#1e3a5f] hover:underline cursor-pointer"
              @click="agregarLinea"
            >
              + Agregar línea
            </button>
          </div>

          <div
            v-for="(linea, indice) in formulario.lineas"
            :key="indice"
            class="grid grid-cols-12 gap-2 items-center rounded-lg border border-slate-200 p-2"
          >
            <div class="col-span-7">
              <select
                v-model="linea.catalogo_id"
                required
                class="w-full rounded border border-slate-300 px-2 py-1.5 text-xs"
              >
                <option value="">Selecciona servicio o producto...</option>
                <option v-for="item in catalogoActivo" :key="item.id" :value="item.id">
                  {{ item.codigo_interno }} · {{ item.nombre }} ({{ formatearMoneda(item.precio) }})
                </option>
              </select>
            </div>
            <div class="col-span-2">
              <input
                v-model.number="linea.cantidad"
                type="number"
                min="1"
                max="100"
                required
                class="w-full rounded border border-slate-300 px-2 py-1.5 text-xs text-center"
              />
            </div>
            <div class="col-span-2 text-right text-xs font-semibold text-slate-800">
              {{ formatearMoneda(calcularSubtotalLinea(linea)) }}
            </div>
            <div class="col-span-1 text-right">
              <button
                v-if="formulario.lineas.length > 1"
                type="button"
                class="text-xs text-rose-600 hover:underline cursor-pointer"
                @click="quitarLinea(indice)"
              >
                ×
              </button>
            </div>
          </div>
          <p v-if="erroresFormulario['lineas.0.catalogo_id']" class="text-xs text-rose-600">
            {{ erroresFormulario['lineas.0.catalogo_id'][0] }}
          </p>
        </div>

        <div class="rounded-lg bg-[#f5f4ee] px-4 py-3 flex items-center justify-between text-sm">
          <div>
            <span class="text-slate-600">Contratos automáticos a generar: </span>
            <strong class="text-[#1e3a5f]">{{ cantidadContratosAGenerar }}</strong>
          </div>
          <div>
            <span class="text-slate-600">Total: </span>
            <strong class="text-lg text-slate-900">{{ formatearMoneda(totalCalculado) }}</strong>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button
            type="button"
            class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 cursor-pointer"
            @click="modalNuevaAbierto = false"
          >
            Cancelar
          </button>
          <button
            type="submit"
            :disabled="guardando"
            class="rounded-lg bg-[#1e3a5f] px-4 py-2 text-sm font-medium text-white hover:bg-[#172e4c] disabled:opacity-60 cursor-pointer"
          >
            {{ guardando ? 'Registrando...' : 'Registrar venta' }}
          </button>
        </div>
      </form>
    </BaseModal>

    <BaseModal :abierto="modalDetalleAbierto" titulo="Detalle de venta" @cerrar="modalDetalleAbierto = false">
      <div v-if="ventaSeleccionada" class="space-y-4 text-sm">
        <div class="grid grid-cols-2 gap-3 rounded-lg bg-[#f5f4ee] p-3">
          <div>
            <p class="text-xs text-slate-500">Cliente</p>
            <p class="font-medium text-slate-900">{{ ventaSeleccionada.cliente?.nombres }}</p>
            <p class="text-xs font-mono text-slate-500">DNI {{ ventaSeleccionada.cliente?.dni }}</p>
          </div>
          <div class="text-right">
            <p class="text-xs text-slate-500">Fecha / Estado</p>
            <p class="font-medium text-slate-900">{{ ventaSeleccionada.fecha_venta }}</p>
            <p class="text-xs uppercase font-semibold text-[#1e3a5f]">{{ ventaSeleccionada.estado }}</p>
          </div>
        </div>

        <table class="min-w-full divide-y divide-slate-200 text-xs">
          <thead class="bg-slate-50 text-left text-slate-600">
            <tr>
              <th class="px-3 py-2">Ítem</th>
              <th class="px-3 py-2 text-right">Cant.</th>
              <th class="px-3 py-2 text-right">P. Unit.</th>
              <th class="px-3 py-2 text-right">Subtotal</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="det in ventaSeleccionada.detalles" :key="det.id">
              <td class="px-3 py-2">
                <div class="font-medium text-slate-900">{{ det.catalogo_servicio?.nombre }}</div>
                <div v-if="det.contrato" class="text-[#1e3a5f]">
                  Contrato {{ det.contrato.codigo_contrato }} ({{ det.contrato.estado }})
                </div>
              </td>
              <td class="px-3 py-2 text-right">{{ det.cantidad }}</td>
              <td class="px-3 py-2 text-right">{{ formatearMoneda(det.precio_unitario) }}</td>
              <td class="px-3 py-2 text-right font-semibold">{{ formatearMoneda(det.subtotal) }}</td>
            </tr>
          </tbody>
        </table>

        <div v-if="ventaSeleccionada.motivo_anulacion" class="rounded bg-rose-50 p-3 text-xs text-rose-800">
          Motivo de anulación: {{ ventaSeleccionada.motivo_anulacion }}
        </div>

        <div class="text-right text-base font-semibold text-slate-900">
          Total: {{ formatearMoneda(ventaSeleccionada.monto_total) }}
        </div>
      </div>
    </BaseModal>

    <BaseModal :abierto="modalAnularAbierto" titulo="Anular venta" @cerrar="modalAnularAbierto = false">
      <form class="space-y-4" @submit.prevent="confirmarAnulacion">
        <div
          v-if="erroresFormulario.general || erroresFormulario.venta || erroresFormulario.estado"
          class="rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700"
        >
          {{
            erroresFormulario.general?.[0] ||
            erroresFormulario.venta?.[0] ||
            erroresFormulario.estado?.[0]
          }}
        </div>

        <p class="text-sm text-slate-700">
          ¿Confirmas anular la venta <strong>#{{ ventaSeleccionada?.id }}</strong>? Si generó un contrato sin pagos
          registrados, ese contrato pasará automáticamente a estado <strong>baja</strong>.
        </p>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Motivo (opcional)</label>
          <input
            v-model="motivoAnulacion"
            type="text"
            maxlength="255"
            placeholder="Ej. Error en selección de plan"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
          />
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button
            type="button"
            class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 cursor-pointer"
            @click="modalAnularAbierto = false"
          >
            Cancelar
          </button>
          <button
            type="submit"
            :disabled="guardando"
            class="rounded-lg bg-rose-700 px-4 py-2 text-sm font-medium text-white hover:bg-rose-800 disabled:opacity-60 cursor-pointer"
          >
            {{ guardando ? 'Anulando...' : 'Confirmar anulación' }}
          </button>
        </div>
      </form>
    </BaseModal>
  </AppLayout>
</template>
