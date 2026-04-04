<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../api'

const loading = ref(true)
const loadError = ref('')
const context = ref(null)

const sectionKey = ref('')
const title = ref('')
const description = ref('')

const authors = ref([
  { first_name: '', last_name: '', class: '', email: '', handy: '' },
])

const submitting = ref(false)
const submitError = ref('')
const successPayload = ref(null)

const selectedSection = computed(() => {
  const sections = context.value?.sections || []
  return sections.find((s) => s.key === sectionKey.value) || null
})

const classOptions = computed(() => selectedSection.value?.class_codes || [])

const canSubmit = computed(() => {
  if (context.value?.phase?.allows_new_submission !== true) {
    return false
  }
  return (context.value?.sections || []).length > 0
})

const phaseHint = computed(() => {
  const p = context.value?.phase
  if (!p) {
    return ''
  }
  if (!p.allows_new_submission) {
    return 'Die Themeneingabe für neue Arbeiten ist derzeit geschlossen.'
  }
  if (context.value?.thesis_session && !(context.value?.sections || []).length) {
    return 'Für dieses Schuljahr sind noch keine Sektionen hinterlegt. Bitte wende dich an die Schule.'
  }
  return ''
})

onMounted(async () => {
  loading.value = true
  loadError.value = ''
  const res = await api.thesisSubmissionContext()
  loading.value = false
  if (!res.ok) {
    loadError.value = 'Kontext konnte nicht geladen werden.'
    return
  }
  context.value = await res.json()
  if (context.value?.message && !context.value?.thesis_session) {
    loadError.value = context.value.message
  }
})

function addAuthor() {
  if (authors.value.length >= 3) {
    return
  }
  authors.value.push({ first_name: '', last_name: '', class: '', email: '', handy: '' })
}

function removeAuthor(index) {
  if (authors.value.length <= 1) {
    return
  }
  authors.value.splice(index, 1)
}

function resetForm() {
  successPayload.value = null
  sectionKey.value = ''
  title.value = ''
  description.value = ''
  authors.value = [{ first_name: '', last_name: '', class: '', email: '', handy: '' }]
  submitError.value = ''
}

async function submit() {
  submitError.value = ''
  if (!canSubmit.value) {
    submitError.value = phaseHint.value || 'Einreichen nicht möglich.'
    return
  }
  if (!context.value?.thesis_session?.id) {
    submitError.value = 'Keine aktive Session.'
    return
  }
  if (!sectionKey.value) {
    submitError.value = 'Bitte eine Sektion wählen.'
    return
  }
  if (!title.value.trim()) {
    submitError.value = 'Bitte einen Titel angeben.'
    return
  }
  if (description.value.trim().length < 10) {
    submitError.value = 'Die Kurzbeschreibung sollte mindestens 10 Zeichen haben.'
    return
  }

  const cleaned = []
  for (let i = 0; i < authors.value.length; i++) {
    const a = authors.value[i]
    const fn = a.first_name.trim()
    const ln = a.last_name.trim()
    const cl = a.class.trim()
    const em = a.email.trim()
    const hy = a.handy.trim()
    if (!fn && !ln && !cl && !em && !hy) {
      continue
    }
    if (!fn || !ln || !cl || !em) {
      submitError.value = `Lernende ${i + 1}: Vorname, Nachname, Klasse und E-Mail sind Pflicht.`
      return
    }
    cleaned.push({
      first_name: fn,
      last_name: ln,
      class: cl,
      email: em,
      handy: hy || undefined,
    })
  }
  if (cleaned.length < 1) {
    submitError.value = 'Mindestens eine vollständige Lernenden-Zeile ausfüllen.'
    return
  }

  submitting.value = true
  const res = await api.submitThesis({
    thesis_session_id: context.value.thesis_session.id,
    section_key: sectionKey.value,
    title: title.value.trim(),
    description: description.value.trim(),
    authors: cleaned,
  })
  submitting.value = false

  if (!res.ok) {
    const data = await res.json().catch(() => ({}))
    const msg =
      data.message ||
      (data.errors && Object.values(data.errors).flat().join(' ')) ||
      'Speichern fehlgeschlagen.'
    submitError.value = typeof msg === 'string' ? msg : 'Speichern fehlgeschlagen.'
    return
  }

  const data = await res.json()
  successPayload.value = data.thesis
}
</script>

<template>
  <div class="min-h-dvh bg-gradient-to-br from-ink-100 via-white to-teal-50/30">
    <div
      class="pointer-events-none fixed inset-0 bg-[radial-gradient(ellipse_70%_40%_at_0%_0%,rgba(20,184,166,0.07),transparent)]"
    />

    <div class="relative mx-auto max-w-xl px-4 py-8 sm:px-6 sm:py-12">
      <header class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-widest text-teal-700">Kompenso</p>
          <h1 class="mt-1 text-2xl font-bold tracking-tight text-ink-900">Thema einreichen</h1>
          <p v-if="context?.thesis_session" class="mt-2 text-sm text-ink-600">
            {{ context.thesis_session.name }}
            <span v-if="context.thesis_session.schoolyear_label" class="text-ink-500">
              · {{ context.thesis_session.schoolyear_label }}
            </span>
          </p>
        </div>
        <RouterLink
          to="/"
          class="shrink-0 rounded-xl border border-ink-200 bg-white px-4 py-2 text-sm font-medium text-ink-700 shadow-sm hover:bg-ink-50"
        >
          Zur Startseite
        </RouterLink>
      </header>

      <p v-if="loading" class="text-sm text-ink-500">Laden …</p>

      <p
        v-else-if="loadError"
        class="rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200"
      >
        {{ loadError }}
      </p>

      <div
        v-else-if="successPayload"
        class="rounded-3xl bg-white/95 p-6 shadow-card ring-1 ring-ink-200/70"
        role="status"
      >
        <h2 class="text-lg font-semibold text-ink-900">Thema eingereicht</h2>
        <p class="mt-2 text-sm text-ink-600">
          Merke dir den Bearbeitungscode. Er erlaubt Änderungen am Thema, solange die Bearbeitungsphase läuft
          (bis kurz nach Ende der Einreichungsphase laut Ausschreibung).
        </p>
        <p
          class="mt-4 rounded-2xl bg-ink-900 px-4 py-3 text-center font-mono text-lg font-semibold tracking-wider text-white"
        >
          {{ successPayload.edit_code }}
        </p>
        <div class="mt-6 flex flex-wrap gap-2">
          <button
            type="button"
            class="rounded-xl border border-ink-200 bg-white px-4 py-2 text-sm font-medium text-ink-800 hover:bg-ink-50"
            @click="resetForm"
          >
            Weiteres Thema
          </button>
          <RouterLink
            to="/"
            class="rounded-xl bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800"
          >
            Fertig
          </RouterLink>
        </div>
      </div>

      <form
        v-else
        class="space-y-6 rounded-3xl bg-white/95 p-5 shadow-card ring-1 ring-ink-200/70 sm:p-6"
        @submit.prevent="submit"
      >
        <p v-if="phaseHint" class="rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-amber-200">
          {{ phaseHint }}
        </p>

        <div>
          <label for="ts-section" class="mb-1.5 block text-sm font-medium text-ink-700">Sektion</label>
          <select
            id="ts-section"
            v-model="sectionKey"
            required
            class="w-full rounded-xl border-0 bg-ink-50 px-4 py-3 text-sm text-ink-900 ring-1 ring-ink-200/80 focus:outline-none focus:ring-2 focus:ring-teal-500/40"
            :disabled="!canSubmit || !(context?.sections?.length)"
          >
            <option disabled value="">Sektion wählen</option>
            <option v-for="s in context?.sections || []" :key="s.key" :value="s.key">
              {{ s.name }}
            </option>
          </select>
        </div>

        <div v-if="sectionKey">
          <label for="ts-title" class="mb-1.5 block text-sm font-medium text-ink-700">
            Titel (Arbeitstitel, max. 100 Zeichen)
          </label>
          <input
            id="ts-title"
            v-model="title"
            type="text"
            maxlength="100"
            required
            class="w-full rounded-xl border-0 bg-ink-50 px-4 py-3 text-sm text-ink-900 ring-1 ring-ink-200/80 focus:outline-none focus:ring-2 focus:ring-teal-500/40"
            :disabled="!canSubmit"
          />
        </div>

        <div v-if="sectionKey">
          <label for="ts-desc" class="mb-1.5 block text-sm font-medium text-ink-700">
            Kurzbeschreibung (konkrete Fragestellung, min. 10 Zeichen)
          </label>
          <textarea
            id="ts-desc"
            v-model="description"
            rows="5"
            required
            minlength="10"
            class="w-full rounded-xl border-0 bg-ink-50 px-4 py-3 text-sm text-ink-900 ring-1 ring-ink-200/80 focus:outline-none focus:ring-2 focus:ring-teal-500/40"
            :disabled="!canSubmit"
          />
        </div>

        <div v-if="sectionKey" class="border-t border-ink-100 pt-4">
          <div class="mb-3 flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-ink-900">Lernende</h2>
            <button
              v-if="authors.length < 3"
              type="button"
              class="text-xs font-medium text-teal-700 hover:underline"
              :disabled="!canSubmit"
              @click="addAuthor"
            >
              + Weitere Person
            </button>
          </div>

          <div v-for="(a, idx) in authors" :key="idx" class="mb-4 rounded-2xl bg-ink-50/80 p-4 ring-1 ring-ink-100">
            <div class="mb-2 flex items-center justify-between">
              <span class="text-xs font-medium uppercase tracking-wide text-ink-500">Person {{ idx + 1 }}</span>
              <button
                v-if="authors.length > 1"
                type="button"
                class="text-xs text-rose-600 hover:underline"
                :disabled="!canSubmit"
                @click="removeAuthor(idx)"
              >
                Entfernen
              </button>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
              <div>
                <label class="mb-1 block text-xs font-medium text-ink-600" :for="'fn-' + idx">Vorname</label>
                <input
                  :id="'fn-' + idx"
                  v-model="a.first_name"
                  type="text"
                  autocomplete="given-name"
                  class="w-full rounded-lg border-0 bg-white px-3 py-2 text-sm ring-1 ring-ink-200 focus:outline-none focus:ring-2 focus:ring-teal-500/30"
                  :disabled="!canSubmit"
                />
              </div>
              <div>
                <label class="mb-1 block text-xs font-medium text-ink-600" :for="'ln-' + idx">Nachname</label>
                <input
                  :id="'ln-' + idx"
                  v-model="a.last_name"
                  type="text"
                  autocomplete="family-name"
                  class="w-full rounded-lg border-0 bg-white px-3 py-2 text-sm ring-1 ring-ink-200 focus:outline-none focus:ring-2 focus:ring-teal-500/30"
                  :disabled="!canSubmit"
                />
              </div>
              <div>
                <label class="mb-1 block text-xs font-medium text-ink-600" :for="'cl-' + idx">Klasse</label>
                <select
                  :id="'cl-' + idx"
                  v-model="a.class"
                  class="w-full rounded-lg border-0 bg-white px-3 py-2 text-sm ring-1 ring-ink-200 focus:outline-none focus:ring-2 focus:ring-teal-500/30"
                  :disabled="!canSubmit || !classOptions.length"
                >
                  <option value="">Klasse wählen</option>
                  <option v-for="c in classOptions" :key="c" :value="c">{{ c }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-xs font-medium text-ink-600" :for="'em-' + idx">E-Mail</label>
                <input
                  :id="'em-' + idx"
                  v-model="a.email"
                  type="email"
                  autocomplete="email"
                  class="w-full rounded-lg border-0 bg-white px-3 py-2 text-sm ring-1 ring-ink-200 focus:outline-none focus:ring-2 focus:ring-teal-500/30"
                  :disabled="!canSubmit"
                />
              </div>
              <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-medium text-ink-600" :for="'hy-' + idx">
                  Handy (optional)
                </label>
                <input
                  :id="'hy-' + idx"
                  v-model="a.handy"
                  type="tel"
                  autocomplete="tel"
                  class="w-full rounded-lg border-0 bg-white px-3 py-2 text-sm ring-1 ring-ink-200 focus:outline-none focus:ring-2 focus:ring-teal-500/30"
                  :disabled="!canSubmit"
                />
              </div>
            </div>
          </div>
        </div>

        <p v-if="submitError" class="text-sm font-medium text-rose-600">{{ submitError }}</p>

        <div class="flex flex-wrap justify-end gap-2 border-t border-ink-100 pt-4">
          <RouterLink
            to="/"
            class="rounded-xl px-4 py-2.5 text-sm font-medium text-ink-600 hover:bg-ink-100"
          >
            Abbrechen
          </RouterLink>
          <button
            type="submit"
            :disabled="submitting || !canSubmit"
            class="rounded-xl bg-gradient-to-r from-teal-600 to-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-md hover:from-teal-500 hover:to-emerald-600 disabled:cursor-not-allowed disabled:opacity-50"
          >
            {{ submitting ? 'Senden …' : 'Thema einreichen' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
