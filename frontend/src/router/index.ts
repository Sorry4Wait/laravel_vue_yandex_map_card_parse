import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from '../composables/useAuth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/settings' },
    { path: '/login', name: 'login', component: () => import('../views/LoginView.vue') },
    { path: '/settings', name: 'settings', component: () => import('../views/SettingsView.vue'), meta: { requiresAuth: true } },
  ],
})

router.beforeEach(async (to) => {
  const { user, checkedAuth, fetchUser } = useAuth()

  if (!checkedAuth.value) {
    await fetchUser()
  }

  if (to.meta.requiresAuth && !user.value) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.name === 'login' && user.value) {
    return { name: 'settings' }
  }
})

export default router
