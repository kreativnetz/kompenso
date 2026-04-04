<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import { getUser, setUser } from '../lib/auth'

const router = useRouter()

const KEY_RE = /^[a-z0-9_]+$/

function emptySectionRow() {
  return {
    key: '',
    name: '',
    prefix: '',
    terms: '',
    exam_year: '',
    finish_class_count: 1,
  }
}

function lastLetterToCount(letter) {
  if (!letter || typeof letter !== 'string') {
    return null
  }
  const c = letter.trim().toLowerCase()
  if (c.length !== 1 || c < 'a' || c > 'z') {
    return null
  }
  return c.charCodeAt(0) - 96
}

function sectionsListFromApi(sections) {
  if (!sections || typeof sections !== 'object' || Array.isArray(sections)) {
    return [emptySectionRow()]
  }
  const keys = Object.keys(sections)
  if (keys.length === 0) {
    return [emptySectionRow()]
  }
  return keys.map((key) => {
    const s = sections[key] || {}
    let finish = Number(s.finish_class_count)
    if (!Number.isFinite(finish) || finish < 1) {
      const fromLetter = lastLetterToCount(s.last_letter)
      finish = fromLetter ?? 1
    }
    finish = Math.min(26, Math.max(1, finish))
    const ey =
      s.exam_year !== undefined && s.exam_year !== null && s.exam_year !== ''
        ? Number(s.exam_year)
        : ''
    return {
      key,
      name: s.name ?? '',
      prefix: s.prefix ?? '',
      terms: s.terms !== undefined && s.terms !== '' ? Number(s.terms) : '',
      exam_year: Number.isFinite(ey) ? ey : '',
      finish_class_count: finish,
    }
  })
}

function sectionsToApi(list) {
  const out = {}
  for (const row of list) {
    const k = String(row.key || '')
      .trim()
      .toLowerCase()
    if (!k) {
      continue
    }
    const terms = parseInt(row.terms, 10)
    const ey = row.exam_year === '' || row.exam_year == null ? 0 : parseInt(row.exam_year, 10)
    let fc = parseInt(row.finish_class_count, 10)
    if (!Number.isFinite(fc) || fc < 1) {
      fc = 1
    }
    fc = Math.min(26, fc)
    out[k] = {
      name: String(row.name || '').trim(),
      prefix: String(row.prefix || '').trim(),
      terms: Number.isFinite(terms) ? terms : 0,
      exam_year: Number.isFinite(ey) ? ey : 0,
      finish_class_count: fc,
    }
  }
  return out
}

function previewClassCodes(prefix, examYear, count) {
  const p = String(prefix || '').trim()
  const y = examYear === '' || examYear == null ? '?' : String(examYear)
  const n = Math.min(26, Math.max(0, Number(count) || 0))
  if (!p || n < 1) {
    return '—'
  }
  const parts = []
  for (let i = 0; i < n; i++) {
    parts.push(`${p}${y}${String.fromCharCode(97 + i)}`)
  }
  return parts.join(', ')
}

function validateSectionsList(list) {
  const seen = new Set()
  let any = false
  for (const row of list) {
    const k = String(row.key || '')
      .trim()
      .toLowerCase()
    if (!k) {
      continue
    }
    any = true
    if (!KEY_RE.test(k)) {
      return `Ungültiger Schlüssel „${k}“: nur a–z, 0–9, Unterstrich.`
    }
    if (seen.has(k)) {
      return `Doppelter Schlüssel „${k}“.`
    }
    seen.add(k)
    if (!String(row.name || '').trim()) {
      return `Abteilung „${k}“: Bezeichnung fehlt.`
    }
    if (!String(row.prefix || '').trim()) {
      return `Abteilung „${k}“: Prefix fehlt.`
    }
    const terms = parseInt(row.terms, 10)
    if (!Number.isFinite(terms) || terms < 1 || terms > 7) {
      return `Abteilung „${k}“: Ausbildungsjahre 1–7.`
    }
    const ey = row.exam_year === '' || row.exam_year == null ? 0 : parseInt(row.exam_year, 10)
    if (!Number.isFinite(ey) || ey < 0 || ey > 99) {
      return `Abteilung „${k}“: Abschlussjahrgang 0–99 (z. B. 24).`
    }
    let fc = parseInt(row.finish_class_count, 10)
    if (!Number.isFinite(fc) || fc < 1 || fc > 26) {
      return `Abteilung „${k}“: Anzahl Abschlussklassen 1–26.`
    }
  }
  if (!any) {
    return 'Mindestens eine Abteilung mit Schlüssel anlegen.'
  }
  return ''
}

const currentUser = ref(getUser())
const schoolyears = ref([])
const loading = ref(true)
const loadError = ref('')
const modalOpen = ref(false)
const saving = ref(false)
const editingId = ref(null)
const toast = ref({ type: '', text: '' })
const deleteTarget = ref(null)
const deleting = ref(false)

const sectionsList = ref([emptySectionRow()])
const showAdvancedJson = ref(false)
const advancedSectionsJson = ref('')

const form = ref(emptyFormMeta())

function emptyFormMeta() {
  const y = new Date().getFullYear()
  return {
    label: `${y}/${String(y + 1).slice(-2)}`,
    starts_on: `${y}-08-01`,
    ends_on: `${y + 1}-07-31`,
    copy_from_previous: true,
    copy_from_schoolyear_id: '',
  }
}

const copySourceOptions = computed(() => schoolyears.value.filter((x) => x.id !== editingId.value))

/** Neues Schuljahr: Abteilungen kommen vom Server per Import, kein manuelles Formular */
const importingSectionsOnCreate = computed(
  () =>
    !editingId.value &&
    (form.value.copy_from_previous || String(form.value.copy_from_schoolyear_id || '').trim() !== ''),
)

const showSectionCards = computed(
  () => !showAdvancedJson.value && (editingId.value || !importingSectionsOnCreate.value),
)

const showAdvancedJsonEditor = computed(
  () => showAdvancedJson.value && !importingSectionsOnCreate.value,
)

watch(importingSectionsOnCreate, (v) => {
  if (v) {
    showAdvancedJson.value = false
  }
})

const sectionDeletePending = ref(null)

const sectionDeleteLabel = computed(() => {
  const i = sectionDeletePending.value
  return sectionsList.value[i]?.name || ''
})

const copySourceLabel = computed(() => {
  if (!form.value.copy_from_schoolyear_id) {
    return ''
  }
  const y = schoolyears.value.find(
    (x) => String(x.id) === String(form.value.copy_from_schoolyear_id),
  )
  return y?.label || ''
})

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
  loading.value = true
  loadError.value = ''
  const res = await api.schoolyears()
  if (!res.ok) {
    if (res.status === 403) {
      await router.replace({ name: 'home' })
      return
    }
    loadError.value = 'Schuljahre konnten nicht geladen werden.'
    loading.value = false
    return
  }
  const data = await res.json()
  schoolyears.value = data.schoolyears
  loading.value = false
}

function openCreate() {
  editingId.value = null
  sectionDeletePending.value = null
  form.value = emptyFormMeta()
  sectionsList.value = [emptySectionRow()]
  showAdvancedJson.value = false
  advancedSectionsJson.value = ''
  modalOpen.value = true
}

function openEdit(row) {
  editingId.value = row.id
  sectionDeletePending.value = null
  form.value = {
    label: row.label,
    starts_on: row.starts_on || '',
    ends_on: row.ends_on || '',
    copy_from_previous: false,
    copy_from_schoolyear_id: '',
  }
  sectionsList.value = sectionsListFromApi(row.sections)
  showAdvancedJson.value = false
  advancedSectionsJson.value = ''
  modalOpen.value = true
}

function closeModal() {
  if (saving.value) {
    return
  }
  sectionDeletePending.value = null
  modalOpen.value = false
}

const modalTitle = computed(() => (editingId.value ? 'Schuljahr bearbeiten' : 'Neues Schuljahr'))

function addSection() {
  sectionsList.value.push(emptySectionRow())
}

function requestRemoveSection(index) {
  sectionDeletePending.value = index
}

function cancelSectionRemove() {
  sectionDeletePending.value = null
}

function confirmSectionRemove() {
  const index = sectionDeletePending.value
  sectionDeletePending.value = null
  if (index == null) {
    return
  }
  if (sectionsList.value.length <= 1) {
    sectionsList.value = [emptySectionRow()]
    return
  }
  sectionsList.value.splice(index, 1)
}

function onCopyFromPreviousChange() {
  if (form.value.copy_from_previous) {
    form.value.copy_from_schoolyear_id = ''
  }
}

function toggleAdvancedJson() {
  showAdvancedJson.value = !showAdvancedJson.value
  if (showAdvancedJson.value) {
    const err = validateSectionsList(sectionsList.value)
    const obj = err ? {} : sectionsToApi(sectionsList.value)
    advancedSectionsJson.value = JSON.stringify(obj, null, 2)
  }
}

async function submitForm() {
  const hasCopy =
    !editingId.value &&
    (form.value.copy_from_previous || !!form.value.copy_from_schoolyear_id)

  let sections
  if (showAdvancedJson.value && !importingSectionsOnCreate.value) {
    try {
      sections = JSON.parse(advancedSectionsJson.value)
    } catch {
      showToast('error', 'Erweitert: Ungültiges JSON.')
      return
    }
    if (sections === null || typeof sections !== 'object' || Array.isArray(sections)) {
      showToast('error', 'Abteilungen müssen ein JSON-Objekt sein.')
      return
    }
  } else if (hasCopy) {
    sections = {
      _placeholder: { name: '-', prefix: 'X', terms: 1, exam_year: 0, finish_class_count: 1 },
    }
  } else {
    const err = validateSectionsList(sectionsList.value)
    if (err) {
      showToast('error', err)
      return
    }
    sections = sectionsToApi(sectionsList.value)
  }

  saving.value = true
  const body = {
    label: form.value.label,
    starts_on: form.value.starts_on,
    ends_on: form.value.ends_on,
    sections,
  }
  if (!editingId.value) {
    if (form.value.copy_from_schoolyear_id) {
      body.copy_from_schoolyear_id = Number(form.value.copy_from_schoolyear_id)
    } else if (form.value.copy_from_previous) {
      body.copy_from_previous = true
    }
  }

  const res = editingId.value
    ? await api.updateSchoolyear(editingId.value, body)
    : await api.createSchoolyear(body)
  saving.value = false

  if (!res.ok) {
    const errBody = await res.json().catch(() => ({}))
    const msg =
      errBody.message ||
      (errBody.errors && Object.values(errBody.errors).flat().join(' ')) ||
      'Speichern fehlgeschlagen.'
    showToast('error', msg)
    return
  }

  const data = await res.json()
  const row = data.schoolyear
  if (editingId.value) {
    const i = schoolyears.value.findIndex((y) => y.id === row.id)
    if (i !== -1) {
      schoolyears.value[i] = row
    }
  } else {
    schoolyears.value.push(row)
    schoolyears.value.sort((a, b) => (b.starts_on || '').localeCompare(a.starts_on || ''))
  }
  modalOpen.value = false
  showToast('success', editingId.value ? 'Gespeichert.' : 'Schuljahr angelegt.')
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
  const res = await api.deleteSchoolyear(deleteTarget.value.id)
  deleting.value = false
  if (!res.ok) {
    const errBody = await res.json().catch(() => ({}))
    showToast('error', errBody.message || 'Löschen fehlgeschlagen.')
    return
  }
  schoolyears.value = schoolyears.value.filter((y) => y.id !== deleteTarget.value.id)
  showToast('success', 'Schuljahr gelöscht.')
  deleteTarget.value = null
}

function initials(u) {
  const a = (u?.first_name || '').trim().charAt(0)
  const b = (u?.last_name || '').trim().charAt(0)
  return (a + b).toUpperCase() || '?'
}

function sectionCount(row) {
  const s = row.sections
  if (!s || typeof s !== 'object') {
    return 0
  }
  return Object.keys(s).length
}

onMounted(async () => {
  await refreshMe()
  await loadSchoolyears()
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
          <h1 class="truncate text-base font-semibold text-ink-900">Schuljahre</h1>
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
        <div v-for="n in 2" :key="n" class="h-28 animate-pulse rounded-lg bg-ink-200/60" />
      </div>

      <div v-else-if="schoolyears.length === 0" class="rounded-lg border border-ink-200 bg-white p-6 text-center">
        <p class="text-sm font-medium text-ink-800">Noch kein Schuljahr</p>
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
          v-for="row in schoolyears"
          :key="row.id"
          class="overflow-hidden rounded-lg border border-ink-200 bg-white shadow-sm"
        >
          <div class="flex items-center justify-between gap-2 border-b border-ink-100 bg-ink-50/50 px-3 py-2">
            <div class="min-w-0">
              <h2 class="truncate text-sm font-semibold text-ink-900 sm:text-base">{{ row.label }}</h2>
              <p class="text-xs text-ink-600">
                {{ row.starts_on }} – {{ row.ends_on }} · {{ sectionCount(row) }} Abteilung(en)
              </p>
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
          :aria-labelledby="'sy-modal-title'"
          class="max-h-[min(95dvh,900px)] w-full max-w-lg overflow-y-auto rounded-t-2xl border border-ink-200 bg-white shadow-xl sm:rounded-2xl"
        >
          <div class="sticky top-0 flex items-center justify-between border-b border-ink-100 bg-white px-3 py-2">
            <h2 id="sy-modal-title" class="text-sm font-semibold text-ink-900 sm:text-base">{{ modalTitle }}</h2>
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
              <label for="sy-label" class="mb-0.5 block text-xs font-medium text-ink-600">Bezeichnung</label>
              <input
                id="sy-label"
                v-model="form.label"
                type="text"
                required
                maxlength="255"
                placeholder="z. B. 2025/26"
                class="w-full rounded-lg border border-ink-200 bg-white px-2.5 py-2 text-sm text-ink-900 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
              />
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label for="sy-start" class="mb-0.5 block text-xs font-medium text-ink-600">Start</label>
                <input
                  id="sy-start"
                  v-model="form.starts_on"
                  type="date"
                  required
                  class="w-full rounded-lg border border-ink-200 bg-white px-2.5 py-2 text-sm text-ink-900 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                />
              </div>
              <div>
                <label for="sy-end" class="mb-0.5 block text-xs font-medium text-ink-600">Ende</label>
                <input
                  id="sy-end"
                  v-model="form.ends_on"
                  type="date"
                  required
                  class="w-full rounded-lg border border-ink-200 bg-white px-2.5 py-2 text-sm text-ink-900 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                />
              </div>
            </div>

            <template v-if="!editingId">
              <div class="rounded-lg border border-ink-100 bg-ink-50/40 px-2.5 py-2">
                <label class="flex cursor-pointer items-start gap-2">
                  <input
                    v-model="form.copy_from_previous"
                    type="checkbox"
                    class="mt-0.5"
                    :disabled="!!form.copy_from_schoolyear_id"
                    @change="onCopyFromPreviousChange"
                  />
                  <span class="text-xs text-ink-800">
                    Abteilungen aus chronologisch vorhergehendem Schuljahr übernehmen (nach Speichern anpassbar).
                  </span>
                </label>
              </div>
              <div>
                <label for="sy-copy-id" class="mb-0.5 block text-xs font-medium text-ink-600">
                  Oder Vorlage aus Schuljahr
                </label>
                <select
                  id="sy-copy-id"
                  v-model="form.copy_from_schoolyear_id"
                  class="w-full rounded-lg border border-ink-200 bg-white px-2.5 py-2 text-sm text-ink-900 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                  @change="form.copy_from_previous = false"
                >
                  <option value="">— nicht kopieren —</option>
                  <option v-for="y in copySourceOptions" :key="y.id" :value="String(y.id)">
                    {{ y.label }}
                  </option>
                </select>
              </div>
            </template>

            <div class="border-t border-ink-100 pt-2">
              <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-500">Abteilungen</p>
                <div v-if="showSectionCards || showAdvancedJsonEditor" class="flex flex-wrap gap-1">
                  <button
                    v-if="showSectionCards"
                    type="button"
                    class="rounded border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-900 hover:bg-emerald-100"
                    @click="addSection"
                  >
                    + Abteilung
                  </button>
                  <button
                    v-if="!importingSectionsOnCreate"
                    type="button"
                    class="rounded border border-ink-200 bg-white px-2 py-0.5 text-xs text-ink-700 hover:bg-ink-50"
                    @click="toggleAdvancedJson"
                  >
                    {{ showAdvancedJson ? 'Formular' : 'Erweitert: JSON' }}
                  </button>
                </div>
              </div>

              <p
                v-if="importingSectionsOnCreate"
                class="mb-2 rounded-lg border border-teal-100 bg-teal-50/50 px-2.5 py-2 text-xs leading-snug text-ink-800"
              >
                <template v-if="form.copy_from_schoolyear_id">
                  Abteilungen werden aus „{{ copySourceLabel }}“ übernommen.
                </template>
                <template v-else>
                  Abteilungen werden aus dem chronologisch vorhergehenden Schuljahr übernommen (falls vorhanden).
                </template>
                Die <strong>Abschlussjahrgänge</strong> in den Abteilungen werden beim Speichern automatisch um die
                Differenz der Startjahre angepasst (z. B. 24 → 25 bei einem Jahr Abstand). Anschliessend unter
                „Bearbeiten“ anpassbar.
              </p>

              <div v-if="showAdvancedJsonEditor">
                <textarea
                  v-model="advancedSectionsJson"
                  rows="14"
                  spellcheck="false"
                  class="w-full rounded-lg border border-ink-200 bg-white px-2.5 py-2 font-mono text-xs text-ink-900 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                />
                <p class="mt-1 text-[11px] text-ink-500">
                  Direktes Bearbeiten des Section-Objekts. Beim Speichern wird nur dieses JSON verwendet.
                </p>
              </div>

              <div v-else-if="showSectionCards" class="flex flex-col gap-2">
                <div
                  v-for="(row, idx) in sectionsList"
                  :key="idx"
                  class="rounded-lg border border-ink-100 bg-ink-50/30 p-2.5"
                >
                  <div class="mb-2 flex items-center justify-between gap-2">
                    <span class="text-xs font-semibold text-ink-800">Abteilung {{ idx + 1 }}</span>
                    <button
                      type="button"
                      class="text-xs text-rose-700 hover:underline"
                      @click="requestRemoveSection(idx)"
                    >
                      Entfernen
                    </button>
                  </div>
                  <div class="grid gap-2 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                      <label class="mb-0.5 block text-[11px] font-medium text-ink-600">Schlüssel (z. B. wml)</label>
                      <input
                        v-model="row.key"
                        type="text"
                        class="w-full rounded border border-ink-200 bg-white px-2 py-1.5 font-mono text-xs text-ink-900"
                        placeholder="nur a-z, 0-9, _"
                        autocapitalize="off"
                      />
                    </div>
                    <div class="sm:col-span-2">
                      <label class="mb-0.5 block text-[11px] font-medium text-ink-600">Bezeichnung</label>
                      <input
                        v-model="row.name"
                        type="text"
                        class="w-full rounded border border-ink-200 bg-white px-2 py-1.5 text-sm text-ink-900"
                      />
                    </div>
                    <div>
                      <label class="mb-0.5 block text-[11px] font-medium text-ink-600">Prefix (Klassencode)</label>
                      <input
                        v-model="row.prefix"
                        type="text"
                        class="w-full rounded border border-ink-200 bg-white px-2 py-1.5 text-sm text-ink-900"
                      />
                    </div>
                    <div>
                      <label class="mb-0.5 block text-[11px] font-medium text-ink-600">Ausbildungsjahre</label>
                      <input
                        v-model.number="row.terms"
                        type="number"
                        min="1"
                        max="7"
                        class="w-full rounded border border-ink-200 bg-white px-2 py-1.5 text-sm text-ink-900"
                      />
                    </div>
                    <div>
                      <label class="mb-0.5 block text-[11px] font-medium text-ink-600">Abschlussjahrgang (z. B. 24)</label>
                      <input
                        v-model.number="row.exam_year"
                        type="number"
                        min="0"
                        max="99"
                        class="w-full rounded border border-ink-200 bg-white px-2 py-1.5 text-sm text-ink-900"
                      />
                    </div>
                    <div>
                      <label class="mb-0.5 block text-[11px] font-medium text-ink-600">Anzahl Abschlussklassen</label>
                      <input
                        v-model.number="row.finish_class_count"
                        type="number"
                        min="1"
                        max="26"
                        class="w-full rounded border border-ink-200 bg-white px-2 py-1.5 text-sm text-ink-900"
                      />
                    </div>
                  </div>
                  <p class="mt-2 text-[11px] leading-snug text-ink-500">
                    Vorschau:
                    <span class="font-mono text-ink-700">{{
                      previewClassCodes(row.prefix, row.exam_year, row.finish_class_count)
                    }}</span>
                  </p>
                </div>
              </div>
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
          <p class="text-sm font-semibold text-ink-900">Schuljahr löschen?</p>
          <p class="mt-1 text-xs text-ink-600">„{{ deleteTarget.label }}“ wird entfernt (nur ohne Sessions).</p>
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

    <Teleport to="body">
      <div
        v-if="sectionDeletePending !== null && modalOpen"
        class="fixed inset-0 z-[70] flex items-center justify-center bg-black/40 p-4"
        role="presentation"
        @click.self="cancelSectionRemove"
      >
        <div
          role="dialog"
          aria-modal="true"
          aria-labelledby="sy-section-del-title"
          class="w-full max-w-sm rounded-xl border border-ink-200 bg-white p-4 shadow-lg"
        >
          <p id="sy-section-del-title" class="text-sm font-semibold text-ink-900">Abteilung entfernen?</p>
          <p class="mt-1 text-xs text-ink-600">
            Bist du sicher, dass du die Abteilung <strong>{{ sectionDeleteLabel }}</strong> entfernen willst?
          </p>
          <div class="mt-4 flex justify-end gap-2">
            <button
              type="button"
              class="rounded-lg px-3 py-1.5 text-sm text-ink-700 hover:bg-ink-100"
              @click="cancelSectionRemove"
            >
              Abbrechen
            </button>
            <button
              type="button"
              class="rounded-lg bg-rose-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-rose-700"
              @click="confirmSectionRemove"
            >
              Entfernen
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
