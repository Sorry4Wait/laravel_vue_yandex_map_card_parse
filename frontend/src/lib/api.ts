import axios from 'axios'

const baseURL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000'

export const api = axios.create({
  baseURL,
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
})

// sanctum's spa auth needs this hit once before login so it can set the
// XSRF-TOKEN cookie - without it the first login attempt 419s
export function primeCsrfCookie() {
  return api.get('/sanctum/csrf-cookie')
}
