<script setup lang="ts">
import { ref } from 'vue'

const props = defineProps<{
  initialUrl?: string | null
  submitting: boolean
  error?: string | null
}>()

const emit = defineEmits<{
  save: [url: string]
}>()

const url = ref(props.initialUrl ?? '')

function handleSubmit() {
  if (!url.value.trim()) return
  emit('save', url.value.trim())
}
</script>

<template>
  <form class="link-form" @submit.prevent="handleSubmit">
    <label class="field">
      <span>Yandex Maps organization link</span>
      <input
        v-model="url"
        type="url"
        placeholder="https://yandex.ru/maps/org/.../1234567890/"
        required
      />
    </label>

    <p v-if="error" class="error-text">{{ error }}</p>

    <button type="submit" class="primary-button" :disabled="submitting">
      {{ submitting ? 'Saving…' : 'Save & fetch reviews' }}
    </button>
  </form>
</template>

<style scoped>
.link-form {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
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
  margin: 0;
}

.primary-button {
  align-self: flex-start;
  padding: 0.55rem 1.1rem;
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
