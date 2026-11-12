<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'

const route = useRoute()
const router = useRouter()
const { usuario, cerrarSesion } = useAuth()

const moduloActual = computed(() => route.meta.modulo || 'clientes')

const navPrincipal = [
  { id: 'dashboard', etiqueta: 'Dashboard', ruta: '/dashboard', disponible: true },
  { id: 'clientes', etiqueta: 'Clientes', ruta: '/clientes', disponible: true },
  { id: 'catalogo', etiqueta: 'Catálogo', ruta: '/catalogo', disponible: true },
  { id: 'ventas', etiqueta: 'Ventas', ruta: '/ventas', disponible: true },
  { id: 'contratos', etiqueta: 'Contratos', ruta: '/contratos', disponible: true },
  { id: 'cobranzas', etiqueta: 'Cobranzas', ruta: '/cobranzas', disponible: true },
  { id: 'alertas', etiqueta: 'Alertas', ruta: '/alertas', disponible: true },
  { id: 'inventario', etiqueta: 'Inventario', ruta: '/inventario', disponible: true },
  { id: 'reclamos', etiqueta: 'Reclamos', ruta: '/reclamos', disponible: true },
  { id: 'auditoria', etiqueta: 'Auditoría', ruta: '/auditoria', disponible: true },
]

const navDisponibles = computed(() => navPrincipal.filter((item) => item.disponible))
const navProximos = computed(() => navPrincipal.filter((item) => !item.disponible))

const manejarLogout = async () => {
  await cerrarSesion()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen flex flex-col bg-white">
    <header class="bg-[#1e3a5f] text-white shadow-sm">
      <div class="mx-auto flex h-14 max-w-7xl items-center justify-between px-4 sm:px-6">
        <div class="flex items-center gap-8">
          <RouterLink to="/dashboard" class="flex items-center gap-2 font-semibold tracking-tight text-lg">
            <svg class="h-5 w-5 text-amber-300" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <path d="M13 2 3 14h8l-1 8 10-12h-8l1-8Z" />
            </svg>
            <span>CableGest</span>
          </RouterLink>

          <nav class="hidden md:flex items-center gap-5 text-sm">
            <template v-for="item in navPrincipal" :key="item.id">
              <RouterLink
                v-if="item.disponible"
                :to="item.ruta"
                class="py-1 transition-colors border-b-2"
                :class="
                  moduloActual === item.id
                    ? 'border-white font-semibold text-white'
                    : 'border-transparent text-slate-200 hover:text-white'
                "
              >
                {{ item.etiqueta }}
              </RouterLink>
              <span
                v-else
                class="cursor-not-allowed text-slate-400/80"
                title="Disponible en próximos sprints"
              >
                {{ item.etiqueta }}
              </span>
            </template>
          </nav>
        </div>

        <div class="flex items-center gap-4 text-sm">
          <span class="hidden sm:inline text-slate-200">
            {{ usuario?.nombre || 'Usuario' }}
          </span>
          <button
            type="button"
            class="rounded-lg border border-white/25 px-3 py-1.5 text-xs font-medium text-white hover:bg-white/10 transition-colors cursor-pointer"
            @click="manejarLogout"
          >
            Cerrar sesión
          </button>
        </div>
      </div>
    </header>

    <div class="flex flex-1 mx-auto w-full max-w-7xl">
      <aside class="w-56 shrink-0 border-r border-slate-200 bg-white py-6 hidden lg:block">
        <div class="px-4 mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
          Operación disponible
        </div>
        <nav class="space-y-1 px-2">
          <RouterLink
            v-for="item in navDisponibles"
            :key="item.id"
            :to="item.ruta"
            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors"
            :class="
              moduloActual === item.id
                ? 'bg-[#ebf2fb] font-semibold text-[#1e3a5f] border-l-4 border-[#1e3a5f]'
                : 'text-slate-600 hover:bg-slate-100'
            "
          >
            <span>{{ item.etiqueta }}</span>
          </RouterLink>
        </nav>

        <template v-if="navProximos.length > 0">
          <div class="px-4 mt-8 mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
            Próximos módulos
          </div>
          <div class="space-y-1 px-3 text-sm text-slate-400">
            <p v-for="item in navProximos" :key="item.id" class="py-1">{{ item.etiqueta }}</p>
          </div>
        </template>
      </aside>

      <main class="flex-1 p-5 sm:p-8 bg-white">
        <div class="mb-4 flex flex-wrap gap-2 lg:hidden">
          <RouterLink
            v-for="item in navDisponibles"
            :key="item.id"
            :to="item.ruta"
            class="rounded-lg px-3 py-1.5 text-sm font-medium border"
            :class="moduloActual === item.id ? 'bg-[#1e3a5f] text-white border-[#1e3a5f]' : 'bg-white text-slate-700 border-slate-300'"
          >
            {{ item.etiqueta }}
          </RouterLink>
        </div>
        <slot />
      </main>
    </div>
  </div>
</template>

