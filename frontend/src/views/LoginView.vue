<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { api } from '../api'
import { setToken, setUser } from '../lib/auth'
import MentorMatchLogo from '../components/MentorMatchLogo.vue'

const router = useRouter()
const route = useRoute()

const token = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function submit() {
  error.value = ''
  loading.value = true
  try {
    const res = await api.login({ token: token.value.trim(), password: password.value })
    const data = await res.json().catch(() => ({}))
    if (!res.ok) {
      const msg = data.message || data.errors?.token?.[0] || 'Anmeldung fehlgeschlagen.'
      error.value = typeof msg === 'string' ? msg : 'Anmeldung fehlgeschlagen.'
      return
    }
    setToken(data.token)
    if (data.teacher) {
      setUser(data.teacher)
    }
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/'
    await router.replace(redirect || '/')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div
    class="flex min-h-dvh items-center justify-center bg-gradient-to-br from-ink-100 via-white to-emerald-50/40 px-4 py-10"
  >
    <div
      class="w-full max-w-md rounded-3xl bg-white/95 p-8 shadow-card ring-1 ring-ink-200/70 backdrop-blur-sm"
    >
      <div class="mb-6 flex justify-center">
        <MentorMatchLogo variant="hero" />
      </div>
      <h1 class="text-center text-2xl font-bold tracking-tight text-ink-900">Anmelden</h1>
      <p class="mt-2 text-center text-sm text-ink-500">
        Kürzel und Passwort
        <span class="text-ink-400">·</span>
        Demo:
        <code class="rounded-md bg-ink-100 px-1.5 py-0.5 font-mono text-xs text-ink-800">password</code>
      </p>

      <form class="mt-8 flex flex-col gap-5" @submit.prevent="submit">
        <div>
          <label for="login-token" class="mb-1.5 block text-sm font-medium text-ink-700">Kürzel</label>
          <input
            id="login-token"
            v-model="token"
            type="text"
            autocomplete="username"
            required
            class="w-full rounded-xl border-0 bg-ink-50 px-4 py-3 text-ink-900 shadow-inner ring-1 ring-ink-200/80 transition placeholder:text-ink-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
          />
        </div>
        <div>
          <label for="login-pass" class="mb-1.5 block text-sm font-medium text-ink-700">Passwort</label>
          <input
            id="login-pass"
            v-model="password"
            type="password"
            autocomplete="current-password"
            required
            class="w-full rounded-xl border-0 bg-ink-50 px-4 py-3 text-ink-900 shadow-inner ring-1 ring-ink-200/80 transition focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
          />
        </div>
        <p v-if="error" class="text-sm font-medium text-rose-600">{{ error }}</p>
        <button
          type="submit"
          :disabled="loading"
          class="rounded-xl bg-gradient-to-r from-emerald-600 to-teal-700 py-3.5 text-sm font-semibold text-white shadow-md transition hover:from-emerald-500 hover:to-teal-600 disabled:cursor-not-allowed disabled:opacity-50"
        >
          {{ loading ? 'Anmeldung …' : 'Anmelden' }}
        </button>
      </form>

      <details class="mt-8 rounded-2xl bg-ink-50/80 px-4 py-3 text-sm text-ink-600 ring-1 ring-ink-200/60">
        <summary class="cursor-pointer font-medium text-ink-800">Demo-Konten</summary>
        <ul class="mt-3 list-inside list-disc space-y-1 text-ink-600">
          <li><code class="font-mono text-xs text-ink-800">deaktiv</code> — Rolle 0 (kein Login)</li>
          <li><code class="font-mono text-xs text-ink-800">lehrer</code> — Rolle 1</li>
          <li><code class="font-mono text-xs text-ink-800">sonder</code> — Rolle 2</li>
          <li><code class="font-mono text-xs text-ink-800">schule</code> — Rolle 3</li>
          <li><code class="font-mono text-xs text-ink-800">gott</code> — Rolle 4</li>
        </ul>
      </details>
    </div>
  </div>
</template>
