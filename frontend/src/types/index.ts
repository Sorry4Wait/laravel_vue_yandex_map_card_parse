export interface User {
  id: number
  name: string
  email: string
}

export interface Organization {
  id: number
  yandex_url: string
  name: string | null
  average_rating: number | null
  ratings_count: number | null
  reviews_count: number | null
  last_parsed_at: string | null
}

export type ParseStatus = 'queued' | 'running' | 'succeeded' | 'failed'

export interface ParseRun {
  id: number
  status: ParseStatus
  reviews_fetched: number
  reviews_created: number
  reviews_updated: number
  failure_reason: string | null
  error_message: string | null
  started_at: string | null
  finished_at: string | null
}

export interface Review {
  id: number
  author_name: string | null
  rating: number | null
  text: string | null
  published_at: string | null
}

export interface PaginatedReviews {
  data: Review[]
  meta: {
    current_page: number
    last_page: number
    total: number
    per_page: number
  }
}
