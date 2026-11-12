<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AppLayout from '../components/AppLayout.vue'
import api from '../services/api'

const cargando = ref(false)
const mensajeError = ref('')
const periodoFiltro = ref('')
const indicadores = ref({
  clientes_activos: 0,
  ingresos_mes: 0,
  tasa_morosidad: 0,
  pagos_vencidos: 0,
  total_pagos_evaluados: 0,
  reclamos_abiertos: 0,
  reclamos_sin_asignar: 0,
  reclamos_en_proceso: 0,
  reclamos_resueltos: 0,
  exactitud_inventario: null,
  ultimo_conteo_fecha: null,
  stock_disponible: 0,
  items_mas_vendidos: [],
  alertas_pendientes: 0,
  mes_actual: '',
})

const maxVendidos = computed(() => {
  const cantidades = indicadores.value.items_mas_vendidos.map((i) => i.cantidad_total)
  return Math.max(1, ...cantidades)
})

const cargarDashboard = async () => {
  cargando.value = true
  mensajeError.value = ''

  try {
    const params = {}
    if (periodoFiltro.value.trim() !== '') {
      params.periodo = periodoFiltro.value.trim()
    }
    const { data } = await api.get('/dashboard', { params })
    indicadores.value = data.data
  } catch (error) {
    mensajeError.value = error.response?.data?.message || 'No se pudieron cargar los indicadores del dashboard.'
  } finally {
    cargando.value = false
  }
}

const formatoMoneda = (valor) => `S/ ${Number(valor || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

onMounted(() => cargarDashboard())
</script>

<template>
  <AppLayout>
    <div class="space-y-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-slate-900">Dashboard gerencial</h1>
          <p class="text-sm text-slate-600 mt-1">
            Indicadores clave de gestión operativa, cobranza, reclamos e inventario.
          </p>
        </div>

        <form class="flex items-center gap-2" @submit.prevent="cargarDashboard">
          <input
            v-model="periodoFiltro"
            type="month"
            aria-label="Periodo de morosidad"
            class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#1e3a5f] focus:outline-none"
          />
          <button
            type="submit"
            :disabled="cargando"
            class="rounded-xl bg-[#1e3a5f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#162d4b] disabled:opacity-50 cursor-pointer"
          >
            {{ cargando ? 'Actualizando...' : 'Actualizar' }}
          </button>
        </form>
      </div>

      <div
        v-if="mensajeError"
        class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
      >
        {{ mensajeError }}
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
          <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Contratos activos</p>
          <p class="mt-2 text-3xl font-bold text-[#1e3a5f]">
            {{ indicadores.clientes_activos.toLocaleString('es-PE') }}
          </p>
          <p class="mt-1 text-xs text-slate-500">Servicios vigentes instalados</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5">
          <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Ingresos del mes ({{ indicadores.mes_actual }})</p>
          <p class="mt-2 text-3xl font-bold text-emerald-700">
            {{ formatoMoneda(indicadores.ingresos_mes) }}
          </p>
          <p class="mt-1 text-xs text-slate-500">Suma de pagos cobrados en el mes</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5">
          <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Tasa de morosidad</p>
          <p class="mt-2 text-3xl font-bold text-rose-700">{{ indicadores.tasa_morosidad }}%</p>
          <p class="mt-1 text-xs text-slate-500">
            {{ indicadores.pagos_vencidos.toLocaleString('es-PE') }} vencidos de
            {{ indicadores.total_pagos_evaluados.toLocaleString('es-PE') }} recibos
          </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5">
          <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Reclamos técnicos</p>
          <div class="mt-2 flex items-baseline gap-4">
            <div>
              <span class="text-3xl font-bold text-amber-700">{{ indicadores.reclamos_abiertos }}</span>
              <span class="ml-1 text-xs text-slate-600">activos</span>
            </div>
            <div>
              <span class="text-2xl font-bold text-emerald-700">{{ indicadores.reclamos_resueltos }}</span>
              <span class="ml-1 text-xs text-slate-600">resueltos</span>
            </div>
          </div>
          <p class="mt-1 text-xs text-slate-500">
            Sin asignar: {{ indicadores.reclamos_sin_asignar }} · En proceso: {{ indicadores.reclamos_en_proceso }}
          </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5">
          <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Exactitud de inventario</p>
          <p class="mt-2 text-3xl font-bold text-[#1e3a5f]">
            {{ indicadores.exactitud_inventario !== null ? `${indicadores.exactitud_inventario}%` : 'Sin conteo' }}
          </p>
          <p class="mt-1 text-xs text-slate-500">
            Stock disponible actual: {{ indicadores.stock_disponible }} equipos
          </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5">
          <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Alertas de vencimiento pendientes</p>
          <p class="mt-2 text-3xl font-bold text-amber-700">
            {{ indicadores.alertas_pendientes.toLocaleString('es-PE') }}
          </p>
          <RouterLink to="/alertas" class="mt-1 inline-block text-xs font-semibold text-[#1e3a5f] hover:underline">
            Revisar alertas →
          </RouterLink>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-5">
          <h2 class="text-base font-semibold text-slate-900 mb-4">Ítems más vendidos (Top 5 por cantidad)</h2>
          <div v-if="indicadores.items_mas_vendidos.length === 0" class="py-8 text-center text-sm text-slate-500">
            Aún no hay ventas registradas para calcular el ranking.
          </div>
          <div v-else class="space-y-4">
            <div v-for="item in indicadores.items_mas_vendidos" :key="item.catalogo_id" class="space-y-1.5">
              <div class="flex items-center justify-between text-sm">
                <div>
                  <span class="font-mono text-xs text-slate-500 mr-2">{{ item.codigo_interno }}</span>
                  <span class="font-medium text-slate-900">{{ item.nombre }}</span>
                </div>
                <div class="text-right whitespace-nowrap">
                  <span class="font-bold text-[#1e3a5f]">{{ item.cantidad_total }} und</span>
                  <span class="text-xs text-slate-500 ml-2">({{ formatoMoneda(item.monto_total) }})</span>
                </div>
              </div>
              <div class="h-2.5 w-full rounded-full bg-slate-100 overflow-hidden">
                <div
                  class="h-full rounded-full bg-[#1e3a5f]"
                  :style="{ width: `${Math.round((item.cantidad_total / maxVendidos) * 100)}%` }"
                />
              </div>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-[#f5f4ee]/70 p-5 space-y-3">
          <h2 class="text-base font-semibold text-slate-900">Accesos operativos</h2>
          <div class="space-y-2 text-sm">
            <RouterLink to="/ventas" class="block rounded-xl bg-white border border-slate-200 px-4 py-2.5 font-medium text-slate-800 hover:border-[#1e3a5f]">
              Registrar nueva venta →
            </RouterLink>
            <RouterLink to="/cobranzas" class="block rounded-xl bg-white border border-slate-200 px-4 py-2.5 font-medium text-slate-800 hover:border-[#1e3a5f]">
              Cobranzas y morosidad →
            </RouterLink>
            <RouterLink to="/inventario" class="block rounded-xl bg-white border border-slate-200 px-4 py-2.5 font-medium text-slate-800 hover:border-[#1e3a5f]">
              Inventario y conteo físico →
            </RouterLink>
            <RouterLink to="/reclamos" class="block rounded-xl bg-white border border-slate-200 px-4 py-2.5 font-medium text-slate-800 hover:border-[#1e3a5f]">
              Atención de reclamos →
            </RouterLink>
            <RouterLink to="/auditoria" class="block rounded-xl bg-white border border-slate-200 px-4 py-2.5 font-medium text-slate-800 hover:border-[#1e3a5f]">
              Bitácora de auditoría →
            </RouterLink>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
