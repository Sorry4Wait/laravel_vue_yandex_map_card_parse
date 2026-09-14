<script setup lang="ts">
import type { Review } from '../../types'
import ReviewItem from './ReviewItem.vue'

defineProps<{
  reviews: Review[]
  loading: boolean
  error: string | null
}>()
</script>

<template>
  <div class="review-list">
    <p v-if="loading" class="state-text">Loading reviews…</p>
    <p v-else-if="error" class="state-text state-text--error">{{ error }}</p>
    <p v-else-if="reviews.length === 0" class="state-text">No reviews yet.</p>

    <template v-else>
      <ReviewItem v-for="review in reviews" :key="review.id" :review="review" />
    </template>
  </div>
</template>

<style scoped>
.review-list {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

.state-text {
  color: var(--color-text-muted);
  font-size: 0.9rem;
  padding: 1rem 0;
}

.state-text--error {
  color: var(--color-danger);
}
</style>
