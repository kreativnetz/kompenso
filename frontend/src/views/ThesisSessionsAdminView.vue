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
    title: 'Lernende: Themeneingabe startet',
    hint: 'Neueinreichungen bis vor Phase 4; Bearbeitung mit Code nur bis vor Phase 2.',
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
    title: 'Lehrpersonen: eintragen & austragen',
    hint: 'LP können Betreuungen wählen und sich wieder austragen (bis vor Phase 4).',
  },
  {
    field: 'phase_4_at',
    num: 4,
    title: 'Lehrpersonen: eintragen ohne Austragen',
    hint: 'Austragen für LP endet; Selbsteintrag bis vor Phase 5.',
  },
  {
    field: 'phase_5_at',
    num: 5,
    title: 'LP-Selbsteintrag endet',
    hint: 'Weitere Zuordnung durch Schulleitung / Administration (bis Session geschlossen).',
  },
]

const RULE_OPTIONS = [
  { value: 0, label: 'Nein' },
  { value: 1, label: 'Ja' },
  { value: 2, label: 'Bewilligung' },
]

const currentUser = ref(getUser())
const sessions = ref([])
const schoolyears = ref([])
const loading = ref(true)
const loadError = ref('')
const modalOpen = ref(false)
const saving = ref(false)
const editingId = ref(null)
const toast = ref({ type: '', text: '' })
const deleteTarget = ref(null)
const deleting = ref(false)

const form = ref(emptyFormShell())

function emptyFormShell() {
  return {
    schoolyear_id: '',
    name: '',
    phase_1_at: '',
    phase_2_at: '',
    phase_3_at: '',
    phase_4_at: '',
    phase_5_at: '',
    closed_at: '',
    copy_defaults: false,
    authorMatrix: {},
    comp: emptyComp(),
    submissionSectionsOpen: {},
  }
}

function emptyComp() {
  return {
    haupt: { 1: '', 2: '', 3: '' },
    gegen: { 1: '', 2: '', 3: '' },
  }
}

function toDatetimeLocalValue(date) {
  const pad = (n) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

function emptyFormWithPhases() {
  const d = new Date()
  const step = (hours) => {
    const x = new Date(d)
    x.setHours(x.getHours() + hours, 0, 0, 0)
    return toDatetimeLocalValue(x)
  }
  const shell = emptyFormShell()
  shell.phase_1_at = step(0)
  shell.phase_2_at = step(24)
  shell.phase_3_at = step(48)
  shell.phase_4_at = step(72)
  shell.phase_5_at = step(96)
  return shell
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

function schoolyearById(id) {
  if (id === '' || id == null) {
    return null
  }
  return schoolyears.value.find((y) => String(y.id) === String(id)) || null
}

function sectionKeysForYearId(yearId) {
  const y = schoolyearById(yearId)
  if (!y?.sections || typeof y.sections !== 'object') {
    return []
  }
  return Object.keys(y.sections)
}

function buildMatrixFromRules(yearId, rules) {
  const keys = sectionKeysForYearId(yearId)
  const m = {}
  for (const sk of keys) {
    m[sk] = { 1: 0, 2: 0, 3: 0 }
    const r = rules?.[sk]
    if (r && typeof r === 'object') {
      for (const n of [1, 2, 3]) {
        const v = r[String(n)] ?? r[n]
        if (v !== undefined && v !== '') {
          m[sk][n] = Number(v)
        }
      }
    }
  }
  return m
}

function mergeMatrixPreserve(prev, yearId) {
  const keys = sectionKeysForYearId(yearId)
  const m = {}
  for (const sk of keys) {
    m[sk] = { 1: 0, 2: 0, 3: 0 }
    if (prev?.[sk]) {
      for (const n of [1, 2, 3]) {
        const v = prev[sk][n]
        if (v !== undefined && v !== '') {
          m[sk][n] = Number(v)
        }
      }
    }
  }
  return m
}

function submissionOpenMapFromRow(row, yearId) {
  const keys = sectionKeysForYearId(yearId)
  const out = {}
  const raw = row?.submission_section_keys
  if (raw === undefined || raw === null || !Array.isArray(raw)) {
    for (const k of keys) {
      out[k] = true
    }
    return out
  }
  if (raw.length === 0) {
    for (const k of keys) {
      out[k] = false
    }
    return out
  }
  const set = new Set(raw.map((x) => String(x).toLowerCase()))
  for (const k of keys) {
    out[k] = set.has(String(k).toLowerCase())
  }
  return out
}

function submissionSummaryText(row) {
  const raw = row?.submission_section_keys
  if (raw === undefined || raw === null) {
    return 'Themeneingabe: alle Abteilungen'
  }
  if (Array.isArray(raw) && raw.length === 0) {
    return 'Themeneingabe: keine Abteilung'
  }
  return `Themeneingabe: ${raw.join(', ')}`
}

function matrixToRules(matrix, restrictToKeys = null) {
  const keyList = restrictToKeys != null ? restrictToKeys : Object.keys(matrix)
  const out = {}
  for (const sk of keyList) {
    if (!matrix[sk]) {
      continue
    }
    out[sk] = {
      '1': Number(matrix[sk][1]) || 0,
      '2': Number(matrix[sk][2]) || 0,
      '3': Number(matrix[sk][3]) || 0,
    }
  }
  return out
}

function compFromApi(c) {
  const out = emptyComp()
  if (!c || typeof c !== 'object') {
    return out
  }
  for (const role of ['haupt', 'gegen']) {
    if (!c[role] || typeof c[role] !== 'object') {
      continue
    }
    for (const n of [1, 2, 3]) {
      const raw = c[role][String(n)] ?? c[role][n]
      if (raw !== undefined && raw !== null && raw !== '') {
        out[role][n] = String(raw)
      }
    }
  }
  return out
}

function compToPayload(comp) {
  const hasAny = ['haupt', 'gegen'].some((role) =>
    [1, 2, 3].some((n) => comp[role][n] !== '' && comp[role][n] != null),
  )
  if (!hasAny) {
    return {}
  }
  return {
    haupt: {
      '1': Number(comp.haupt[1]),
      '2': Number(comp.haupt[2]),
      '3': Number(comp.haupt[3]),
    },
    gegen: {
      '1': Number(comp.gegen[1]),
      '2': Number(comp.gegen[2]),
      '3': Number(comp.gegen[3]),
    },
  }
}

function onSchoolyearChange() {
  const prevOpen = { ...form.value.submissionSectionsOpen }
  form.value.authorMatrix = mergeMatrixPreserve(form.value.authorMatrix, form.value.schoolyear_id)
  const keys = sectionKeysForYearId(form.value.schoolyear_id)
  const next = {}
  for (const k of keys) {
    next[k] = prevOpen[k] !== undefined ? prevOpen[k] : true
  }
  form.value.submissionSectionsOpen = next
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

async function loadSchoolyears() {
  const res = await api.schoolyears()
  if (!res.ok) {
    if (res.status === 403) {
      await router.replace({ name: 'home' })
    }
    return false
  }
  const data = await res.json()
  schoolyears.value = data.schoolyears
  return true
}

async function loadSessions() {
  const res = await api.thesisSessions()
  if (!res.ok) {
    return false
  }
  const data = await res.json()
  sessions.value = data.thesis_sessions
  return true
}

async function loadAll() {
  loading.value = true
  loadError.value = ''
  const okY = await loadSchoolyears()
  if (!okY) {
    loadError.value = 'Daten konnten nicht geladen werden.'
    loading.value = false
    return
  }
  const okS = await loadSessions()
  if (!okS) {
    loadError.value = 'Sessions konnten nicht geladen werden.'
    loading.value = false
    return
  }
  loading.value = false
}

function openCreate() {
  editingId.value = null
  const f = emptyFormWithPhases()
  f.schoolyear_id = schoolyears.value[0]?.id ?? ''
  f.authorMatrix = buildMatrixFromRules(f.schoolyear_id, {})
  f.submissionSectionsOpen = submissionOpenMapFromRow(null, f.schoolyear_id)
  f.copy_defaults = false
  form.value = f
  modalOpen.value = true
}

function openEdit(row) {
  editingId.value = row.id
  const f = emptyFormShell()
  f.schoolyear_id = row.schoolyear_id || ''
  f.name = row.name
  f.phase_1_at = row.phase_1_at || ''
  f.phase_2_at = row.phase_2_at || ''
  f.phase_3_at = row.phase_3_at || ''
  f.phase_4_at = row.phase_4_at || ''
  f.phase_5_at = row.phase_5_at || ''
  f.closed_at = row.closed_at || ''
  f.copy_defaults = false
  f.authorMatrix = buildMatrixFromRules(f.schoolyear_id, row.section_author_rules || {})
  f.submissionSectionsOpen = submissionOpenMapFromRow(row, f.schoolyear_id)
  f.comp = compFromApi(row.compensation)
  form.value = f
  modalOpen.value = true
}

function closeModal() {
  if (saving.value) {
    return
  }
  modalOpen.value = false
}

const modalTitle = computed(() => (editingId.value ? 'Session bearbeiten' : 'Neue Session'))

const sectionKeys = computed(() => sectionKeysForYearId(form.value.schoolyear_id))

async function submitForm() {
  if (!form.value.schoolyear_id) {
    showToast('error', 'Bitte Schuljahr wählen.')
    return
  }

  const selectedSubmissionKeys = sectionKeys.value.filter((sk) => form.value.submissionSectionsOpen[sk])
  const sectionRulesPayload = matrixToRules(form.value.authorMatrix, selectedSubmissionKeys)
  const compensationPayload = compToPayload(form.value.comp)

  if (Object.keys(compensationPayload).length > 0) {
    for (const role of ['haupt', 'gegen']) {
      for (const n of [1, 2, 3]) {
        const v = compensationPayload[role][n]
        if (Number.isNaN(v)) {
          showToast('error', 'Entschädigung: alle sechs Felder müssen gültige Zahlen sein.')
          return
        }
      }
    }
  }

  saving.value = true
  const body = {
    schoolyear_id: Number(form.value.schoolyear_id),
    name: form.value.name,
    phase_1_at: form.value.phase_1_at,
    phase_2_at: form.value.phase_2_at,
    phase_3_at: form.value.phase_3_at,
    phase_4_at: form.value.phase_4_at,
    phase_5_at: form.value.phase_5_at,
    closed_at: form.value.closed_at || null,
    section_author_rules: sectionRulesPayload,
    compensation: compensationPayload,
    submission_section_keys: selectedSubmissionKeys.map((k) => String(k).toLowerCase()),
  }
  if (!editingId.value && form.value.copy_defaults) {
    body.copy_defaults = true
  }

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
    sessions.value.sort((a, b) => {
      const la = a.schoolyear?.label || ''
      const lb = b.schoolyear?.label || ''
      const c = lb.localeCompare(la, 'de')
      if (c !== 0) {
        return c
      }
      return a.name.localeCompare(b.name, 'de')
    })
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
  await loadAll()
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
            :disabled="schoolyears.length === 0"
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
      <p
        v-if="!loading && schoolyears.length === 0"
        class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
      >
        Zuerst unter
        <RouterLink to="/schuljahre" class="font-semibold underline underline-offset-2">Schuljahre</RouterLink>
        mindestens ein Schuljahr mit Abteilungen anlegen.
      </p>

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
          :disabled="schoolyears.length === 0"
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
            <div class="min-w-0">
              <p class="text-[11px] font-medium uppercase tracking-wide text-ink-500">
                {{ row.schoolyear?.label || '—' }}
              </p>
              <h2 class="truncate text-sm font-semibold text-ink-900 sm:text-base">{{ row.name }}</h2>
              <p class="mt-0.5 text-[11px] leading-snug text-ink-500">{{ submissionSummaryText(row) }}</p>
            </div>
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
          <div
            v-if="row.closed_at"
            class="border-t border-ink-100 px-3 py-2 text-xs text-ink-600"
          >
            Geschlossen:
            <time class="font-mono font-semibold text-ink-800">{{ formatPhaseDisplay(row.closed_at) }}</time>
          </div>
        </article>
      </div>
    </main>

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
          class="max-h-[min(95dvh,880px)] w-full max-w-lg overflow-y-auto rounded-t-2xl border border-ink-200 bg-white shadow-xl sm:rounded-2xl"
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
              <label for="sess-sy" class="mb-0.5 block text-xs font-medium text-ink-600">Schuljahr</label>
              <select
                id="sess-sy"
                v-model="form.schoolyear_id"
                required
                class="w-full rounded-lg border border-ink-200 bg-white px-2.5 py-2 text-sm text-ink-900 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                @change="onSchoolyearChange"
              >
                <option disabled value="">— wählen —</option>
                <option v-for="y in schoolyears" :key="y.id" :value="y.id">{{ y.label }}</option>
              </select>
            </div>

            <div v-if="!editingId" class="rounded-lg border border-ink-100 bg-ink-50/40 px-2.5 py-2">
              <label class="flex cursor-pointer items-start gap-2">
                <input v-model="form.copy_defaults" type="checkbox" class="mt-0.5" />
                <span class="text-xs text-ink-800">
                  Regeln und Entschädigung von der letzten Session dieses Schuljahrs übernehmen (sonst vom Vorjahr, falls
                  vorhanden). Überschreibt die untenstehenden Felder beim Speichern.
                </span>
              </label>
            </div>

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
                Themeneingabe (Lernende)
              </p>
              <p class="mb-2 text-xs leading-snug text-ink-600">
                Nur angehakte Abteilungen erscheinen in der öffentlichen Maske (während des Einschreibefensters).
              </p>
              <div v-if="sectionKeys.length === 0" class="rounded border border-ink-100 bg-ink-50/50 px-2 py-2 text-xs text-ink-600">
                Keine Abteilungen im gewählten Schuljahr.
              </div>
              <ul v-else class="space-y-1.5 rounded-lg border border-ink-100 bg-ink-50/40 px-2.5 py-2">
                <li v-for="sk in sectionKeys" :key="'sub-' + sk" class="flex items-center gap-2">
                  <input
                    :id="'subsec-' + sk"
                    v-model="form.submissionSectionsOpen[sk]"
                    type="checkbox"
                    class="rounded border-ink-300 text-teal-600 focus:ring-teal-500"
                  />
                  <label :for="'subsec-' + sk" class="cursor-pointer font-mono text-xs font-medium text-ink-900">
                    {{ sk }}
                  </label>
                </li>
              </ul>
            </div>

            <div class="border-t border-ink-100 pt-2">
              <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-ink-500">
                Autorenregeln nur für die Themeneingabe (Lernende)
              </p>
              <p class="mb-2 text-xs leading-snug text-ink-600">
                Gilt nicht für die Betreuung durch Lehrpersonen. Pro Abteilung und Anzahl Lernende: 0 = Einreichen nicht
                erlaubt, 1 = Arbeit sofort aktiv (Thesis-Status „bewilligt“), 2 = bewilligungspflichtig (Thesis-Status
                „ausstehend“ bis Rektorat auf der Themensliste freigibt oder ablehnt).
              </p>
              <div v-if="sectionKeys.length === 0" class="rounded border border-ink-100 bg-ink-50/50 px-2 py-2 text-xs text-ink-600">
                Keine Abteilungen im gewählten Schuljahr — bitte Schuljahr bearbeiten.
              </div>
              <div v-else class="overflow-x-auto rounded-lg border border-ink-100">
                <table class="w-full min-w-[280px] border-collapse text-xs">
                  <thead>
                    <tr class="border-b border-ink-200 bg-ink-50/80">
                      <th class="px-2 py-1.5 text-left font-semibold text-ink-800">Abteilung</th>
                      <th class="px-1 py-1.5 text-center font-semibold text-ink-800">1 Autor</th>
                      <th class="px-1 py-1.5 text-center font-semibold text-ink-800">2</th>
                      <th class="px-1 py-1.5 text-center font-semibold text-ink-800">3</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="sk in sectionKeys" :key="sk" class="border-b border-ink-100 last:border-0">
                      <td class="px-2 py-1 font-mono font-medium text-ink-900">{{ sk }}</td>
                      <td v-for="n in [1, 2, 3]" :key="n" class="px-1 py-1">
                        <select
                          v-model.number="form.authorMatrix[sk][n]"
                          class="w-full min-w-[4.5rem] rounded border border-ink-200 bg-white px-1 py-1 text-ink-900"
                        >
                          <option v-for="opt in RULE_OPTIONS" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                          </option>
                        </select>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="border-t border-ink-100 pt-2">
              <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-ink-500">
                Entschädigung (leer lassen = nicht erfassen)
              </p>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <p class="mb-1 text-xs font-semibold text-ink-800">Hauptbetreuung</p>
                  <div class="space-y-1">
                    <label v-for="n in [1, 2, 3]" :key="'h' + n" class="flex items-center gap-2 text-xs text-ink-700">
                      <span class="w-16 shrink-0">{{ n }} Autor(en)</span>
                      <input
                        v-model="form.comp.haupt[n]"
                        type="text"
                        inputmode="decimal"
                        class="min-w-0 flex-1 rounded border border-ink-200 px-2 py-1 font-mono text-xs"
                        placeholder="0.2"
                      />
                    </label>
                  </div>
                </div>
                <div>
                  <p class="mb-1 text-xs font-semibold text-ink-800">Gegenbetreuung</p>
                  <div class="space-y-1">
                    <label v-for="n in [1, 2, 3]" :key="'g' + n" class="flex items-center gap-2 text-xs text-ink-700">
                      <span class="w-16 shrink-0">{{ n }} Autor(en)</span>
                      <input
                        v-model="form.comp.gegen[n]"
                        type="text"
                        inputmode="decimal"
                        class="min-w-0 flex-1 rounded border border-ink-200 px-2 py-1 font-mono text-xs"
                        placeholder="0.07"
                      />
                    </label>
                  </div>
                </div>
              </div>
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

            <div class="border-t border-ink-100 pt-2">
              <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-ink-500">
                Session schliessen (optional)
              </p>
              <p class="mb-2 text-xs text-ink-500">
                Ab diesem Zeitpunkt ist die Session archiviert; Lehrpersonen können weiter einsehen, aber nichts mehr
                ändern.
              </p>
              <input
                v-model="form.closed_at"
                type="datetime-local"
                class="w-full rounded border border-ink-200 bg-white px-2 py-1.5 font-mono text-xs text-ink-900 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
              />
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
