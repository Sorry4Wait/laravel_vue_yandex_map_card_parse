<script setup lang="ts">
import { computed } from 'vue'
import type { ParseRun } from '../../types'

const props = defineProps<{
  parseRun: ParseRun
}>()

const label = computed(() => {
  switch (props.parseRun.status) {
    case 'queued':
      return 'Parse is queued…'
    case 'running':
      return `Fetching reviews… ${props.parseRun.reviews_fetched} so far`
    case 'succeeded':
      return `Done — ${props.parseRun.reviews_created} new, ${props.parseRun.reviews_updated} updated`
    case 'failed':
      return failureLabel.value
    default:
      return ''
  }
})

const failureLabel = computed(() => {
  switch (props.parseRun.failure_reason) {
    case 'blocked':
      return 'Yandex blocked our requests. Will retry automatically with backoff.'
    case 'source_changed':
      return 'Yandex changed something on their end and the parser needs updating. See logs.'
    case 'unavailable':
      return 'Yandex was unreachable. Will retry automatically.'
    default:
      return 'Parsing failed.'
  }
})
</script>

<template>
  <div class="parse-banner" :class="`parse-banner--${parseRun.status}`">
    <span v-if="parseRun.status === 'queued' || parseRun.status === 'running'" class="spinner" />
    {{ label }}
  </div>
</template>

<style scoped>
.parse-banner {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.6rem 0.9rem;
  border-radius: 6px;
  font-size: 0.9rem;
}

.parse-banner--queued,
.parse-banner--running {
  background: #eef2ff;
  color: #3730a3;
}

.parse-banner--succeeded {
  background: #ecfdf3;
  color: var(--color-success);
}

.parse-banner--failed {
  background: #fef2f2;
  color: var(--color-danger);
}

.spinner {
  width: 0.8rem;
  height: 0.8rem;
  border: 2px solid currentColor;
  border-right-color: transparent;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
