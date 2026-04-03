<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import { getUser, setUser } from '../lib/auth'

const router = useRouter()

const currentUser = ref(getUser())
const teachers = ref([])
const search = ref('')
const loading = ref(true)
const loadError = ref('')
const updatingId = ref(null)
const toast = ref({ type: '', text: '' })

const STATUS_STYLE = {
  0: 'bg-slate-100 text-slate-800 ring-1 ring-slate-200/80',
  1: 'bg-emerald-50 text-emerald-900 ring-1 ring-emerald-200/90',
  2: 'bg-violet-50 text-violet-900 ring-1 ring-violet-200/90',
  3: 'bg-amber-50 text-amber-950 ring-1 ring-amber-200/90',
  4: 'bg-rose-50 text-rose-950 ring-1 ring-rose-200/90',
}

const STATUS_DOT = {
  0: 'bg-slate-400',
  1: 'bg-emerald-500',
  2: 'bg-violet-500',
  3: 'bg-amber-500',
  4: 'bg-rose-600',
}

const ALL_OPTIONS = [
  { value: 0, label: 'Deaktiviert' },
  { value: 1, label: 'Lehrperson' },
  { value: 2, label: 'Sonderfunktion' },
  { value: 3, label: 'Schulleitung' },
  { value: 4, label: 'Administrator' },
]

const canAssignAdmin = computed(() => currentUser.value?.abilities?.assign_admin === true)

function optionsForRow(row) {
  if (rowLocked(row)) {
    return ALL_OPTIONS.filter((o) => o.value === row.status)
  }
  if (canAssignAdmin.value) {
    return ALL_OPTIONS
  }
  return ALL_OPTIONS.filter((o) => o.value !== 4)
}

function rowLocked(row) {
  if (canAssignAdmin.value) {
    return false
  }
  return row.status === 4
}

function initials(row) {
  const a = (row.first_name || '').trim().charAt(0)
  const b = (row.last_name || '').trim().charAt(0)
  const s = (a + b).toUpperCase()
  return s || '?'
}

const filteredTeachers = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) {
    return teachers.value
  }
  return teachers.value.filter((t) => {
    const hay = [t.full_name, t.last_name, t.first_name, t.token, t.email, t.role]
      .join(' ')
      .toLowerCase()
    return hay.includes(q)
  })
})

let toastTimer
function showToast(type, text) {
  clearTimeout(toastTimer)
  toast.value = { type, text }
  toastTimer = setTimeout(() => {
    toast.value = { type: '', text: '' }
  }, 4200)
}

async function refreshMe() {
  const res = await api.me()
  if (res.ok) {
    const data = await res.json()
    currentUser.value = data.teacher
    setUser(data.teacher)
  }
}

async function loadTeachers() {
  loading.value = true
  loadError.value = ''
  const res = await api.teachers()
  if (!res.ok) {
    if (res.status === 403) {
      await router.replace({ name: 'home' })
      return
    }
    loadError.value = 'Liste konnte nicht geladen werden.'
    loading.value = false
    return
  }
  const data = await res.json()
  teachers.value = data.teachers
  loading.value = false
}

async function onStatusChange(row, event) {
  const next = Number(event.target.value)
  if (next === row.status) {
    return
  }
  updatingId.value = row.id
  const res = await api.updateTeacher(row.id, { status: next })
  updatingId.value = null

  if (!res.ok) {
    const body = await res.json().catch(() => ({}))
    const msg = body.message || 'Speichern fehlgeschlagen.'
    showToast('error', msg)
    event.target.value = String(row.status)
    return
  }

  const data = await res.json()
  const updated = data.teacher
  const i = teachers.value.findIndex((t) => t.id === updated.id)
  if (i !== -1) {
    teachers.value[i] = updated
  }

  if (currentUser.value?.id === updated.id) {
    await refreshMe()
  }

  showToast('success', `${updated.full_name}: Rolle aktualisiert.`)
}

onMounted(async () => {
  await refreshMe()
  await loadTeachers()
})
</script>

<template>
  <div class="min-h-dvh bg-gradient-to-br from-ink-100 via-white to-emerald-50/40">
    <div
      class="pointer-events-none fixed inset-0 bg-[radial-gradient(ellipse_80%_50%_at_50%_-20%,rgba(16,185,129,0.12),transparent)]"
    />

    <header class="relative border-b border-ink-200/60 bg-white/70 backdrop-blur-md">
      <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6">
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
            <h1 class="text-lg font-semibold tracking-tight text-ink-900 sm:text-xl">Lehrpersonen</h1>
            <p class="text-sm text-ink-500">Verwalten, filtern, Rollen zuweisen</p>
          </div>
        </div>
        <div
          v-if="currentUser"
          class="flex items-center gap-3 rounded-2xl bg-white/90 px-4 py-2 shadow-soft ring-1 ring-ink-200/50"
        >
          <div class="text-right text-sm leading-tight">
            <p class="font-medium text-ink-900">{{ currentUser.full_name }}</p>
            <p class="text-ink-500">{{ currentUser.role }}</p>
          </div>
          <div
            class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-emerald-400 to-teal-600 text-sm font-bold text-white shadow-md"
          >
            {{ initials(currentUser) }}
          </div>
        </div>
      </div>
    </header>

    <main class="relative mx-auto max-w-6xl px-4 py-8 sm:px-6">
      <div
        v-if="toast.text"
        role="status"
        class="mb-6 flex items-center gap-3 rounded-2xl px-4 py-3 shadow-card ring-1 transition"
        :class="
          toast.type === 'error'
            ? 'bg-rose-50 text-rose-900 ring-rose-200'
            : 'bg-emerald-50 text-emerald-900 ring-emerald-200'
        "
      >
        <span class="text-lg">{{ toast.type === 'error' ? '⚠' : '✓' }}</span>
        <p class="text-sm font-medium">{{ toast.text }}</p>
      </div>

      <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="max-w-xl flex-1">
          <label for="teacher-search" class="mb-2 block text-sm font-medium text-ink-700">Suche</label>
          <div class="relative">
            <span
              class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-ink-400"
              aria-hidden="true"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                />
              </svg>
            </span>
            <input
              id="teacher-search"
              v-model="search"
              type="search"
              autocomplete="off"
              placeholder="Name, Kürzel, E-Mail …"
              class="w-full rounded-2xl border-0 bg-white py-3.5 pl-12 pr-4 text-ink-900 shadow-soft ring-1 ring-ink-200/80 transition placeholder:text-ink-400 focus:ring-2 focus:ring-emerald-500/30"
            />
          </div>
        </div>
        <p class="text-sm text-ink-500">
          <span class="font-semibold text-ink-700">{{ filteredTeachers.length }}</span>
          von
          <span class="font-semibold text-ink-700">{{ teachers.length }}</span>
          Personen
        </p>
      </div>

      <p
        v-if="currentUser && !canAssignAdmin"
        class="mb-6 rounded-2xl border border-amber-200/80 bg-amber-50/90 px-4 py-3 text-sm leading-relaxed text-amber-950 shadow-sm"
      >
        <strong class="font-semibold">Schulleitung:</strong>
        Sie können keine Administratoren bearbeiten und niemanden zur Rolle „Administrator“ machen.
      </p>

      <p v-if="loadError" class="rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-800 ring-1 ring-rose-200">
        {{ loadError }}
      </p>

      <div
        v-else-if="loading"
        class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
      >
        <div
          v-for="n in 6"
          :key="n"
          class="h-48 animate-pulse rounded-3xl bg-white/60 ring-1 ring-ink-200/50"
        />
      </div>

      <div
        v-else
        class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
      >
        <article
          v-for="row in filteredTeachers"
          :key="row.id"
          class="group relative flex flex-col overflow-hidden rounded-3xl bg-white/90 p-5 shadow-card ring-1 ring-ink-200/60 transition hover:-translate-y-0.5 hover:shadow-lg hover:ring-emerald-200/50"
        >
          <div
            class="pointer-events-none absolute -right-8 -top-8 h-24 w-24 rounded-full bg-gradient-to-br from-emerald-200/30 to-transparent opacity-0 transition group-hover:opacity-100"
          />

          <div class="relative flex items-start gap-4">
            <div
              class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-ink-700 to-ink-900 text-lg font-bold text-white shadow-md"
            >
              {{ initials(row) }}
            </div>
            <div class="min-w-0 flex-1">
              <h2 class="truncate font-semibold text-ink-900">{{ row.full_name }}</h2>
              <p class="mt-0.5 font-mono text-sm text-emerald-700">{{ row.token }}</p>
              <a
                :href="'mailto:' + row.email"
                class="mt-1 block truncate text-sm text-ink-500 underline-offset-2 hover:text-emerald-700 hover:underline"
              >
                {{ row.email }}
              </a>
            </div>
          </div>

          <div class="relative mt-4 flex items-center gap-2">
            <span
              class="inline-flex h-2 w-2 shrink-0 rounded-full shadow-sm"
              :class="STATUS_DOT[row.status] ?? STATUS_DOT[0]"
              aria-hidden="true"
            />
            <span
              class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold tracking-wide"
              :class="STATUS_STYLE[row.status] ?? STATUS_STYLE[0]"
            >
              {{ row.role }}
            </span>
          </div>

          <div class="relative mt-5 flex flex-col gap-2 border-t border-ink-100 pt-4">
            <label class="text-xs font-medium uppercase tracking-wider text-ink-500">Rolle ändern</label>
            <div class="relative">
              <select
                :value="String(row.status)"
                :disabled="updatingId === row.id || rowLocked(row)"
                class="w-full cursor-pointer appearance-none rounded-xl border-0 bg-ink-50 py-3 pl-4 pr-10 text-sm font-medium text-ink-900 ring-1 ring-ink-200/80 transition hover:bg-ink-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 disabled:cursor-not-allowed disabled:opacity-60"
                @change="onStatusChange(row, $event)"
              >
                <option
                  v-for="opt in optionsForRow(row)"
                  :key="opt.value"
                  :value="String(opt.value)"
                >
                  {{ opt.label }}
                </option>
              </select>
              <span
                class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-ink-400"
                aria-hidden="true"
              >
                ▾
              </span>
            </div>
            <p
              v-if="rowLocked(row)"
              class="text-xs leading-relaxed text-amber-800/90"
            >
              Nur ein Administrator kann dieses Profil bearbeiten.
            </p>
          </div>

          <div
            v-if="updatingId === row.id"
            class="absolute inset-0 flex items-center justify-center rounded-3xl bg-white/60 backdrop-blur-[2px]"
          >
            <span class="h-8 w-8 animate-spin rounded-full border-2 border-emerald-500 border-t-transparent" />
          </div>
        </article>
      </div>

      <p
        v-if="!loading && !loadError && filteredTeachers.length === 0"
        class="mt-12 text-center text-ink-500"
      >
        Keine Treffer für „{{ search }}“.
      </p>
    </main>
  </div>
</template>
