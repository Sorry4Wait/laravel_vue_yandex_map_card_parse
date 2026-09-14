import { ref } from 'vue'
import { api, primeCsrfCookie } from '../lib/api'
import type { User } from '../types'

// module-level state on purpose - auth is used by the router guard and by
// every view, a pinia store would just be this with extra ceremony
const user = ref<User | null>(null)
const checkedAuth = ref(false)

async function fetchUser() {
  try {
    const { data } = await api.get('/api/user')
    user.value = data.user
  } catch {
    user.value = null
  } finally {
    checkedAuth.value = true
  }
}

async function login(email: string, password: string) {
  await primeCsrfCookie()
  const { data } = await api.post('/api/login', { email, password })
  user.value = data.user
}

async function logout() {
  await api.post('/api/logout')
  user.value = null
}

export function useAuth() {
  return { user, checkedAuth, fetchUser, login, logout }
}
