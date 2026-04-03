const KEY = 'kompenso_token'
const USER_KEY = 'kompenso_user'

export function getToken() {
  return localStorage.getItem(KEY)
}

export function setToken(token) {
  localStorage.setItem(KEY, token)
}

export function clearUser() {
  sessionStorage.removeItem(USER_KEY)
}

export function clearToken() {
  localStorage.removeItem(KEY)
  clearUser()
}

export function setUser(user) {
  if (user) {
    sessionStorage.setItem(USER_KEY, JSON.stringify(user))
  } else {
    sessionStorage.removeItem(USER_KEY)
  }
}

export function getUser() {
  try {
    const raw = sessionStorage.getItem(USER_KEY)
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}
