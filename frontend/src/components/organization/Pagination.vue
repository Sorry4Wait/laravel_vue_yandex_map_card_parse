<script setup lang="ts">
const props = defineProps<{
  currentPage: number
  lastPage: number
}>()

const emit = defineEmits<{
  change: [page: number]
}>()

function go(page: number) {
  if (page < 1 || page > props.lastPage || page === props.currentPage) return
  emit('change', page)
}
</script>

<template>
  <div v-if="lastPage > 1" class="pagination">
    <button type="button" :disabled="currentPage === 1" @click="go(currentPage - 1)">Prev</button>
    <span class="pagination__pages">Page {{ currentPage }} of {{ lastPage }}</span>
    <button type="button" :disabled="currentPage === lastPage" @click="go(currentPage + 1)">Next</button>
  </div>
</template>

<style scoped>
.pagination {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  padding: 1rem 0 0;
  margin-top: 0.5rem;
  border-top: 1px solid var(--color-border);
  font-size: 0.85rem;
}

button {
  padding: 0.4rem 0.8rem;
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  border-radius: 6px;
  cursor: pointer;
  font: inherit;
}

button:disabled {
  opacity: 0.5;
  cursor: default;
}

.pagination__pages {
  color: var(--color-text-muted);
}
</style>
