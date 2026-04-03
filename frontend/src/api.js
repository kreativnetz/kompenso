import { clearToken, getToken } from './lib/auth'

async function request(path, options = {}) {
  const headers = {
    Accept: 'application/json',
    ...options.headers,
  }
  const token = getToken()
  if (token) {
    headers.Authorization = `Bearer ${token}`
  }
  if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
    headers['Content-Type'] = 'application/json'
    options.body = JSON.stringify(options.body)
  }
  const res = await fetch(`/api${path}`, { ...options, headers })
  if (res.status === 401) {
    clearToken()
  }
  return res
}

export const api = {
  login(body) {
    return request('/login', { method: 'POST', body })
  },
  me() {
    return request('/me')
  },
  logout() {
    return request('/logout', { method: 'POST' })
  },
  teachers() {
    return request('/teachers')
  },
  updateTeacher(id, body) {
    return request(`/teachers/${id}`, { method: 'PATCH', body })
  },
}
