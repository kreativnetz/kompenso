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
  schoolyears() {
    return request('/schoolyears')
  },
  createSchoolyear(body) {
    return request('/schoolyears', { method: 'POST', body })
  },
  updateSchoolyear(id, body) {
    return request(`/schoolyears/${id}`, { method: 'PATCH', body })
  },
  deleteSchoolyear(id) {
    return request(`/schoolyears/${id}`, { method: 'DELETE' })
  },
  thesisSessions() {
    return request('/thesis-sessions')
  },
  createThesisSession(body) {
    return request('/thesis-sessions', { method: 'POST', body })
  },
  updateThesisSession(id, body) {
    return request(`/thesis-sessions/${id}`, { method: 'PATCH', body })
  },
  deleteThesisSession(id) {
    return request(`/thesis-sessions/${id}`, { method: 'DELETE' })
  },
  thesisSessionExcelExport(sessionId) {
    return request(`/thesis-sessions/${sessionId}/excel-export`, {
      headers: { Accept: 'text/plain,*/*' },
    })
  },
  thesisSessionClose(sessionId) {
    return request(`/thesis-sessions/${sessionId}/close`, { method: 'POST' })
  },
  thesisSubmissionContext(params = {}) {
    const q = new URLSearchParams()
    if (params.thesis_session_id != null && params.thesis_session_id !== '') {
      q.set('thesis_session_id', String(params.thesis_session_id))
    }
    const qs = q.toString()
    return request(`/public/thesis-submission/context${qs ? `?${qs}` : ''}`)
  },
  submitThesis(body) {
    return request('/public/thesis-submission', { method: 'POST', body })
  },
  thesisForEdit(params) {
    const q = new URLSearchParams()
    q.set('edit_code', String(params.edit_code ?? '').trim())
    q.set('thesis_session_id', String(params.thesis_session_id))
    return request(`/public/thesis-submission/for-edit?${q.toString()}`)
  },
  updateThesisByCode(body) {
    return request('/public/thesis-submission/by-code', { method: 'PATCH', body })
  },
  thesisSessionsForTeacher() {
    return request('/me/thesis-sessions')
  },
  publicThesisSessionsForHome() {
    return request('/public/thesis-sessions')
  },
  thesisSessionTeacherBoard(sessionId) {
    return request(`/thesis-sessions/${sessionId}/teacher-board`)
  },
  thesisSessionSupervisionList(sessionId) {
    return request(`/thesis-sessions/${sessionId}/supervision-list`)
  },
  thesisSessionTeacherOverview(sessionId) {
    return request(`/thesis-sessions/${sessionId}/teacher-supervision-overview`)
  },
  thesisSessionMyBookings(sessionId) {
    return request(`/thesis-sessions/${sessionId}/my-bookings`)
  },
  setThesisWorkflowStatus(sessionId, thesisId, body) {
    return request(`/thesis-sessions/${sessionId}/theses/${thesisId}/workflow-status`, {
      method: 'POST',
      body,
    })
  },
  bookSupervision(sessionId, body) {
    return request(`/thesis-sessions/${sessionId}/supervisions`, { method: 'POST', body })
  },
  withdrawSupervision(sessionId, supervisionId) {
    return request(`/thesis-sessions/${sessionId}/supervisions/${supervisionId}/withdraw`, {
      method: 'POST',
    })
  },
  assignSupervision(sessionId, body) {
    return request(`/thesis-sessions/${sessionId}/supervisions/assign`, { method: 'POST', body })
  },
}
