<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import { getUser, setUser } from '../lib/auth'

const router = useRouter()

const PHASE_META = [
  {
    field: 'phase_1_at',
    num: 1,
    title: 'Phase 1',
    hint: 'Lernende können beginnen, sich einzutragen.',
    accent: 'from-emerald-500/20 to-teal-500/10 ring-emerald-200/80',
  },
  {
    field: 'phase_2_at',
    num: 2,
    title: 'Phase 2',
    hint: 'Lehrpersonen erhalten Einsicht.',
    accent: 'from-sky-500/15 to-blue-500/10 ring-sky-200/80',
  },
  {
    field: 'phase_3_at',
    num: 3,
    title: 'Phase 3',
    hint: 'Lehrpersonen können sich eintragen.',
    accent: 'from-violet-500/15 to-purple-500/10 ring-violet-200/80',
  },
  {
    field: 'phase_4_at',
    num: 4,
    title: 'Phase 4',
    hint: 'Lernende können sich nicht mehr eintragen · Lehrpersonen nur noch austragen.',
    accent: 'from-amber-500/20 to-orange-500/10 ring-amber-200/80',
  },
  {
    field: 'phase_5_at',
    num: 5,
    title: 'Phase 5',
    hint: 'Nur noch Administrator und Gott können Änderungen machen.',
    accent: 'from-rose-500/15 to-rose-600/10 ring-rose-200/80',
  },
]

const currentUser = ref(getUser())
const sessions = ref([])
const loading = ref(true)
const loadError = ref('')
const modalOpen = ref(false)
const saving = ref(false)
const editingId = ref(null)
const toast = ref({ type: '', text: '' })
const deleteTarget = ref(null)
const deleting = ref(false)

const form = ref(emptyForm())

function emptyForm() {
  const d = new Date()
  const step = (hours) => {
    const x = new Date(d)
    x.setHours(x.getHours() + hours, 0, 0, 0)
    return toDatetimeLocalValue(x)
  }
  return {
    name: '',
    phase_1_at: step(0),
    phase_2_at: step(24),
    phase_3_at: step(48),
    phase_4_at: step(72),
    phase_5_at: step(96),
  }
}

function toDatetimeLocalValue(date) {
  const pad = (n) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

function parseLocalInput(isoLike) {
  if (!isoLike) {
    return null
  }
  const [d, t] = isoLike.split('T')
  if (!d || !t) {
    return null
  }
  const [y, m, day] = d.split('-').map(Number)
  const [hh, mm] = t.split(':').map(Number)
  return new Date(y, m - 1, day, hh, mm, 0, 0)
}

const fmt = new Intl.DateTimeFormat('de-CH', {
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
})

function formatPhaseDisplay(value) {
  const d = parseLocalInput(value)
  return d ? fmt.format(d) : '—'
}

let toastTimer
function showToast(type, text) {
  clearTimeout(toastTimer)
  toast.value = { type, text }
  toastTimer = setTimeout(() => {
    toast.value = { type: '', text: '' }
  }, 4000)
}

async function refreshMe() {
  const res = await api.me()
  if (res.ok) {
    const data = await res.json()
    currentUser.value = data.teacher
    setUser(data.teacher)
  }
}

async function loadSessions() {
  loading.value = true
  loadError.value = ''
  const res = await api.thesisSessions()
  if (!res.ok) {
    if (res.status === 403) {
      await router.replace({ name: 'home' })
      return
    }
    loadError.value = 'Sessions konnten nicht geladen werden.'
    loading.value = false
    return
  }
  const data = await res.json()
  sessions.value = data.thesis_sessions
  loading.value = false
}

function openCreate() {
  editingId.value = null
  form.value = emptyForm()
  modalOpen.value = true
}

function openEdit(row) {
  editingId.value = row.id
  form.value = {
    name: row.name,
    phase_1_at: row.phase_1_at || '',
    phase_2_at: row.phase_2_at || '',
    phase_3_at: row.phase_3_at || '',
    phase_4_at: row.phase_4_at || '',
    phase_5_at: row.phase_5_at || '',
  }
  modalOpen.value = true
}

function closeModal() {
  if (saving.value) {
    return
  }
  modalOpen.value = false
}

const modalTitle = computed(() => (editingId.value ? 'Session bearbeiten' : 'Neue Session'))

async function submitForm() {
  saving.value = true
  const body = { ...form.value }
  const res = editingId.value
    ? await api.updateThesisSession(editingId.value, body)
    : await api.createThesisSession(body)
  saving.value = false

  if (!res.ok) {
    const err = await res.json().catch(() => ({}))
    const msg =
      err.message ||
      (err.errors && Object.values(err.errors).flat().join(' ')) ||
      'Speichern fehlgeschlagen.'
    showToast('error', msg)
    return
  }

  const data = await res.json()
  const row = data.thesis_session
  if (editingId.value) {
    const i = sessions.value.findIndex((s) => s.id === row.id)
    if (i !== -1) {
      sessions.value[i] = row
    }
  } else {
    sessions.value.push(row)
    sessions.value.sort((a, b) => a.name.localeCompare(b.name, 'de'))
  }
  modalOpen.value = false
  showToast('success', editingId.value ? 'Gespeichert.' : 'Session angelegt.')
}

function confirmDelete(row) {
  deleteTarget.value = row
}

function cancelDelete() {
  deleteTarget.value = null
}

async function doDelete() {
  if (!deleteTarget.value) {
    return
  }
  deleting.value = true
  const res = await api.deleteThesisSession(deleteTarget.value.id)
  deleting.value = false
  if (!res.ok) {
    showToast('error', 'Löschen fehlgeschlagen.')
    return
  }
  sessions.value = sessions.value.filter((s) => s.id !== deleteTarget.value.id)
  showToast('success', 'Session gelöscht.')
  deleteTarget.value = null
}

function initials(u) {
  const a = (u?.first_name || '').trim().charAt(0)
  const b = (u?.last_name || '').trim().charAt(0)
  return (a + b).toUpperCase() || '?'
}

onMounted(async () => {
  await refreshMe()
  await loadSessions()
})
</script>

<template>
  <div class="min-h-dvh bg-gradient-to-br from-ink-100 via-white to-teal-50/35">
    <div
      class="pointer-events-none fixed inset-0 bg-[radial-gradient(ellipse_75%_45%_at_20%_0%,rgba(20,184,166,0.10),transparent)]"
    />

    <header class="relative border-b border-ink-200/60 bg-white/75 backdrop-blur-md">
      <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6">
        <div class="flex items-center gap-4">
          <RouterLink
            to="/"
            class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-ink-600 transition hover:bg-ink-100 hover:text-ink-900"
          >
            <span aria-hidden="true" class="text-lg leading-none">←</span>
            Start
          </RouterLink>
          <div class="h-8 w-px bg-ink-200" />
          <div>
            <h1 class="text-lg font-semibold tracking-tight text-ink-900 sm:text-xl">Zuordnungssessions</h1>
            <p class="text-sm text-ink-500">Phasen mit Datum &amp; Uhrzeit für IDPA/SA &amp; Co.</p>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <button
            type="button"
            class="rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-700 px-4 py-2.5 text-sm font-semibold text-white shadow-md transition hover:from-emerald-500 hover:to-teal-600"
            @click="openCreate"
          >
            + Neue Session
          </button>
          <div
            v-if="currentUser"
            class="flex items-center gap-3 rounded-2xl bg-white/90 px-3 py-2 shadow-soft ring-1 ring-ink-200/50"
          >
            <div class="text-right text-sm leading-tight">
              <p class="font-medium text-ink-900">{{ currentUser.full_name }}</p>
              <p class="text-ink-500">{{ currentUser.role }}</p>
            </div>
            <div
              class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-teal-400 to-emerald-700 text-sm font-bold text-white shadow-md"
            >
              {{ initials(currentUser) }}
            </div>
          </div>
        </div>
      </div>
    </header>

    <main class="relative mx-auto max-w-5xl px-4 py-8 sm:px-6">
      <div
        v-if="toast.text"
        role="status"
        class="mb-6 flex items-center gap-3 rounded-2xl px-4 py-3 shadow-card ring-1"
        :class="
          toast.type === 'error'
            ? 'bg-rose-50 text-rose-900 ring-rose-200'
            : 'bg-emerald-50 text-emerald-900 ring-emerald-200'
        "
      >
        <span>{{ toast.type === 'error' ? '⚠' : '✓' }}</span>
        <p class="text-sm font-medium">{{ toast.text }}</p>
      </div>

      <p v-if="loadError" class="rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-800 ring-1 ring-rose-200">
        {{ loadError }}
      </p>

      <div v-else-if="loading" class="space-y-4">
        <div v-for="n in 3" :key="n" class="h-56 animate-pulse rounded-3xl bg-white/60 ring-1 ring-ink-200/50" />
      </div>

      <div v-else-if="sessions.length === 0" class="rounded-3xl bg-white/90 p-10 text-center shadow-card ring-1 ring-ink-200/60">
        <p class="text-lg font-medium text-ink-800">Noch keine Session</p>
        <p class="mt-2 text-sm text-ink-500">Lege eine Zuordnungssession mit fünf Phasen an.</p>
        <button
          type="button"
          class="mt-6 rounded-2xl bg-ink-900 px-5 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-black"
          @click="openCreate"
        >
          Erste Session anlegen
        </button>
      </div>

      <div v-else class="flex flex-col gap-8">
        <article
          v-for="row in sessions"
          :key="row.id"
          class="overflow-hidden rounded-3xl bg-white/95 shadow-card ring-1 ring-ink-200/65"
        >
          <div
            class="flex flex-col gap-4 border-b border-ink-100 bg-gradient-to-r from-ink-50/80 to-white px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
          >
            <div>
              <h2 class="text-xl font-bold tracking-tight text-ink-900">{{ row.name }}</h2>
              <p class="mt-0.5 text-xs text-ink-500">Fünf Zeitpunkte · Reihenfolge beachten</p>
            </div>
            <div class="flex flex-wrap gap-2">
              <button
                type="button"
                class="rounded-xl border border-ink-200 bg-white px-4 py-2 text-sm font-medium text-ink-800 shadow-sm transition hover:bg-ink-50"
                @click="openEdit(row)"
              >
                Bearbeiten
              </button>
              <button
                type="button"
                class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-medium text-rose-900 transition hover:bg-rose-100"
                @click="confirmDelete(row)"
              >
                Löschen
              </button>
            </div>
          </div>

          <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3">
            <div
              v-for="p in PHASE_META"
              :key="p.field"
              class="rounded-2xl bg-gradient-to-br p-4 ring-1"
              :class="p.accent"
            >
              <div class="flex items-baseline justify-between gap-2">
                <span
                  class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white/90 text-xs font-bold text-ink-800 shadow-sm ring-1 ring-ink-200/60"
                >
                  {{ p.num }}
                </span>
                <time
                  class="text-right font-mono text-sm font-semibold tabular-nums text-ink-900"
                  :datetime="row[p.field]"
                >
                  {{ formatPhaseDisplay(row[p.field]) }}
                </time>
              </div>
              <p class="mt-3 text-sm font-semibold text-ink-900">{{ p.title }}</p>
              <p class="mt-1 text-xs leading-relaxed text-ink-600">{{ p.hint }}</p>
            </div>
          </div>
        </article>
      </div>
    </main>

    <!-- Modal -->
    <Teleport to="body">
      <div
        v-if="modalOpen"
        class="fixed inset-0 z-50 flex items-end justify-center bg-ink-900/40 p-4 backdrop-blur-sm sm:items-center"
        role="presentation"
        @click.self="closeModal"
      >
        <div
          role="dialog"
          aria-modal="true"
          :aria-labelledby="'session-modal-title'"
          class="max-h-[min(92dvh,900px)] w-full max-w-xl overflow-y-auto rounded-3xl bg-white shadow-2xl ring-1 ring-ink-200"
        >
          <div class="sticky top-0 z-10 flex items-center justify-between border-b border-ink-100 bg-white/95 px-5 py-4 backdrop-blur">
            <h2 id="session-modal-title" class="text-lg font-bold text-ink-900">{{ modalTitle }}</h2>
            <button
              type="button"
              class="rounded-lg p-2 text-ink-500 hover:bg-ink-100 hover:text-ink-900"
              aria-label="Schliessen"
              @click="closeModal"
            >
              ✕
            </button>
          </div>

          <form class="space-y-5 p-5" @submit.prevent="submitForm">
            <div>
              <label for="sess-name" class="mb-1.5 block text-sm font-medium text-ink-700">Name</label>
              <input
                id="sess-name"
                v-model="form.name"
                type="text"
                required
                maxlength="255"
                placeholder="z. B. IDPA/SA 2025/26"
                class="w-full rounded-xl border-0 bg-ink-50 px-4 py-3 text-ink-900 ring-1 ring-ink-200/80 focus:outline-none focus:ring-2 focus:ring-teal-500/35"
              />
            </div>

            <div class="space-y-4">
              <p class="text-xs font-semibold uppercase tracking-wider text-ink-500">Phasen (Datum &amp; Uhrzeit)</p>
              <div
                v-for="p in PHASE_META"
                :key="p.field"
                class="rounded-2xl border border-ink-100 bg-ink-50/50 p-4"
              >
                <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                  <div>
                    <p class="text-sm font-semibold text-ink-900">
                      <span class="mr-2 inline-flex h-6 w-6 items-center justify-center rounded-md bg-white text-xs font-bold text-teal-800 ring-1 ring-teal-200">
                        {{ p.num }}
                      </span>
                      {{ p.title }}
                    </p>
                    <p class="mt-1 pl-8 text-xs text-ink-600">{{ p.hint }}</p>
                  </div>
                </div>
                <input
                  v-model="form[p.field]"
                  type="datetime-local"
                  required
                  class="mt-3 w-full rounded-xl border-0 bg-white px-3 py-2.5 font-mono text-sm text-ink-900 ring-1 ring-ink-200/80 focus:outline-none focus:ring-2 focus:ring-teal-500/35"
                />
              </div>
            </div>

            <div class="flex flex-wrap justify-end gap-3 border-t border-ink-100 pt-4">
              <button
                type="button"
                class="rounded-xl px-4 py-2.5 text-sm font-medium text-ink-700 hover:bg-ink-100"
                @click="closeModal"
              >
                Abbrechen
              </button>
              <button
                type="submit"
                :disabled="saving"
                class="rounded-xl bg-gradient-to-r from-emerald-600 to-teal-700 px-5 py-2.5 text-sm font-semibold text-white shadow-md disabled:opacity-50"
              >
                {{ saving ? 'Speichern …' : 'Speichern' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Delete confirm -->
    <Teleport to="body">
      <div
        v-if="deleteTarget"
        class="fixed inset-0 z-[60] flex items-center justify-center bg-ink-900/45 p-4 backdrop-blur-sm"
        @click.self="cancelDelete"
      >
        <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl ring-1 ring-ink-200">
          <p class="text-lg font-semibold text-ink-900">Session löschen?</p>
          <p class="mt-2 text-sm text-ink-600">
            „{{ deleteTarget.name }}“ wird unwiderruflich entfernt.
          </p>
          <div class="mt-6 flex justify-end gap-3">
            <button
              type="button"
              class="rounded-xl px-4 py-2 text-sm font-medium text-ink-700 hover:bg-ink-100"
              @click="cancelDelete"
            >
              Abbrechen
            </button>
            <button
              type="button"
              :disabled="deleting"
              class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50"
              @click="doDelete"
            >
              {{ deleting ? '…' : 'Löschen' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
