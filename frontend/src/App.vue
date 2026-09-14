<script setup lang="ts">
import { useAuth } from './composables/useAuth'
import { useRouter } from 'vue-router'

const { user, logout } = useAuth()
const router = useRouter()

async function handleLogout() {
  await logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="app-shell">
    <header v-if="user" class="app-header">
      <span class="app-title">Yandex Reviews</span>
      <div class="app-header__user">
        <span>{{ user.email }}</span>
        <button type="button" class="link-button" @click="handleLogout">Log out</button>
      </div>
    </header>

    <main class="app-content">
      <router-view />
    </main>
  </div>
</template>

<style>
:root {
  color-scheme: light;
  --color-bg: #f7f7f8;
  --color-surface: #ffffff;
  --color-border: #e2e2e6;
  --color-text: #1c1c1f;
  --color-text-muted: #6b6b73;
  --color-primary: #d6001c;
  --color-primary-hover: #b40017;
  --color-danger: #c0392b;
  --color-success: #1a7f3c;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
}

* {
  box-sizing: border-box;
}

body {
  margin: 0;
  background: var(--color-bg);
  color: var(--color-text);
}

.app-shell {
  height: 100vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.app-header {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 1.5rem;
  background: var(--color-surface);
  border-bottom: 1px solid var(--color-border);
}

.app-title {
  font-weight: 600;
}

.app-header__user {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  color: var(--color-text-muted);
  font-size: 0.9rem;
}

.app-content {
  flex: 1;
  min-height: 0;
  display: flex;
  justify-content: center;
  padding: 2rem 1rem;
  overflow: hidden;
}

.link-button {
  background: none;
  border: none;
  color: var(--color-primary);
  cursor: pointer;
  font: inherit;
  padding: 0;
}

.link-button:hover {
  color: var(--color-primary-hover);
}
</style>
