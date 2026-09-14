import { ref } from 'vue'
import { api } from '../lib/api'
import type { Organization, ParseRun } from '../types'

const organization = ref<Organization | null>(null)
const parseRun = ref<ParseRun | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

let pollTimer: ReturnType<typeof setTimeout> | null = null

function stopPolling() {
  if (pollTimer) {
    clearTimeout(pollTimer)
    pollTimer = null
  }
}

async function pollParseStatus() {
  if (!organization.value) return

  try {
    const { data } = await api.get<ParseRun>(`/api/organizations/${organization.value.id}/parse-status`)
    parseRun.value = data

    if (data.status === 'queued' || data.status === 'running') {
      pollTimer = setTimeout(pollParseStatus, 2000)
    } else {
      // parse just finished, pull the fresh aggregate numbers onto the org
      await fetchCurrent()
    }
  } catch {
    // transient poll failure isn't worth surfacing, just try again shortly
    pollTimer = setTimeout(pollParseStatus, 4000)
  }
}

async function fetchCurrent() {
  loading.value = true
  error.value = null

  try {
    const { data } = await api.get('/api/organization')
    organization.value = data.organization
    parseRun.value = data.parse_run

    if (parseRun.value && !['succeeded', 'failed'].includes(parseRun.value.status)) {
      stopPolling()
      pollParseStatus()
    }
  } catch {
    error.value = 'Could not load organization data.'
  } finally {
    loading.value = false
  }
}

async function saveLink(yandexUrl: string) {
  loading.value = true
  error.value = null

  try {
    const { data } = await api.post('/api/organization', { yandex_url: yandexUrl })
    organization.value = data.organization
    parseRun.value = data.parse_run
    stopPolling()
    pollParseStatus()
  } catch (e: any) {
    error.value = e?.response?.data?.errors?.yandex_url?.[0] ?? 'Could not save this link.'
    throw e
  } finally {
    loading.value = false
  }
}

export function useOrganization() {
  return { organization, parseRun, loading, error, fetchCurrent, saveLink, stopPolling }
}
