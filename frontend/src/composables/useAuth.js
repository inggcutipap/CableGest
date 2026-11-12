import { computed, ref } from 'vue'
import api, { TOKEN_KEY, USUARIO_KEY } from '../services/api'

const leerUsuarioGuardado = () => {
  try {
    const bruto = localStorage.getItem(USUARIO_KEY)
    return bruto ? JSON.parse(bruto) : null
  } catch {
    return null
  }
}

const token = ref(localStorage.getItem(TOKEN_KEY) || '')
const usuario = ref(leerUsuarioGuardado())

export function useAuth() {
  const estaAutenticado = computed(() => Boolean(token.value))

  const guardarSesion = (nuevoToken, nuevoUsuario) => {
    token.value = nuevoToken
    usuario.value = nuevoUsuario
    localStorage.setItem(TOKEN_KEY, nuevoToken)
    localStorage.setItem(USUARIO_KEY, JSON.stringify(nuevoUsuario))
  }

  const limpiarSesion = () => {
    token.value = ''
    usuario.value = null
    localStorage.removeItem(TOKEN_KEY)
    localStorage.removeItem(USUARIO_KEY)
  }

  const iniciarSesion = async (correo, password) => {
    const { data } = await api.post('/login', { correo, password })
    guardarSesion(data.data.token, data.data.usuario)
    return data.data.usuario
  }

  const cargarUsuario = async () => {
    if (!token.value) {
      return null
    }
    const { data } = await api.get('/me')
    usuario.value = data.data
    localStorage.setItem(USUARIO_KEY, JSON.stringify(data.data))
    return data.data
  }

  const cerrarSesion = async () => {
    try {
      if (token.value) {
        await api.post('/logout')
      }
    } finally {
      limpiarSesion()
    }
  }

  return {
    token,
    usuario,
    estaAutenticado,
    iniciarSesion,
    cargarUsuario,
    cerrarSesion,
    limpiarSesion,
  }
}
