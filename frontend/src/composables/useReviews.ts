import { ref } from 'vue'
import { api } from '../lib/api'
import type { PaginatedReviews, Review } from '../types'

export function useReviews(organizationId: () => number | undefined) {
  const reviews = ref<Review[]>([])
  const currentPage = ref(1)
  const lastPage = ref(1)
  const total = ref(0)
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchPage(page = 1) {
    const orgId = organizationId()
    if (!orgId) return

    loading.value = true
    error.value = null

    try {
      const { data } = await api.get<PaginatedReviews>(`/api/organizations/${orgId}/reviews`, {
        params: { page },
      })
      reviews.value = data.data
      currentPage.value = data.meta.current_page
      lastPage.value = data.meta.last_page
      total.value = data.meta.total
    } catch {
      error.value = 'Could not load reviews.'
    } finally {
      loading.value = false
    }
  }

  return { reviews, currentPage, lastPage, total, loading, error, fetchPage }
}
