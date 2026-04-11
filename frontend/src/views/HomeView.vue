<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { api } from '../api'
import { clearToken, getToken, setUser } from '../lib/auth'

const route = useRoute()
const router = useRouter()
const teacher = ref(null)
const loadError = ref('')
const loading = ref(false)
const tokenPresent = ref(!!getToken())
const boardSessionsLoading = ref(false)
const boardSessionsError = ref('')
const boardSessions = ref(null)

/** Öffentliche Sessions mit offener Themeneingabe (Gäste) */
const publicSessionsForSubmit = ref([])

/** Öffentlicher Themeneingabe-Kontext (nur Gäste) */
const submissionContext = ref(null)
const submissionContextLoading = ref(false)
const editCodeInput = ref('')
const editCodeError = ref('')
const editCodeBusy = ref(false)

const isGuest = computed(() => !tokenPresent.value)

const canManageTeachers = computed(() => teacher.value?.abilities?.manage_teachers === true)

const boardMissingHint = computed(() => route.query.board_missing === '1')

const teacherSessions = computed(() => boardSessions.value?.thesis_sessions ?? [])

/** Bearbeitungscode nur im ersten Lernenden-Fenster (API: allows_edit_by_code). */
const showLearnerEditCode = computed(() => {
  if (!isGuest.value) {
    return false
  }
  const ctx = submissionContext.value
  if (!ctx?.thesis_session?.id) {
    return false
  }
  return ctx.phase?.allows_edit_by_code === true
})

function thesisSubmitLink(sessionId) {
  return { name: 'thesis-submit', query: { thesis_session_id: String(sessionId) } }
}

function boardLink(sessionId) {
  return { name: 'thesis-teacher-board', query: { thesis_session_id: String(sessionId) } }
}

function bookingsLink(sessionId) {
  return { name: 'thesis-my-bookings', query: { thesis_session_id: String(sessionId) } }
}

function supervisionListLink(sessionId) {
  return { name: 'thesis-supervision-list', query: { thesis_session_id: String(sessionId) } }
}

function teachersOverviewLink(sessionId) {
  return { name: 'thesis-teachers-overview', query: { thesis_session_id: String(sessionId) } }
}

async function goToEditWithCode() {
  editCodeError.value = ''
  const code = editCodeInput.value.trim()
  const sid = submissionContext.value?.thesis_session?.id
  if (!code) {
    editCodeError.value = 'Bitte den Bearbeitungscode eingeben.'
    return
  }
  if (sid == null) {
    editCodeError.value = 'Aktuell ist keine Themeneingabe aktiv.'
    return
  }
  editCodeBusy.value = true
  const res = await api.thesisForEdit({ edit_code: code, thesis_session_id: sid })
  editCodeBusy.value = false
  if (!res.ok) {
    const data = await res.json().catch(() => ({}))
    editCodeError.value =
      typeof data.message === 'string'
        ? data.message
        : 'Dieser Bearbeitungscode passt nicht zur aktuellen Ausschreibung oder ist unbekannt.'
    return
  }
  await router.push({
    name: 'thesis-submit',
    query: { code, thesis_session_id: String(sid) },
  })
}

onMounted(async () => {
  tokenPresent.value = !!getToken()
  if (!tokenPresent.value) {
    submissionContextLoading.value = true
    const [sc, pub] = await Promise.all([api.thesisSubmissionContext(), api.publicThesisSessionsForHome()])
    submissionContextLoading.value = false
    if (sc.ok) {
      submissionContext.value = await sc.json()
    }
    if (pub.ok) {
      const data = await pub.json()
      publicSessionsForSubmit.value = data.thesis_sessions ?? []
    }
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

  boardSessionsLoading.value = true
  boardSessionsError.value = ''
  const sRes = await api.thesisSessionsForTeacher()
  boardSessionsLoading.value = false
  if (sRes.ok) {
    boardSessions.value = await sRes.json()
  } else {
    boardSessionsError.value = 'Zuordnungssessions konnten nicht geladen werden.'
  }
})

function initials(t) {
  const a = (t.first_name || '').trim().charAt(0)
  const b = (t.last_name || '').trim().charAt(0)
  return (a + b).toUpperCase() || '?'
}

async function logout() {
  await api.logout().catch(() => {})
  clearToken()
  await router.replace({ name: 'home' })
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
            IDPA Manager: Lernende reichen Themen ein, Lehrpersonen verwalten Zuordnungen und
            Sitzungen.
          </p>
        </header>

        <div v-if="submissionContextLoading" class="text-sm text-ink-500">Laden …</div>
        <template v-else>
          <div v-if="publicSessionsForSubmit.length" class="flex flex-col gap-3">
            <RouterLink
              v-for="ps in publicSessionsForSubmit"
              :key="ps.id"
              :to="thesisSubmitLink(ps.id)"
              class="inline-flex items-center justify-center rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-700 px-5 py-3.5 text-sm font-semibold text-white shadow-md transition hover:from-emerald-500 hover:to-teal-600 focus:outline-none focus:ring-2 focus:ring-emerald-400/50"
            >
              Thema einreichen
              <span v-if="ps.name" class="ml-2 truncate opacity-90">({{ ps.name }})</span>
            </RouterLink>
          </div>
          <RouterLink
            v-else
            to="/thema/einreichen"
            class="inline-flex items-center justify-center rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-700 px-5 py-3.5 text-sm font-semibold text-white shadow-md transition hover:from-emerald-500 hover:to-teal-600 focus:outline-none focus:ring-2 focus:ring-emerald-400/50"
          >
            Thema einreichen
          </RouterLink>

          <div class="mt-3">
            <RouterLink
              to="/login"
              class="inline-flex items-center justify-center rounded-2xl border border-ink-200 bg-white px-5 py-3.5 text-sm font-semibold text-ink-800 shadow-sm transition hover:border-ink-300 hover:bg-ink-50 focus:outline-none focus:ring-2 focus:ring-emerald-400/40"
            >
              Anmelden (Lehrpersonen)
            </RouterLink>
          </div>
        </template>

        <section
          v-if="showLearnerEditCode"
          class="mt-6 rounded-2xl border border-ink-200/80 bg-white/90 p-4 shadow-sm ring-1 ring-ink-100 sm:p-5"
        >
          <h2 class="text-sm font-semibold text-ink-900">Thema bearbeiten</h2>
          <p class="mt-1 text-xs text-ink-600">
            Mit dem Bearbeitungscode können Sie Ihre Einreichung in der ersten Phase der Ausschreibung anpassen.
          </p>
          <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1">
              <label for="home-edit-code" class="mb-1 block text-xs font-medium text-ink-600">
                Bearbeitungscode
              </label>
              <input
                id="home-edit-code"
                v-model="editCodeInput"
                type="text"
                autocomplete="off"
                autocapitalize="off"
                spellcheck="false"
                class="w-full rounded-xl border-0 bg-ink-50 px-3 py-2.5 font-mono text-sm text-ink-900 ring-1 ring-ink-200 focus:outline-none focus:ring-2 focus:ring-emerald-500/35"
                @keyup.enter="goToEditWithCode"
              />
            </div>
            <button
              type="button"
              class="inline-flex shrink-0 items-center justify-center rounded-xl bg-ink-800 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-ink-900 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="editCodeBusy || submissionContextLoading"
              @click="goToEditWithCode"
            >
              {{ editCodeBusy ? 'Prüfen …' : 'Weiter' }}
            </button>
          </div>
          <p v-if="editCodeError" class="mt-2 text-sm text-rose-600">{{ editCodeError }}</p>
        </section>

        <p class="mt-8 text-center text-xs text-ink-500 sm:text-left">
          Für die Themeneingabe ist kein Login nötig. Nach dem Einreichen erhalten Sie einen Bearbeitungscode.
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
          <p
            v-if="boardMissingHint"
            class="mb-4 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200"
          >
            Bitte öffne die Themenliste über eine Session unten.
            <button
              type="button"
              class="ml-2 font-semibold text-amber-950 underline"
              @click="router.replace({ query: {} })"
            >
              Hinweis schliessen
            </button>
          </p>

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
                <p class="truncate text-lg font-semibold text-ink-900">{{ teacher.full_name }} ({{ teacher.token }})</p>
                <p class="font-mono text-sm text-emerald-700">{{ teacher.role }}</p>
              </div>
            </div>
          </section>

          <section
            v-if="boardSessionsLoading"
            class="mt-6 rounded-3xl bg-white/80 px-5 py-4 text-sm text-ink-600 shadow-card ring-1 ring-ink-200/60"
          >
            Sessions werden geladen …
          </section>

          <p
            v-else-if="boardSessionsError"
            class="mt-6 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-800 ring-1 ring-rose-200"
          >
            {{ boardSessionsError }}
          </p>

          <template v-else-if="boardSessions">
            <section class="mt-6 space-y-4">
              <h2 class="text-sm font-semibold text-ink-900">Zuordnungssessions</h2>
              <template v-if="!teacherSessions.length">
                <p class="text-sm text-ink-600">
                  Keine Session sichtbar (für Lehrpersonen ab Beginn der LP-Einsicht).
                </p>
              </template>
              <article
                v-for="s in teacherSessions"
                v-else
                :key="s.id"
                class="overflow-hidden rounded-3xl p-5 text-ink-900 shadow-card ring-1"
                :class="
                  s.is_highlight
                    ? 'bg-gradient-to-r from-emerald-700 to-teal-800 text-white ring-emerald-900/20'
                    : 'bg-white/90 ring-ink-200/60'
                "
              >
                <p
                  class="text-xs font-semibold uppercase tracking-wider"
                  :class="s.is_highlight ? 'text-white/80' : 'text-ink-500'"
                >
                  Session
                </p>
                <p class="mt-1 text-lg font-semibold" :class="s.is_highlight ? 'text-white' : 'text-ink-900'">
                  {{ s.name }}
                </p>
                <p
                  v-if="s.schoolyear_label"
                  class="mt-1 text-sm"
                  :class="s.is_highlight ? 'text-white/85' : 'text-ink-600'"
                >
                  Schuljahr {{ s.schoolyear_label }}
                </p>
                <p v-if="s.is_closed" class="mt-2">
                  <span
                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="
                      s.is_highlight
                        ? 'bg-white/20 text-white'
                        : 'bg-ink-200 text-ink-800'
                    "
                  >
                    Archiviert
                  </span>
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                  <RouterLink
                    :to="boardLink(s.id)"
                    class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold shadow-sm transition"
                    :class="
                      s.is_highlight
                        ? 'bg-white text-emerald-900 hover:bg-emerald-50'
                        : 'border border-ink-200 bg-white text-emerald-800 hover:bg-emerald-50'
                    "
                  >
                    Zur Themensliste
                  </RouterLink>
                  <RouterLink
                    :to="bookingsLink(s.id)"
                    class="inline-flex items-center rounded-xl border px-4 py-2 text-sm font-semibold shadow-sm backdrop-blur-sm transition"
                    :class="
                      s.is_highlight
                        ? 'border-white/40 bg-white/10 text-white hover:bg-white/20'
                        : 'border-emerald-300 bg-white text-emerald-800 hover:bg-emerald-50'
                    "
                  >
                    Meine Buchungen
                  </RouterLink>
                  <RouterLink
                    v-if="canManageTeachers"
                    :to="supervisionListLink(s.id)"
                    class="inline-flex items-center rounded-xl border px-4 py-2 text-sm font-semibold shadow-sm backdrop-blur-sm transition"
                    :class="
                      s.is_highlight
                        ? 'border-white/40 bg-white/10 text-white hover:bg-white/20'
                        : 'border-ink-200 bg-ink-50 text-ink-800 hover:bg-ink-100'
                    "
                  >
                    Betreuungsliste
                  </RouterLink>
                  <RouterLink
                    v-if="canManageTeachers"
                    :to="teachersOverviewLink(s.id)"
                    class="inline-flex items-center rounded-xl border px-4 py-2 text-sm font-semibold shadow-sm backdrop-blur-sm transition"
                    :class="
                      s.is_highlight
                        ? 'border-white/40 bg-white/10 text-white hover:bg-white/20'
                        : 'border-ink-200 bg-ink-50 text-ink-800 hover:bg-ink-100'
                    "
                  >
                    Lehrpersonen
                  </RouterLink>
                </div>
              </article>
            </section>
          </template>

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
                <p class="mt-1 text-sm text-white/70">Abteilung &amp; Zeiträume</p>
              </div>
              <span class="text-2xl opacity-90" aria-hidden="true">→</span>
            </RouterLink>
            <RouterLink
              to="/zuordnungssessions"
              class="flex items-center justify-between gap-4 rounded-3xl bg-gradient-to-r from-teal-800 to-emerald-900 p-5 text-white shadow-card transition hover:from-teal-900 hover:to-emerald-950 focus:outline-none focus:ring-2 focus:ring-emerald-400/50"
            >
              <div>
                <p class="text-sm font-medium text-white/80">Verwaltung</p>
                <p class="text-lg font-semibold">Sessions</p>
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
