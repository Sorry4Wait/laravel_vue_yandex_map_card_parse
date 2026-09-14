<script setup lang="ts">
import type { Organization } from '../../types'

defineProps<{
  organization: Organization
}>()
</script>

<template>
  <div class="rating-summary">
    <div class="rating-summary__score">
      <span class="rating-summary__value">{{ organization.average_rating?.toFixed(1) ?? '—' }}</span>
      <span class="rating-summary__label">avg rating</span>
    </div>

    <div class="rating-summary__stat">
      <span class="rating-summary__value">{{ organization.ratings_count ?? '—' }}</span>
      <span class="rating-summary__label">ratings</span>
    </div>

    <div class="rating-summary__stat">
      <span class="rating-summary__value">{{ organization.reviews_count ?? '—' }}</span>
      <span class="rating-summary__label">reviews</span>
    </div>
  </div>
</template>

<style scoped>
.rating-summary {
  display: flex;
  gap: 2rem;
  padding: 1rem 0;
}

.rating-summary__score,
.rating-summary__stat {
  display: flex;
  flex-direction: column;
}

.rating-summary__value {
  font-size: 1.6rem;
  font-weight: 600;
}

.rating-summary__label {
  font-size: 0.8rem;
  color: var(--color-text-muted);
}
</style>
