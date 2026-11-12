<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'

const router = useRouter()
const { iniciarSesion } = useAuth()

const correo = ref('')
const password = ref('')
const cargando = ref(false)
const mensajeError = ref('')

const enviarFormulario = async () => {
  mensajeError.value = ''
  cargando.value = true

  try {
    await iniciarSesion(correo.value.trim(), password.value)
    router.push({ name: 'dashboard' })
  } catch (error) {
    mensajeError.value =
      error.response?.data?.message ||
      'No se pudo iniciar sesión. Verifica tus credenciales.'
  } finally {
    cargando.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex flex-col bg-[#f5f4ee]">
    <header class="bg-[#1e3a5f] text-white shadow-sm">
      <div class="mx-auto flex h-14 max-w-7xl items-center justify-between px-4 sm:px-6">
        <div class="flex items-center gap-2 font-semibold text-lg">
          <svg class="h-5 w-5 text-amber-300" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M13 2 3 14h8l-1 8 10-12h-8l1-8Z" />
          </svg>
          <span>CableGest</span>
        </div>
        <span class="text-xs sm:text-sm text-slate-200">
          Corporación Global de Telecomunicaciones S.A.C.
        </span>
      </div>
    </header>

    <main class="flex flex-1 items-center justify-center px-4 py-12">
      <div class="w-full max-w-md rounded-2xl bg-white/80 p-8 shadow-sm border border-slate-200/80">
        <div class="text-center mb-7">
          <div class="inline-flex items-center justify-center gap-2 text-2xl font-bold text-[#1e3a5f]">
            <svg class="h-7 w-7 text-amber-500" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <path d="M13 2 3 14h8l-1 8 10-12h-8l1-8Z" />
            </svg>
            <span>CableGest</span>
          </div>
          <p class="mt-1 text-sm text-slate-600">Sistema de gestión operativa</p>
        </div>

        <div
          v-if="mensajeError"
          class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
        >
          {{ mensajeError }}
        </div>

        <form class="space-y-4" @submit.prevent="enviarFormulario">
          <div>
            <label for="correo" class="block text-sm font-medium text-slate-700 mb-1">
              Usuario
            </label>
            <input
              id="correo"
              v-model="correo"
              type="email"
              required
              autocomplete="username"
              placeholder="admin@cablevision.pe"
              class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 focus:border-[#1e3a5f] focus:outline-none focus:ring-2 focus:ring-[#1e3a5f]/20"
            />
          </div>

          <div>
            <label for="password" class="block text-sm font-medium text-slate-700 mb-1">
              Contraseña
            </label>
            <input
              id="password"
              v-model="password"
              type="password"
              required
              autocomplete="current-password"
              placeholder="••••••••"
              class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-slate-900 focus:border-[#1e3a5f] focus:outline-none focus:ring-2 focus:ring-[#1e3a5f]/20"
            />
          </div>

          <button
            type="submit"
            :disabled="cargando"
            class="w-full rounded-xl border border-[#1e3a5f] bg-[#1e3a5f] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#162d4b] disabled:opacity-60 transition-colors cursor-pointer"
          >
            {{ cargando ? 'Ingresando...' : 'Iniciar sesión' }}
          </button>
        </form>

        <p class="mt-6 text-center text-xs text-slate-500">
          Laravel 12 + Vue.js 3 · JWT Auth
        </p>
      </div>
    </main>
  </div>
</template>
