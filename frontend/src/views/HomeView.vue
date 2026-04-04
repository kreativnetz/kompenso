<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { api } from '../api'
import { clearToken, getToken, setUser } from '../lib/auth'

const router = useRouter()
const teacher = ref(null)
const loadError = ref('')
const loading = ref(false)
const tokenPresent = ref(!!getToken())

const isGuest = computed(() => !tokenPresent.value)

const canManageTeachers = computed(() => teacher.value?.abilities?.manage_teachers === true)

onMounted(async () => {
  tokenPresent.value = !!getToken()
  if (!tokenPresent.value) {
    return
  }
  loading.value = true
  const res = await api.me()
  loading.value = false
  if (!res.ok) {
    loadError.value = 'Sitzung ungültig. Bitte erneut anmelden.'
    await router.replace({ name: 'login' })
    return
  }
  const data = await res.json()
  teacher.value = data.teacher
  setUser(data.teacher)
})

function initials(t) {
  const a = (t.first_name || '').trim().charAt(0)
  const b = (t.last_name || '').trim().charAt(0)
  return (a + b).toUpperCase() || '?'
}

async function logout() {
  await api.logout().catch(() => {})
  clearToken()
  await router.replace({ name: 'login' })
}
</script>

<template>
  <div class="min-h-dvh bg-gradient-to-br from-ink-100 via-white to-emerald-50/30">
    <div
      class="pointer-events-none fixed inset-0 bg-[radial-gradient(ellipse_70%_40%_at_100%_0%,rgba(16,185,129,0.08),transparent)]"
    />

    <div class="relative mx-auto max-w-lg px-4 py-10 sm:px-6">
      <template v-if="isGuest">
        <header class="mb-8 text-center sm:text-left">
          <h1 class="text-3xl font-bold tracking-tight text-ink-900">Kompenso</h1>
          <p class="mt-2 text-sm leading-relaxed text-ink-600">
            Plattform für Abschlussarbeiten: Lernende reichen Themen ein, Lehrpersonen verwalten Zuordnungen und
            Sitzungen.
          </p>
        </header>

        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
          <RouterLink
            to="/thema/einreichen"
            class="inline-flex items-center justify-center rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-700 px-5 py-3.5 text-sm font-semibold text-white shadow-md transition hover:from-emerald-500 hover:to-teal-600 focus:outline-none focus:ring-2 focus:ring-emerald-400/50"
          >
            Thema einreichen
          </RouterLink>
          <RouterLink
            to="/login"
            class="inline-flex items-center justify-center rounded-2xl border border-ink-200 bg-white px-5 py-3.5 text-sm font-semibold text-ink-800 shadow-sm transition hover:border-ink-300 hover:bg-ink-50 focus:outline-none focus:ring-2 focus:ring-emerald-400/40"
          >
            Anmelden (Lehrpersonen)
          </RouterLink>
        </div>

        <p class="mt-8 text-center text-xs text-ink-500 sm:text-left">
          Für die Themeneingabe ist kein Login nötig. Nach dem Einreichen erhältst du einen Bearbeitungscode.
        </p>
      </template>

      <template v-else>
        <header class="mb-8 flex items-center justify-between gap-4">
          <h1 class="text-2xl font-bold tracking-tight text-ink-900">Kompenso</h1>
          <button
            type="button"
            class="rounded-xl border border-ink-200 bg-white px-4 py-2 text-sm font-medium text-ink-700 shadow-sm transition hover:border-ink-300 hover:bg-ink-50"
            @click="logout"
          >
            Abmelden
          </button>
        </header>

        <p v-if="loadError" class="rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-800 ring-1 ring-rose-200">
          {{ loadError }}
        </p>

        <p v-else-if="loading" class="text-center text-sm text-ink-500">Laden …</p>

        <template v-else-if="teacher">
          <section
            class="overflow-hidden rounded-3xl bg-white/90 p-6 shadow-card ring-1 ring-ink-200/60"
          >
            <div class="flex items-center gap-4">
              <div
                class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-700 text-xl font-bold text-white shadow-lg"
              >
                {{ initials(teacher) }}
              </div>
              <div class="min-w-0">
                <p class="text-xs font-medium uppercase tracking-wider text-ink-500">Angemeldet als</p>
                <p class="truncate text-lg font-semibold text-ink-900">{{ teacher.full_name }}</p>
                <p class="font-mono text-sm text-emerald-700">{{ teacher.role }}</p>
              </div>
            </div>
          </section>

          <div v-if="canManageTeachers" class="mt-6 grid gap-4 sm:grid-cols-2">
            <RouterLink
              to="/lehrpersonen"
              class="flex items-center justify-between gap-4 rounded-3xl bg-gradient-to-r from-ink-800 to-ink-900 p-5 text-white shadow-card transition hover:from-ink-900 hover:to-black focus:outline-none focus:ring-2 focus:ring-emerald-400/50"
            >
              <div>
                <p class="text-sm font-medium text-white/80">Verwaltung</p>
                <p class="text-lg font-semibold">Lehrpersonen</p>
                <p class="mt-1 text-sm text-white/70">Kacheln, Suche, Rollen</p>
              </div>
              <span class="text-2xl opacity-90" aria-hidden="true">→</span>
            </RouterLink>
            <RouterLink
              to="/schuljahre"
              class="flex items-center justify-between gap-4 rounded-3xl bg-gradient-to-r from-indigo-800 to-violet-900 p-5 text-white shadow-card transition hover:from-indigo-900 hover:to-violet-950 focus:outline-none focus:ring-2 focus:ring-emerald-400/50"
            >
              <div>
                <p class="text-sm font-medium text-white/80">Verwaltung</p>
                <p class="text-lg font-semibold">Schuljahre</p>
                <p class="mt-1 text-sm text-white/70">Sektionen &amp; Zeiträume</p>
              </div>
              <span class="text-2xl opacity-90" aria-hidden="true">→</span>
            </RouterLink>
            <RouterLink
              to="/zuordnungssessions"
              class="flex items-center justify-between gap-4 rounded-3xl bg-gradient-to-r from-teal-800 to-emerald-900 p-5 text-white shadow-card transition hover:from-teal-900 hover:to-emerald-950 focus:outline-none focus:ring-2 focus:ring-emerald-400/50"
            >
              <div>
                <p class="text-sm font-medium text-white/80">Verwaltung</p>
                <p class="text-lg font-semibold">Zuordnungssessions</p>
                <p class="mt-1 text-sm text-white/70">Phasen, Regeln, Entschädigung</p>
              </div>
              <span class="text-2xl opacity-90" aria-hidden="true">→</span>
            </RouterLink>
          </div>
        </template>
      </template>
    </div>
  </div>
</template>
