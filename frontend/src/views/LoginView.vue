<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'

const email = ref('')
const password = ref('')
const submitting = ref(false)
const error = ref<string | null>(null)

const { login } = useAuth()
const router = useRouter()
const route = useRoute()

async function handleSubmit() {
  submitting.value = true
  error.value = null

  try {
    await login(email.value, password.value)
    const redirect = (route.query.redirect as string) || '/settings'
    router.push(redirect)
  } catch (e: any) {
    error.value = e?.response?.status === 422
      ? 'Wrong email or password.'
      : 'Something went wrong, try again.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="login-card">
    <h1>Sign in</h1>

    <form @submit.prevent="handleSubmit">
      <label class="field">
        <span>Email</span>
        <input v-model="email" type="email" autocomplete="email" required />
      </label>

      <label class="field">
        <span>Password</span>
        <input v-model="password" type="password" autocomplete="current-password" required />
      </label>

      <p v-if="error" class="error-text">{{ error }}</p>

      <button type="submit" class="primary-button" :disabled="submitting">
        {{ submitting ? 'Signing in…' : 'Sign in' }}
      </button>
    </form>
  </div>
</template>

<style scoped>
.login-card {
  width: 100%;
  max-width: 360px;
  align-self: flex-start;
  margin-top: 4rem;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  padding: 2rem;
}

h1 {
  margin: 0 0 1.5rem;
  font-size: 1.25rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  margin-bottom: 1rem;
  font-size: 0.9rem;
}

input {
  padding: 0.5rem 0.65rem;
  border: 1px solid var(--color-border);
  border-radius: 6px;
  font: inherit;
}

.error-text {
  color: var(--color-danger);
  font-size: 0.85rem;
  margin: 0 0 1rem;
}

.primary-button {
  width: 100%;
  padding: 0.6rem;
  background: var(--color-primary);
  color: #fff;
  border: none;
  border-radius: 6px;
  font: inherit;
  cursor: pointer;
}

.primary-button:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.primary-button:disabled {
  opacity: 0.6;
  cursor: default;
}
</style>
