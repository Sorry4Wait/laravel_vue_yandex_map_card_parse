<script setup lang="ts">
import type { Review } from '../../types'

const props = defineProps<{
  review: Review
}>()

function formatDate(value: string | null) {
  if (!value) return ''
  return new Date(value).toLocaleDateString('ru-RU', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>

<template>
  <article class="review-item">
    <div class="review-item__head">
      <span class="review-item__author">{{ review.author_name ?? 'Anonymous' }}</span>
      <span class="review-item__rating" aria-label="rating">
        <span v-for="n in 5" :key="n" :class="{ filled: props.review.rating && n <= props.review.rating }">★</span>
      </span>
      <span class="review-item__date">{{ formatDate(review.published_at) }}</span>
    </div>
    <p v-if="review.text" class="review-item__text">{{ review.text }}</p>
  </article>
</template>

<style scoped>
.review-item {
  padding: 0.9rem 0;
  border-bottom: 1px solid var(--color-border);
}

.review-item__head {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin-bottom: 0.35rem;
  font-size: 0.85rem;
}

.review-item__author {
  font-weight: 600;
}

.review-item__date {
  color: var(--color-text-muted);
  margin-left: auto;
}

.review-item__rating span {
  color: var(--color-border);
}

.review-item__rating span.filled {
  color: #f5a623;
}

.review-item__text {
  margin: 0;
  font-size: 0.92rem;
  line-height: 1.4;
  white-space: pre-line;
}
</style>
