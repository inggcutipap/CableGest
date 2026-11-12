import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import LoginView from '../views/LoginView.vue'
import ClientesView from '../views/ClientesView.vue'
import CatalogoView from '../views/CatalogoView.vue'
import VentasView from '../views/VentasView.vue'
import ContratosView from '../views/ContratosView.vue'
import CobranzasView from '../views/CobranzasView.vue'
import AlertasView from '../views/AlertasView.vue'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: LoginView,
    meta: { guestOnly: true },
  },
  {
    path: '/',
    redirect: '/clientes',
  },
  {
    path: '/clientes',
    name: 'clientes',
    component: ClientesView,
    meta: { requiresAuth: true, modulo: 'clientes' },
  },
  {
    path: '/catalogo',
    name: 'catalogo',
    component: CatalogoView,
    meta: { requiresAuth: true, modulo: 'catalogo' },
  },
  {
    path: '/ventas',
    name: 'ventas',
    component: VentasView,
    meta: { requiresAuth: true, modulo: 'ventas' },
  },
  {
    path: '/contratos',
    name: 'contratos',
    component: ContratosView,
    meta: { requiresAuth: true, modulo: 'contratos' },
  },
  {
    path: '/cobranzas',
    name: 'cobranzas',
    component: CobranzasView,
    meta: { requiresAuth: true, modulo: 'cobranzas' },
  },
  {
    path: '/alertas',
    name: 'alertas',
    component: AlertasView,
    meta: { requiresAuth: true, modulo: 'alertas' },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  const { estaAutenticado, usuario, cargarUsuario, limpiarSesion } = useAuth()

  if (to.meta.requiresAuth) {
    if (!estaAutenticado.value) {
      return { name: 'login' }
    }
    if (!usuario.value) {
      try {
        await cargarUsuario()
      } catch {
        limpiarSesion()
        return { name: 'login' }
      }
    }
  }

  if (to.meta.guestOnly && estaAutenticado.value) {
    return { name: 'clientes' }
  }

  return true
})

export default router

