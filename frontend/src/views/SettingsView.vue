<script setup lang="ts">
import { computed, onMounted, onUnmounted, watch } from 'vue'
import { useOrganization } from '../composables/useOrganization'
import { useReviews } from '../composables/useReviews'
import LinkForm from '../components/organization/LinkForm.vue'
import ParseStatusBanner from '../components/organization/ParseStatusBanner.vue'
import RatingSummary from '../components/organization/RatingSummary.vue'
import ReviewList from '../components/organization/ReviewList.vue'
import Pagination from '../components/organization/Pagination.vue'

const { organization, parseRun, loading: orgLoading, error: orgError, fetchCurrent, saveLink, stopPolling } = useOrganization()

const {
  reviews,
  currentPage,
  lastPage,
  loading: reviewsLoading,
  error: reviewsError,
  fetchPage,
} = useReviews(() => organization.value?.id)

onMounted(async () => {
  await fetchCurrent()
  if (organization.value) {
    fetchPage(1)
  }
})

onUnmounted(() => stopPolling())

// once a parse finishes, the reviews table has fresh data worth showing
watch(
  () => parseRun.value?.status,
  (status, previous) => {
    if (status === 'succeeded' && previous !== 'succeeded') {
      fetchPage(1)
    }
  },
)

const savingLink = computed(() => orgLoading.value)

async function handleSave(url: string) {
  try {
    await saveLink(url)
  } catch {
    // error already captured in orgError, form shows it
  }
}
</script>

<template>
  <div class="settings">
    <section class="settings__card">
      <h1>Organization link</h1>
      <LinkForm
        :initial-url="organization?.yandex_url"
        :submitting="savingLink"
        :error="orgError"
        @save="handleSave"
      />
    </section>

    <ParseStatusBanner v-if="parseRun" :parse-run="parseRun" />

    <section v-if="organization" class="settings__card">
      <h2>{{ organization.name ?? organization.yandex_url }}</h2>
      <RatingSummary :organization="organization" />
    </section>

    <section v-if="organization" class="settings__card settings__card--reviews">
      <h2>Reviews</h2>
      <ReviewList :reviews="reviews" :loading="reviewsLoading" :error="reviewsError" />
      <Pagination :current-page="currentPage" :last-page="lastPage" @change="fetchPage" />
    </section>
  </div>
</template>

<style scoped>
.settings {
  width: 100%;
  max-width: 640px;
  height: 100%;
  min-height: 0;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.settings__card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  padding: 1.5rem;
}

/* takes whatever vertical space is left after the other cards, so the
   review list (not the whole page) is what actually scrolls */
.settings__card--reviews {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
}

h1 {
  font-size: 1.15rem;
  margin: 0 0 1rem;
}

h2 {
  font-size: 1.05rem;
  margin: 0 0 1rem;
  flex-shrink: 0;
}
</style>
