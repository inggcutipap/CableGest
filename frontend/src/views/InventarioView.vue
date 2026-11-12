<script setup>
import { onMounted, reactive, ref } from 'vue'
import AppLayout from '../components/AppLayout.vue'
import BaseModal from '../components/BaseModal.vue'
import api from '../services/api'

const pestana = ref('equipos')

const equipos = ref([])
const metaEquipos = ref({
  total: 0,
  disponibles: 0,
  instalados: 0,
  en_reparacion: 0,
  baja: 0,
  current_page: 1,
  last_page: 1,
})
const buscarEquipo = ref('')
const estadoFiltro = ref('')
const cargandoEquipos = ref(false)

const stockItems = ref([])
const stockResumen = ref({
  total_tipos: 0,
  total_disponible: 0,
  total_instalado: 0,
  total_en_reparacion: 0,
})
const cargandoStock = ref(false)

const seriesTexto = ref('')
const resultadoConteo = ref(null)
const historialConteos = ref([])
const calculandoExactitud = ref(false)

const catalogoEquipos = ref([])
const contratosDisponibles = ref([])
const buscarContratoTexto = ref('')

const modalEntradaAbierto = ref(false)
const modalInstalarAbierto = ref(false)
const modalSalidaAbierto = ref(false)
const modalDetalleAbierto = ref(false)

const equipoSeleccionado = ref(null)
const detalleEquipo = ref(null)

const guardando = ref(false)
const mensajeExito = ref('')
const mensajeError = ref('')
const erroresForm = ref({})

const formEntrada = reactive({
  catalogo_id: '',
  marca: '',
  serie: '',
  motivo: 'Ingreso a almacén',
})

const formInstalar = reactive({
  contrato_id: '',
  fecha_instalacion: new Date().toISOString().slice(0, 10),
})

const formSalida = reactive({
  estado: 'en_reparacion',
  motivo: '',
})

const cargarCatalogoEquipos = async () => {
  try {
    const { data } = await api.get('/catalogo', {
      params: { tipo: 'equipo', solo_activos: 1, per_page: 100 },
    })
    catalogoEquipos.value = data.data
  } catch {
    catalogoEquipos.value = []
  }
}

const cargarEquipos = async (pagina = 1) => {
  cargandoEquipos.value = true
  mensajeError.value = ''

  try {
    const { data } = await api.get('/equipos', {
      params: {
        buscar: buscarEquipo.value.trim(),
        estado: estadoFiltro.value,
        page: pagina,
        per_page: 15,
      },
    })
    equipos.value = data.data
    metaEquipos.value = data.meta
  } catch (error) {
    mensajeError.value = error.response?.data?.message || 'Error al cargar los equipos.'
  } finally {
    cargandoEquipos.value = false
  }
}

const cargarStock = async () => {
  cargandoStock.value = true
  mensajeError.value = ''

  try {
    const { data } = await api.get('/inventario/stock')
    stockItems.value = data.data
    stockResumen.value = data.meta
  } catch (error) {
    mensajeError.value = error.response?.data?.message || 'Error al calcular el stock.'
  } finally {
    cargandoStock.value = false
  }
}

const cargarConteos = async () => {
  try {
    const { data } = await api.get('/inventario/conteos')
    historialConteos.value = data.data
    if (!resultadoConteo.value && data.data.length > 0) {
      resultadoConteo.value = data.data[0]
    }
  } catch {
    historialConteos.value = []
  }
}

const cambiarPestana = (nueva) => {
  pestana.value = nueva
  mensajeError.value = ''
  if (nueva === 'equipos') {
    cargarEquipos()
  } else if (nueva === 'stock') {
    cargarStock()
  } else {
    cargarConteos()
  }
}

const abrirModalEntrada = () => {
  erroresForm.value = {}
  formEntrada.catalogo_id = catalogoEquipos.value[0]?.id || ''
  formEntrada.marca = ''
  formEntrada.serie = ''
  formEntrada.motivo = 'Ingreso a almacén'
  modalEntradaAbierto.value = true
}

const registrarEntrada = async () => {
  guardando.value = true
  erroresForm.value = {}
  mensajeExito.value = ''

  try {
    await api.post('/equipos', {
      catalogo_id: Number(formEntrada.catalogo_id),
      marca: formEntrada.marca,
      serie: formEntrada.serie,
      motivo: formEntrada.motivo,
    })
    modalEntradaAbierto.value = false
    mensajeExito.value = 'Entrada de equipo registrada correctamente.'
    await Promise.all([cargarEquipos(1), cargarStock()])
  } catch (error) {
    if (error.response?.status === 422) {
      erroresForm.value = error.response.data.errors || {}
    } else {
      mensajeError.value = error.response?.data?.message || 'No se pudo registrar la entrada.'
    }
  } finally {
    guardando.value = false
  }
}

const buscarContratos = async () => {
  try {
    const { data } = await api.get('/contratos', {
      params: { buscar: buscarContratoTexto.value.trim(), estado: 'activo', per_page: 20 },
    })
    contratosDisponibles.value = data.data
  } catch {
    contratosDisponibles.value = []
  }
}

const abrirModalInstalar = async (equipo) => {
  equipoSeleccionado.value = equipo
  erroresForm.value = {}
  formInstalar.contrato_id = ''
  formInstalar.fecha_instalacion = new Date().toISOString().slice(0, 10)
  buscarContratoTexto.value = ''
  modalInstalarAbierto.value = true
  await buscarContratos()
}

const confirmarInstalacion = async () => {
  guardando.value = true
  erroresForm.value = {}
  mensajeExito.value = ''

  try {
    await api.post(`/equipos/${equipoSeleccionado.value.id}/instalar`, {
      contrato_id: Number(formInstalar.contrato_id),
      fecha_instalacion: formInstalar.fecha_instalacion,
    })
    modalInstalarAbierto.value = false
    mensajeExito.value = 'Equipo instalado en contrato y descontado del stock.'
    await Promise.all([cargarEquipos(metaEquipos.value.current_page), cargarStock()])
  } catch (error) {
    if (error.response?.status === 422) {
      erroresForm.value = error.response.data.errors || {}
    } else {
      mensajeError.value = error.response?.data?.message || 'No se pudo instalar el equipo.'
    }
  } finally {
    guardando.value = false
  }
}

const abrirModalSalida = (equipo) => {
  equipoSeleccionado.value = equipo
  erroresForm.value = {}
  formSalida.estado = 'en_reparacion'
  formSalida.motivo = ''
  modalSalidaAbierto.value = true
}

const confirmarSalida = async () => {
  guardando.value = true
  erroresForm.value = {}
  mensajeExito.value = ''

  try {
    await api.post(`/equipos/${equipoSeleccionado.value.id}/salida`, {
      estado: formSalida.estado,
      motivo: formSalida.motivo,
    })
    modalSalidaAbierto.value = false
    mensajeExito.value = 'Salida de equipo registrada correctamente.'
    await Promise.all([cargarEquipos(metaEquipos.value.current_page), cargarStock()])
  } catch (error) {
    if (error.response?.status === 422) {
      erroresForm.value = error.response.data.errors || {}
    } else {
      mensajeError.value = error.response?.data?.message || 'No se pudo registrar la salida.'
    }
  } finally {
    guardando.value = false
  }
}

const verDetalleEquipo = async (equipo) => {
  try {
    const { data } = await api.get(`/equipos/${equipo.id}`)
    detalleEquipo.value = data.data
    modalDetalleAbierto.value = true
  } catch (error) {
    mensajeError.value = error.response?.data?.message || 'No se pudo cargar el historial del equipo.'
  }
}

const ejecutarConteoExactitud = async () => {
  calculandoExactitud.value = true
  mensajeError.value = ''
  mensajeExito.value = ''

  const series = seriesTexto.value
    .split(/[\r\n,;]+/)
    .map((s) => s.trim())
    .filter((s) => s.length > 0)

  try {
    const { data } = await api.post('/inventario/exactitud', { series })
    resultadoConteo.value = data.data
    mensajeExito.value = `Conteo físico procesado: ${data.data.exactitud}% de exactitud.`
    await cargarConteos()
  } catch (error) {
    mensajeError.value = error.response?.data?.message || 'Error al calcular la exactitud.'
  } finally {
    calculandoExactitud.value = false
  }
}

const claseEstadoEquipo = (estado) => {
  if (estado === 'disponible') return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
  if (estado === 'instalado') return 'bg-sky-50 text-sky-700 ring-sky-200'
  if (estado === 'en_reparacion') return 'bg-amber-50 text-amber-800 ring-amber-200'
  return 'bg-rose-50 text-rose-700 ring-rose-200'
}

const etiquetaEstadoEquipo = (estado) => {
  if (estado === 'en_reparacion') return 'En reparación'
  return estado.charAt(0).toUpperCase() + estado.slice(1)
}

onMounted(async () => {
  await Promise.all([cargarCatalogoEquipos(), cargarEquipos(), cargarStock()])
})
</script>

<template>
  <AppLayout>
    <div class="space-y-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-slate-900">Inventario de equipos</h1>
          <p class="text-sm text-slate-600 mt-1">
            Control serializado de entradas, instalaciones en contratos, stock calculado y conteo físico.
          </p>
        </div>
        <button
          type="button"
          class="inline-flex items-center justify-center rounded-xl bg-[#1e3a5f] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#162d4b] transition-colors cursor-pointer"
          @click="abrirModalEntrada"
        >
          + Registrar entrada
        </button>
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
          <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Disponibles en almacén</p>
          <p class="mt-1 text-2xl font-bold text-emerald-700">{{ metaEquipos.disponibles }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
          <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Instalados en clientes</p>
          <p class="mt-1 text-2xl font-bold text-sky-700">{{ metaEquipos.instalados }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
          <p class="text-xs font-medium uppercase tracking-wider text-slate-500">En reparación</p>
          <p class="mt-1 text-2xl font-bold text-amber-700">{{ metaEquipos.en_reparacion }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
          <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Dados de baja</p>
          <p class="mt-1 text-2xl font-bold text-rose-700">{{ metaEquipos.baja }}</p>
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

      <div class="border-b border-slate-200 flex gap-6 text-sm font-medium">
        <button
          type="button"
          class="pb-3 border-b-2 transition-colors cursor-pointer"
          :class="pestana === 'equipos' ? 'border-[#1e3a5f] text-[#1e3a5f] font-semibold' : 'border-transparent text-slate-500 hover:text-slate-800'"
          @click="cambiarPestana('equipos')"
        >
          Equipos registrados
        </button>
        <button
          type="button"
          class="pb-3 border-b-2 transition-colors cursor-pointer"
          :class="pestana === 'stock' ? 'border-[#1e3a5f] text-[#1e3a5f] font-semibold' : 'border-transparent text-slate-500 hover:text-slate-800'"
          @click="cambiarPestana('stock')"
        >
          Stock por tipo
        </button>
        <button
          type="button"
          class="pb-3 border-b-2 transition-colors cursor-pointer"
          :class="pestana === 'exactitud' ? 'border-[#1e3a5f] text-[#1e3a5f] font-semibold' : 'border-transparent text-slate-500 hover:text-slate-800'"
          @click="cambiarPestana('exactitud')"
        >
          Conteo físico y exactitud
        </button>
      </div>

      <section v-if="pestana === 'equipos'" class="space-y-4">
        <form
          class="flex flex-col gap-3 sm:flex-row rounded-2xl border border-slate-200 bg-[#f5f4ee]/60 p-4"
          @submit.prevent="cargarEquipos(1)"
        >
          <input
            v-model="buscarEquipo"
            type="text"
            placeholder="Buscar por serie, marca o tipo de equipo..."
            class="flex-1 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm text-slate-900 focus:border-[#1e3a5f] focus:outline-none"
          />
          <select
            v-model="estadoFiltro"
            class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#1e3a5f] focus:outline-none"
          >
            <option value="">Todos los estados</option>
            <option value="disponible">Disponible</option>
            <option value="instalado">Instalado</option>
            <option value="en_reparacion">En reparación</option>
            <option value="baja">Baja</option>
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
                  <th class="px-4 py-3.5">Serie</th>
                  <th class="px-4 py-3.5">Equipo / Catálogo</th>
                  <th class="px-4 py-3.5">Marca</th>
                  <th class="px-4 py-3.5">Estado</th>
                  <th class="px-4 py-3.5">Ubicación / Contrato</th>
                  <th class="px-4 py-3.5 text-right">Acciones</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200">
                <tr v-if="cargandoEquipos">
                  <td colspan="6" class="px-4 py-8 text-center text-slate-500">Cargando equipos...</td>
                </tr>
                <tr v-else-if="equipos.length === 0">
                  <td colspan="6" class="px-4 py-8 text-center text-slate-500">No hay equipos con el filtro aplicado.</td>
                </tr>
                <tr v-for="eq in equipos" :key="eq.id" class="hover:bg-slate-50/70">
                  <td class="px-4 py-3.5 font-mono font-semibold text-slate-900">{{ eq.serie }}</td>
                  <td class="px-4 py-3.5">
                    <div class="font-medium text-slate-900">{{ eq.catalogo_servicio?.nombre }}</div>
                    <div class="text-xs text-slate-500">{{ eq.catalogo_servicio?.codigo_interno }}</div>
                  </td>
                  <td class="px-4 py-3.5 text-slate-700">{{ eq.marca }}</td>
                  <td class="px-4 py-3.5">
                    <span
                      class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset"
                      :class="claseEstadoEquipo(eq.estado)"
                    >
                      {{ etiquetaEstadoEquipo(eq.estado) }}
                    </span>
                  </td>
                  <td class="px-4 py-3.5 text-xs text-slate-600">
                    <template v-if="eq.instalaciones?.length">
                      <div class="font-semibold text-slate-900">
                        {{ eq.instalaciones[0].contrato?.codigo_contrato }}
                      </div>
                      <div>{{ eq.instalaciones[0].contrato?.cliente?.nombres }}</div>
                    </template>
                    <span v-else>{{ eq.estado === 'disponible' ? 'En almacén' : 'Sin contrato activo' }}</span>
                  </td>
                  <td class="px-4 py-3.5 text-right whitespace-nowrap space-x-2">
                    <button
                      v-if="eq.estado === 'disponible'"
                      type="button"
                      class="rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 cursor-pointer"
                      @click="abrirModalInstalar(eq)"
                    >
                      Instalar
                    </button>
                    <button
                      v-if="eq.estado === 'disponible' || eq.estado === 'instalado'"
                      type="button"
                      class="rounded-lg border border-amber-300 px-2.5 py-1.5 text-xs font-medium text-amber-800 hover:bg-amber-50 cursor-pointer"
                      @click="abrirModalSalida(eq)"
                    >
                      Salida
                    </button>
                    <button
                      type="button"
                      class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 cursor-pointer"
                      @click="verDetalleEquipo(eq)"
                    >
                      Historial
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div
            v-if="metaEquipos.last_page > 1"
            class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600"
          >
            <span>Página {{ metaEquipos.current_page }} de {{ metaEquipos.last_page }}</span>
            <div class="flex gap-2">
              <button
                type="button"
                :disabled="metaEquipos.current_page <= 1"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 disabled:opacity-40 cursor-pointer"
                @click="cargarEquipos(metaEquipos.current_page - 1)"
              >
                Anterior
              </button>
              <button
                type="button"
                :disabled="metaEquipos.current_page >= metaEquipos.last_page"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 disabled:opacity-40 cursor-pointer"
                @click="cargarEquipos(metaEquipos.current_page + 1)"
              >
                Siguiente
              </button>
            </div>
          </div>
        </div>
      </section>

      <section v-if="pestana === 'stock'" class="space-y-4">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
              <thead class="bg-[#f5f4ee] text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                <tr>
                  <th class="px-4 py-3.5">Código</th>
                  <th class="px-4 py-3.5">Tipo de equipo</th>
                  <th class="px-4 py-3.5 text-right">Disponibles</th>
                  <th class="px-4 py-3.5 text-right">Instalados</th>
                  <th class="px-4 py-3.5 text-right">En reparación</th>
                  <th class="px-4 py-3.5 text-right">Baja</th>
                  <th class="px-4 py-3.5 text-right">Total</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200">
                <tr v-if="cargandoStock">
                  <td colspan="7" class="px-4 py-8 text-center text-slate-500">Calculando stock...</td>
                </tr>
                <tr v-else-if="stockItems.length === 0">
                  <td colspan="7" class="px-4 py-8 text-center text-slate-500">No hay ítems de tipo equipo activos en el catálogo.</td>
                </tr>
                <tr v-for="item in stockItems" :key="item.id" class="hover:bg-slate-50/70">
                  <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ item.codigo_interno }}</td>
                  <td class="px-4 py-3 font-medium text-slate-900">{{ item.nombre }}</td>
                  <td class="px-4 py-3 text-right font-bold text-emerald-700">{{ item.stock_disponible }}</td>
                  <td class="px-4 py-3 text-right text-sky-700">{{ item.instalados }}</td>
                  <td class="px-4 py-3 text-right text-amber-700">{{ item.en_reparacion }}</td>
                  <td class="px-4 py-3 text-right text-rose-700">{{ item.baja }}</td>
                  <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ item.total_unidades }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <section v-if="pestana === 'exactitud'" class="space-y-6">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <div class="rounded-2xl border border-slate-200 bg-white p-5 space-y-4">
            <h2 class="text-base font-semibold text-slate-900">Registrar conteo físico en almacén</h2>
            <p class="text-xs text-slate-600">
              Ingresa las series verificadas físicamente (una por línea o separadas por comas). Se compararán contra los equipos marcados como <strong>disponible</strong>.
            </p>
            <textarea
              v-model="seriesTexto"
              rows="6"
              placeholder="SN-ONU-0001&#10;SN-ONU-0002&#10;SN-TPL-0003"
              class="w-full rounded-xl border border-slate-300 px-3 py-2 font-mono text-sm focus:border-[#1e3a5f] focus:outline-none"
            />
            <button
              type="button"
              :disabled="calculandoExactitud"
              class="rounded-xl bg-[#1e3a5f] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#162d4b] disabled:opacity-50 cursor-pointer"
              @click="ejecutarConteoExactitud"
            >
              {{ calculandoExactitud ? 'Calculando...' : 'Calcular exactitud de inventario' }}
            </button>
          </div>

          <div v-if="resultadoConteo" class="rounded-2xl border border-slate-200 bg-white p-5 space-y-4">
            <div class="flex items-center justify-between">
              <h2 class="text-base font-semibold text-slate-900">Resultado del conteo</h2>
              <span class="rounded-full bg-[#ebf2fb] px-3 py-1 text-sm font-bold text-[#1e3a5f]">
                {{ resultadoConteo.exactitud }}% exactitud
              </span>
            </div>

            <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
              <div class="rounded-xl bg-slate-50 p-3">
                <div class="text-xs text-slate-500">Sistema</div>
                <div class="font-bold text-slate-900">{{ resultadoConteo.total_sistema }}</div>
              </div>
              <div class="rounded-xl bg-slate-50 p-3">
                <div class="text-xs text-slate-500">Físico</div>
                <div class="font-bold text-slate-900">{{ resultadoConteo.total_fisico }}</div>
              </div>
              <div class="rounded-xl bg-emerald-50 p-3">
                <div class="text-xs text-emerald-700">Coincidentes</div>
                <div class="font-bold text-emerald-800">{{ resultadoConteo.coincidentes }}</div>
              </div>
              <div class="rounded-xl bg-sky-50 p-3">
                <div class="text-xs text-sky-700">Verificados</div>
                <div class="font-bold text-sky-800">{{ resultadoConteo.verificados }}</div>
              </div>
            </div>

            <div class="space-y-2 text-xs">
              <div v-if="resultadoConteo.discrepancias?.faltantes?.length">
                <p class="font-semibold text-rose-700">
                  Faltantes en físico ({{ resultadoConteo.discrepancias.faltantes.length }}):
                </p>
                <ul class="mt-1 list-disc pl-5 text-slate-700">
                  <li v-for="f in resultadoConteo.discrepancias.faltantes" :key="f.serie">
                    <span class="font-mono font-semibold">{{ f.serie }}</span> — {{ f.equipo || 'Equipo' }}
                  </li>
                </ul>
              </div>
              <div v-if="resultadoConteo.discrepancias?.sobrantes?.length">
                <p class="font-semibold text-amber-700">
                  Sobrantes o fuera de almacén ({{ resultadoConteo.discrepancias.sobrantes.length }}):
                </p>
                <ul class="mt-1 list-disc pl-5 text-slate-700">
                  <li v-for="s in resultadoConteo.discrepancias.sobrantes" :key="s.serie">
                    <span class="font-mono font-semibold">{{ s.serie }}</span> ({{ s.tipo }})
                  </li>
                </ul>
              </div>
              <p
                v-if="!resultadoConteo.discrepancias?.faltantes?.length && !resultadoConteo.discrepancias?.sobrantes?.length"
                class="text-emerald-700 font-medium"
              >
                Sin discrepancias: el conteo físico coincide al 100% con el sistema.
              </p>
            </div>
          </div>
        </div>

        <div v-if="historialConteos.length" class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
          <div class="border-b border-slate-200 bg-[#f5f4ee] px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-600">
            Últimos conteos físicos
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
              <thead class="text-left text-xs text-slate-500">
                <tr>
                  <th class="px-4 py-2.5">Fecha</th>
                  <th class="px-4 py-2.5">Responsable</th>
                  <th class="px-4 py-2.5 text-right">Sistema</th>
                  <th class="px-4 py-2.5 text-right">Físico</th>
                  <th class="px-4 py-2.5 text-right">Coincidentes</th>
                  <th class="px-4 py-2.5 text-right">Exactitud</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200">
                <tr v-for="c in historialConteos" :key="c.id">
                  <td class="px-4 py-2.5 text-slate-700">{{ c.fecha?.slice(0, 16).replace('T', ' ') }}</td>
                  <td class="px-4 py-2.5 text-slate-700">{{ c.usuario?.nombre }}</td>
                  <td class="px-4 py-2.5 text-right">{{ c.total_sistema }}</td>
                  <td class="px-4 py-2.5 text-right">{{ c.total_fisico }}</td>
                  <td class="px-4 py-2.5 text-right">{{ c.coincidentes }} / {{ c.verificados }}</td>
                  <td class="px-4 py-2.5 text-right font-semibold text-[#1e3a5f]">{{ c.exactitud }}%</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>

    <BaseModal :abierto="modalEntradaAbierto" titulo="Registrar entrada de equipo" @cerrar="modalEntradaAbierto = false">
      <form class="space-y-4" @submit.prevent="registrarEntrada">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Tipo de equipo *</label>
          <select
            v-model="formEntrada.catalogo_id"
            required
            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
          >
            <option v-for="item in catalogoEquipos" :key="item.id" :value="item.id">
              {{ item.codigo_interno }} — {{ item.nombre }}
            </option>
          </select>
          <p v-if="erroresForm.catalogo_id" class="mt-1 text-xs text-rose-600">{{ erroresForm.catalogo_id[0] }}</p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Marca *</label>
            <input
              v-model="formEntrada.marca"
              type="text"
              required
              placeholder="TP-Link / Huawei"
              class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
            />
            <p v-if="erroresForm.marca" class="mt-1 text-xs text-rose-600">{{ erroresForm.marca[0] }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Número de serie *</label>
            <input
              v-model="formEntrada.serie"
              type="text"
              required
              placeholder="SN-2026-0001"
              class="w-full rounded-xl border border-slate-300 px-3 py-2 font-mono text-sm focus:border-[#1e3a5f] focus:outline-none"
            />
            <p v-if="erroresForm.serie" class="mt-1 text-xs text-rose-600">{{ erroresForm.serie[0] }}</p>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Motivo de ingreso</label>
          <input
            v-model="formEntrada.motivo"
            type="text"
            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
          />
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button
            type="button"
            class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 cursor-pointer"
            @click="modalEntradaAbierto = false"
          >
            Cancelar
          </button>
          <button
            type="submit"
            :disabled="guardando"
            class="rounded-xl bg-[#1e3a5f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#162d4b] disabled:opacity-50 cursor-pointer"
          >
            {{ guardando ? 'Guardando...' : 'Registrar entrada' }}
          </button>
        </div>
      </form>
    </BaseModal>

    <BaseModal :abierto="modalInstalarAbierto" titulo="Instalar equipo en contrato" @cerrar="modalInstalarAbierto = false">
      <form v-if="equipoSeleccionado" class="space-y-4" @submit.prevent="confirmarInstalacion">
        <div class="rounded-xl bg-[#f5f4ee] p-3 text-sm">
          <div class="font-semibold text-slate-900">{{ equipoSeleccionado.catalogo_servicio?.nombre }}</div>
          <div class="text-xs text-slate-600">Serie: <span class="font-mono font-semibold">{{ equipoSeleccionado.serie }}</span> · Marca: {{ equipoSeleccionado.marca }}</div>
        </div>

        <div class="flex gap-2">
          <input
            v-model="buscarContratoTexto"
            type="text"
            placeholder="Filtrar contrato por código, DNI o nombre..."
            class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
          />
          <button
            type="button"
            class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-100 cursor-pointer"
            @click="buscarContratos"
          >
            Buscar
          </button>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Contrato destino *</label>
          <select
            v-model="formInstalar.contrato_id"
            required
            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
          >
            <option value="">Selecciona un contrato activo...</option>
            <option v-for="c in contratosDisponibles" :key="c.id" :value="c.id">
              {{ c.codigo_contrato }} — {{ c.cliente?.nombres }} ({{ c.cliente?.dni }})
            </option>
          </select>
          <p v-if="erroresForm.contrato_id" class="mt-1 text-xs text-rose-600">{{ erroresForm.contrato_id[0] }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Fecha de instalación</label>
          <input
            v-model="formInstalar.fecha_instalacion"
            type="date"
            required
            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
          />
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button
            type="button"
            class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 cursor-pointer"
            @click="modalInstalarAbierto = false"
          >
            Cancelar
          </button>
          <button
            type="submit"
            :disabled="guardando"
            class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50 cursor-pointer"
          >
            {{ guardando ? 'Instalando...' : 'Confirmar instalación' }}
          </button>
        </div>
      </form>
    </BaseModal>

    <BaseModal :abierto="modalSalidaAbierto" titulo="Registrar salida de equipo" @cerrar="modalSalidaAbierto = false">
      <form v-if="equipoSeleccionado" class="space-y-4" @submit.prevent="confirmarSalida">
        <div class="rounded-xl bg-[#f5f4ee] p-3 text-sm">
          <div class="font-semibold text-slate-900">{{ equipoSeleccionado.serie }}</div>
          <div class="text-xs text-slate-600">{{ equipoSeleccionado.catalogo_servicio?.nombre }}</div>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Nuevo estado *</label>
          <select
            v-model="formSalida.estado"
            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
          >
            <option value="en_reparacion">En reparación</option>
            <option value="baja">Baja definitiva</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Motivo *</label>
          <input
            v-model="formSalida.motivo"
            type="text"
            required
            placeholder="Falla de fuente / Equipo dañado"
            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-[#1e3a5f] focus:outline-none"
          />
          <p v-if="erroresForm.motivo" class="mt-1 text-xs text-rose-600">{{ erroresForm.motivo[0] }}</p>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button
            type="button"
            class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 cursor-pointer"
            @click="modalSalidaAbierto = false"
          >
            Cancelar
          </button>
          <button
            type="submit"
            :disabled="guardando"
            class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-50 cursor-pointer"
          >
            {{ guardando ? 'Guardando...' : 'Registrar salida' }}
          </button>
        </div>
      </form>
    </BaseModal>

    <BaseModal :abierto="modalDetalleAbierto" titulo="Historial de movimientos del equipo" @cerrar="modalDetalleAbierto = false">
      <div v-if="detalleEquipo" class="space-y-4 text-sm">
        <div class="rounded-xl bg-[#f5f4ee] p-3">
          <div class="font-mono font-bold text-slate-900">{{ detalleEquipo.serie }}</div>
          <div class="text-xs text-slate-600">{{ detalleEquipo.catalogo_servicio?.nombre }} · {{ detalleEquipo.marca }}</div>
        </div>

        <div>
          <h4 class="font-semibold text-slate-800 mb-2">Movimientos</h4>
          <ul class="space-y-2 max-h-56 overflow-y-auto">
            <li
              v-for="m in detalleEquipo.movimientos"
              :key="m.id"
              class="rounded-xl border border-slate-200 px-3 py-2 text-xs"
            >
              <div class="flex items-center justify-between font-semibold">
                <span :class="m.tipo_movimiento === 'entrada' ? 'text-emerald-700' : 'text-amber-700'">
                  {{ m.tipo_movimiento.toUpperCase() }}
                </span>
                <span class="text-slate-500">{{ m.fecha?.slice(0, 16).replace('T', ' ') }}</span>
              </div>
              <div class="text-slate-700 mt-0.5">{{ m.motivo || 'Sin motivo' }}</div>
              <div class="text-slate-400">Responsable: {{ m.usuario?.nombre }}</div>
            </li>
          </ul>
        </div>
      </div>
    </BaseModal>
  </AppLayout>
</template>
