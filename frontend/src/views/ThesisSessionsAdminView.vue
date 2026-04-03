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
    title: 'Lernende: Einschreiben möglich',
    hint: 'Ab hier können sich Lernende eintragen.',
  },
  {
    field: 'phase_2_at',
    num: 2,
    title: 'Lehrpersonen: Einsicht',
    hint: 'LP sehen die Themen (Lesephase).',
  },
  {
    field: 'phase_3_at',
    num: 3,
    title: 'Lehrpersonen: sich eintragen',
    hint: 'LP können sich für Betreuungen eintragen.',
  },
  {
    field: 'phase_4_at',
    num: 4,
    title: 'Nur noch LP-Austragen',
    hint: 'Lernende nicht mehr eintragen; LP nur noch austragen.',
  },
  {
    field: 'phase_5_at',
    num: 5,
    title: 'Nur Administrator / Gott',
    hint: 'Weitere Änderungen nur mit höchster Rolle.',
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
  <div class="min-h-dvh bg-ink-50">
    <header class="sticky top-0 z-20 border-b border-ink-200 bg-white/95 backdrop-blur">
      <div class="mx-auto flex max-w-2xl flex-wrap items-center justify-between gap-2 px-3 py-2 sm:px-4">
        <div class="flex min-w-0 items-center gap-2">
          <RouterLink
            to="/"
            class="shrink-0 rounded-lg px-2 py-1 text-sm text-ink-600 hover:bg-ink-100 hover:text-ink-900"
          >
            ← Start
          </RouterLink>
          <h1 class="truncate text-base font-semibold text-ink-900">Zuordnungssessions</h1>
        </div>
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800 sm:text-sm"
            @click="openCreate"
          >
            + Neu
          </button>
          <div
            v-if="currentUser"
            class="hidden items-center gap-2 rounded-lg border border-ink-200 bg-white px-2 py-1 sm:flex"
          >
            <span class="max-w-[10rem] truncate text-xs text-ink-700">{{ currentUser.full_name }}</span>
            <span
              class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-[10px] font-bold text-white"
            >
              {{ initials(currentUser) }}
            </span>
          </div>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-2xl px-3 py-4 sm:px-4">
      <div
        v-if="toast.text"
        role="status"
        class="mb-3 flex items-center gap-2 rounded-lg border px-3 py-2 text-sm"
        :class="
          toast.type === 'error'
            ? 'border-rose-200 bg-rose-50 text-rose-900'
            : 'border-emerald-200 bg-emerald-50 text-emerald-900'
        "
      >
        <span>{{ toast.type === 'error' ? '⚠' : '✓' }}</span>
        <span class="font-medium">{{ toast.text }}</span>
      </div>

      <p v-if="loadError" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
        {{ loadError }}
      </p>

      <div v-else-if="loading" class="space-y-2">
        <div v-for="n in 2" :key="n" class="h-40 animate-pulse rounded-lg bg-ink-200/60" />
      </div>

      <div v-else-if="sessions.length === 0" class="rounded-lg border border-ink-200 bg-white p-6 text-center">
        <p class="text-sm font-medium text-ink-800">Noch keine Session</p>
        <button
          type="button"
          class="mt-3 rounded-lg bg-ink-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black"
          @click="openCreate"
        >
          Anlegen
        </button>
      </div>

      <div v-else class="flex flex-col gap-3">
        <article
          v-for="row in sessions"
          :key="row.id"
          class="overflow-hidden rounded-lg border border-ink-200 bg-white shadow-sm"
        >
          <div class="flex items-center justify-between gap-2 border-b border-ink-100 bg-ink-50/50 px-3 py-2">
            <h2 class="min-w-0 truncate text-sm font-semibold text-ink-900 sm:text-base">{{ row.name }}</h2>
            <div class="flex shrink-0 gap-1">
              <button
                type="button"
                class="rounded border border-ink-200 bg-white px-2 py-1 text-xs font-medium text-ink-800 hover:bg-ink-50"
                @click="openEdit(row)"
              >
                Bearbeiten
              </button>
              <button
                type="button"
                class="rounded border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-medium text-rose-800 hover:bg-rose-100"
                @click="confirmDelete(row)"
              >
                Löschen
              </button>
            </div>
          </div>

          <ol class="divide-y divide-ink-100 px-3 py-0.5">
            <li
              v-for="p in PHASE_META"
              :key="p.field"
              class="flex gap-3 py-2 text-sm"
            >
              <span
                class="w-6 shrink-0 pt-0.5 text-center text-xs font-bold tabular-nums text-teal-700"
              >
                {{ p.num }}
              </span>
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                  <span class="font-medium leading-snug text-ink-900">{{ p.title }}</span>
                  <time
                    class="shrink-0 font-mono text-xs font-semibold tabular-nums text-ink-800 sm:text-sm"
                    :datetime="row[p.field]"
                  >
                    {{ formatPhaseDisplay(row[p.field]) }}
                  </time>
                </div>
                <p class="mt-0.5 text-xs leading-snug text-ink-500">{{ p.hint }}</p>
              </div>
            </li>
          </ol>
        </article>
      </div>
    </main>

    <!-- Modal -->
    <Teleport to="body">
      <div
        v-if="modalOpen"
        class="fixed inset-0 z-50 flex items-end justify-center bg-black/35 p-0 sm:items-center sm:p-4"
        role="presentation"
        @click.self="closeModal"
      >
        <div
          role="dialog"
          aria-modal="true"
          :aria-labelledby="'session-modal-title'"
          class="max-h-[min(95dvh,720px)] w-full max-w-lg overflow-y-auto rounded-t-2xl border border-ink-200 bg-white shadow-xl sm:rounded-2xl"
        >
          <div class="sticky top-0 flex items-center justify-between border-b border-ink-100 bg-white px-3 py-2">
            <h2 id="session-modal-title" class="text-sm font-semibold text-ink-900 sm:text-base">{{ modalTitle }}</h2>
            <button
              type="button"
              class="rounded p-1.5 text-ink-500 hover:bg-ink-100"
              aria-label="Schliessen"
              @click="closeModal"
            >
              ✕
            </button>
          </div>

          <form class="space-y-3 p-3 sm:p-4" @submit.prevent="submitForm">
            <div>
              <label for="sess-name" class="mb-0.5 block text-xs font-medium text-ink-600">Name</label>
              <input
                id="sess-name"
                v-model="form.name"
                type="text"
                required
                maxlength="255"
                placeholder="z. B. IDPA/SA 2025/26"
                class="w-full rounded-lg border border-ink-200 bg-white px-2.5 py-2 text-sm text-ink-900 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
              />
            </div>

            <div class="border-t border-ink-100 pt-2">
              <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-ink-500">
                Phasen (untereinander · Datum &amp; Uhrzeit)
              </p>
              <ol class="space-y-2">
                <li
                  v-for="p in PHASE_META"
                  :key="p.field"
                  class="rounded-lg border border-ink-100 bg-ink-50/40 px-2.5 py-2"
                >
                  <div class="flex gap-2">
                    <span
                      class="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-white text-[10px] font-bold text-teal-800 ring-1 ring-teal-200/80"
                    >
                      {{ p.num }}
                    </span>
                    <div class="min-w-0 flex-1">
                      <p class="text-xs font-semibold leading-tight text-ink-900">{{ p.title }}</p>
                      <p class="text-[11px] leading-snug text-ink-500">{{ p.hint }}</p>
                      <input
                        v-model="form[p.field]"
                        type="datetime-local"
                        required
                        class="mt-1.5 w-full rounded border border-ink-200 bg-white px-2 py-1.5 font-mono text-xs text-ink-900 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                      />
                    </div>
                  </div>
                </li>
              </ol>
            </div>

            <div class="flex justify-end gap-2 border-t border-ink-100 pt-3">
              <button
                type="button"
                class="rounded-lg px-3 py-1.5 text-sm text-ink-700 hover:bg-ink-100"
                @click="closeModal"
              >
                Abbrechen
              </button>
              <button
                type="submit"
                :disabled="saving"
                class="rounded-lg bg-emerald-700 px-4 py-1.5 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-50"
              >
                {{ saving ? '…' : 'Speichern' }}
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
        class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4"
        @click.self="cancelDelete"
      >
        <div class="w-full max-w-sm rounded-xl border border-ink-200 bg-white p-4 shadow-lg">
          <p class="text-sm font-semibold text-ink-900">Session löschen?</p>
          <p class="mt-1 text-xs text-ink-600">
            „{{ deleteTarget.name }}“ wird entfernt.
          </p>
          <div class="mt-4 flex justify-end gap-2">
            <button
              type="button"
              class="rounded-lg px-3 py-1.5 text-sm text-ink-700 hover:bg-ink-100"
              @click="cancelDelete"
            >
              Abbrechen
            </button>
            <button
              type="button"
              :disabled="deleting"
              class="rounded-lg bg-rose-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50"
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
