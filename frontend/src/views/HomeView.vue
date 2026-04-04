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

/** Öffentlicher Themeneingabe-Kontext (nur Gäste) */
const submissionContext = ref(null)
const submissionContextLoading = ref(false)
const editCodeInput = ref('')
const editCodeError = ref('')
const editCodeBusy = ref(false)

const isGuest = computed(() => !tokenPresent.value)

const canManageTeachers = computed(() => teacher.value?.abilities?.manage_teachers === true)

const boardMissingHint = computed(() => route.query.board_missing === '1')

const currentAccessibleSession = computed(() => boardSessions.value?.current_accessible_session ?? null)

const supervisedSessionsList = computed(() => boardSessions.value?.supervised_sessions ?? [])

const supervisedSessionsOthers = computed(() => {
  const curId = currentAccessibleSession.value?.id
  if (curId == null) {
    return supervisedSessionsList.value
  }
  return supervisedSessionsList.value.filter((s) => s.id !== curId)
})

/** Phasen 1–3 laut ThesisSessionPhase (vor phase_4_at), nur wenn eine Session offen ist */
const showLearnerEditCode = computed(() => {
  if (!isGuest.value) {
    return false
  }
  const ctx = submissionContext.value
  if (!ctx?.thesis_session?.id) {
    return false
  }
  const idx = ctx.phase?.phase_index
  if (typeof idx === 'number') {
    return idx >= 1 && idx <= 3
  }
  return ctx.phase?.allows_new_submission === true
})

function boardLink(sessionId) {
  return { name: 'thesis-teacher-board', query: { thesis_session_id: String(sessionId) } }
}

function bookingsLink(sessionId) {
  return { name: 'thesis-my-bookings', query: { thesis_session_id: String(sessionId) } }
}

function supervisionListLink(sessionId) {
  return { name: 'thesis-supervision-list', query: { thesis_session_id: String(sessionId) } }
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
    const sc = await api.thesisSubmissionContext()
    submissionContextLoading.value = false
    if (sc.ok) {
      submissionContext.value = await sc.json()
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
  const sRes = await api.thesisSessionsSupervised()
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

        <section
          v-if="showLearnerEditCode"
          class="mt-6 rounded-2xl border border-ink-200/80 bg-white/90 p-4 shadow-sm ring-1 ring-ink-100 sm:p-5"
        >
          <h2 class="text-sm font-semibold text-ink-900">Thema bearbeiten</h2>
          <p class="mt-1 text-xs text-ink-600">
            Mit dem Bearbeitungscode kannst du deine Einreichung anpassen (solange die Ausschreibung läuft).
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
          <p
            v-if="boardMissingHint"
            class="mb-4 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200"
          >
            Bitte öffne die Themensliste über eine Session unten (aktuell oder vergangene Zuordnung).
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
            <section
              v-if="currentAccessibleSession"
              class="mt-6 overflow-hidden rounded-3xl bg-gradient-to-r from-emerald-700 to-teal-800 p-5 text-white shadow-card ring-1 ring-emerald-900/20"
            >
              <p class="text-xs font-semibold uppercase tracking-wider text-white/80">Aktuelle Session</p>
              <p class="mt-1 text-lg font-semibold">{{ currentAccessibleSession.name }}</p>
              <p v-if="currentAccessibleSession.schoolyear_label" class="mt-1 text-sm text-white/85">
                Schuljahr {{ currentAccessibleSession.schoolyear_label }}
              </p>
              <div class="mt-4 flex flex-wrap gap-2">
                <RouterLink
                  :to="boardLink(currentAccessibleSession.id)"
                  class="inline-flex items-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-emerald-900 shadow-sm transition hover:bg-emerald-50"
                >
                  Zur Themensliste
                </RouterLink>
                <RouterLink
                  :to="bookingsLink(currentAccessibleSession.id)"
                  class="inline-flex items-center rounded-xl border border-white/40 bg-white/10 px-4 py-2 text-sm font-semibold text-white shadow-sm backdrop-blur-sm transition hover:bg-white/20"
                >
                  Meine Buchungen
                </RouterLink>
                <RouterLink
                  :to="supervisionListLink(currentAccessibleSession.id)"
                  class="inline-flex items-center rounded-xl border border-white/40 bg-white/10 px-4 py-2 text-sm font-semibold text-white shadow-sm backdrop-blur-sm transition hover:bg-white/20"
                >
                  Betreuungsliste
                </RouterLink>
              </div>
            </section>

            <section
              v-if="supervisedSessionsOthers.length"
              class="mt-6 overflow-hidden rounded-3xl bg-white/90 p-6 shadow-card ring-1 ring-ink-200/60"
            >
              <h2 class="text-lg font-semibold text-ink-900">Deine Zuordnungssessions</h2>
              <p class="mt-1 text-sm text-ink-600">
                Sessions mit bestätigter Betreuung. Nach Abschluss siehst du nur noch deine Arbeiten.
              </p>
              <ul class="mt-4 space-y-3">
                <li
                  v-for="s in supervisedSessionsOthers"
                  :key="s.id"
                  class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-ink-100 bg-ink-50/50 px-4 py-3"
                >
                  <div>
                    <p class="font-medium text-ink-900">{{ s.name }}</p>
                    <p class="text-sm text-ink-600">
                      <span v-if="s.schoolyear_label">Schuljahr {{ s.schoolyear_label }}</span>
                      <span v-if="s.is_past" class="ml-2 rounded-full bg-ink-200 px-2 py-0.5 text-xs text-ink-800">
                        Abgeschlossen
                      </span>
                    </p>
                  </div>
                  <div class="flex flex-wrap gap-2">
                    <RouterLink
                      :to="boardLink(s.id)"
                      class="inline-flex rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500"
                    >
                      Themensliste
                    </RouterLink>
                    <RouterLink
                      :to="bookingsLink(s.id)"
                      class="inline-flex rounded-xl border border-emerald-300 bg-white px-4 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-50"
                    >
                      Meine Buchungen
                    </RouterLink>
                  </div>
                </li>
              </ul>
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
