<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { api } from '../api'
import { getUser, setUser } from '../lib/auth'

const route = useRoute()
const router = useRouter()

const sessionId = computed(() => {
  const q = route.query.thesis_session_id
  if (q == null || String(q).trim() === '') {
    return null
  }
  return Number(q)
})

const sessionQuery = computed(() =>
  sessionId.value != null && Number.isFinite(sessionId.value)
    ? { thesis_session_id: String(sessionId.value) }
    : {},
)

const loading = ref(true)
const loadError = ref('')
const payload = ref(null)
const teacher = ref(getUser())

async function ensureUser() {
  let u = getUser()
  if (!u?.id) {
    const res = await api.me()
    if (!res.ok) {
      return null
    }
    const data = await res.json()
    u = data.teacher
    setUser(u)
  }
  teacher.value = u
  return u
}

async function loadOverview() {
  const id = sessionId.value
  if (id == null || Number.isNaN(id)) {
    await router.replace({ name: 'home', query: { board_missing: '1' } })
    return
  }
  loading.value = true
  loadError.value = ''
  const res = await api.thesisSessionTeacherOverview(id)
  loading.value = false
  if (res.status === 403) {
    const err = await res.json().catch(() => ({}))
    loadError.value = err.message || 'Kein Zugriff auf diese Session.'
    payload.value = null
    return
  }
  if (!res.ok) {
    loadError.value = 'Die Lehrpersonenübersicht konnte nicht geladen werden.'
    payload.value = null
    return
  }
  payload.value = await res.json()
}

function rowHighlight(t) {
  const m = t.main_count ?? 0
  const s = t.secondary_count ?? 0
  if (m + s === 0) {
    return 'bg-rose-50/50'
  }
  return ''
}

onMounted(async () => {
  const u = await ensureUser()
  if (!u) {
    await router.replace({ name: 'login', query: { redirect: route.fullPath } })
    return
  }
  await loadOverview()
})

watch(
  () => route.query.thesis_session_id,
  async () => {
    if (teacher.value?.id) {
      await loadOverview()
    }
  },
)
</script>

<template>
  <div class="min-h-dvh bg-gradient-to-br from-ink-100 via-white to-emerald-50/30">
    <div
      class="pointer-events-none fixed inset-0 bg-[radial-gradient(ellipse_70%_40%_at_100%_0%,rgba(16,185,129,0.08),transparent)]"
    />

    <div class="relative mx-auto max-w-3xl px-3 py-6 sm:px-5">
      <header class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
          <button
            type="button"
            class="mb-1 text-sm font-medium text-emerald-700 hover:text-emerald-800"
            @click="router.push({ name: 'home' })"
          >
            ← Zurück
          </button>
          <h1 class="text-xl font-bold tracking-tight text-ink-900 sm:text-2xl">Lehrpersonenübersicht</h1>
          <p v-if="payload?.thesis_session?.name" class="mt-1 text-sm text-ink-600">
            {{ payload.thesis_session.name }}
            <span v-if="payload.thesis_session.schoolyear_label" class="text-ink-500">
              · Schuljahr {{ payload.thesis_session.schoolyear_label }}
            </span>
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <RouterLink
            v-if="Object.keys(sessionQuery).length"
            :to="{ name: 'thesis-teacher-board', query: sessionQuery }"
            class="inline-flex items-center rounded-xl border border-emerald-200 bg-white px-4 py-2 text-sm font-semibold text-emerald-800 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50"
          >
            Zur Themensliste
          </RouterLink>
          <RouterLink
            v-if="Object.keys(sessionQuery).length"
            :to="{ name: 'thesis-supervision-list', query: sessionQuery }"
            class="inline-flex items-center rounded-xl border border-ink-200 bg-white px-4 py-2 text-sm font-semibold text-ink-800 shadow-sm transition hover:border-ink-300 hover:bg-ink-50"
          >
            Betreuungsliste
          </RouterLink>
        </div>
      </header>

      <p v-if="loadError" class="mb-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-800 ring-1 ring-rose-200">
        {{ loadError }}
      </p>

      <p v-if="loading" class="text-center text-sm text-ink-500">Laden …</p>

      <template v-else-if="payload">
        <section
          v-if="payload.summary"
          class="mb-5 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5"
        >
          <div
            class="rounded-xl border border-ink-200/80 bg-white/90 px-3 py-2 shadow-sm ring-1 ring-ink-100"
          >
            <p class="text-[10px] font-semibold uppercase tracking-wide text-ink-500">Arbeiten</p>
            <p class="mt-0.5 text-lg font-bold tabular-nums text-ink-900">
              {{ payload.summary.total_theses }}
            </p>
            <p class="text-[10px] text-ink-500">erfasst</p>
          </div>
          <div
            class="rounded-xl border border-emerald-200/80 bg-emerald-50/60 px-3 py-2 shadow-sm ring-1 ring-emerald-100"
          >
            <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-800/80">Haupt</p>
            <p class="mt-0.5 text-lg font-bold tabular-nums text-emerald-900">
              {{ payload.summary.with_main_supervision }}
            </p>
            <p class="text-[10px] text-emerald-800/70">besetzt</p>
          </div>
          <div
            class="rounded-xl border border-amber-200/80 bg-amber-50/50 px-3 py-2 shadow-sm ring-1 ring-amber-100"
          >
            <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-900/80">Haupt</p>
            <p class="mt-0.5 text-lg font-bold tabular-nums text-amber-950">
              {{ payload.summary.missing_main }}
            </p>
            <p class="text-[10px] text-amber-900/70">fehlend</p>
          </div>
          <div
            class="rounded-xl border border-teal-200/80 bg-teal-50/60 px-3 py-2 shadow-sm ring-1 ring-teal-100"
          >
            <p class="text-[10px] font-semibold uppercase tracking-wide text-teal-900/80">Gegen</p>
            <p class="mt-0.5 text-lg font-bold tabular-nums text-teal-950">
              {{ payload.summary.with_secondary_supervision }}
            </p>
            <p class="text-[10px] text-teal-900/70">besetzt</p>
          </div>
          <div
            class="rounded-xl border border-violet-200/80 bg-violet-50/50 px-3 py-2 shadow-sm ring-1 ring-violet-100"
          >
            <p class="text-[10px] font-semibold uppercase tracking-wide text-violet-900/80">Gegen</p>
            <p class="mt-0.5 text-lg font-bold tabular-nums text-violet-950">
              {{ payload.summary.missing_secondary }}
            </p>
            <p class="text-[10px] text-violet-900/70">fehlend</p>
          </div>
        </section>

        <div class="rounded-2xl bg-white/95 p-3 shadow-card ring-1 ring-ink-200/60 sm:p-4">
          <h2 class="mb-2 text-sm font-semibold text-ink-900">Lehrpersonen</h2>
          <p v-if="!payload.teachers?.length" class="text-xs text-ink-600">Keine aktiven Lehrpersonen.</p>
          <div v-else class="overflow-x-auto rounded-lg border border-ink-200">
            <table class="w-full border-collapse text-left">
              <thead>
                <tr class="border-b border-ink-200 bg-ink-50 text-xs font-semibold text-ink-700">
                  <th class="px-2 py-1">Kürzel</th>
                  <th class="px-2 py-1">Name</th>
                  <th class="px-2 py-1 text-center">HB</th>
                  <th class="px-2 py-1 text-center">GB</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="t in payload.teachers"
                  :key="t.id"
                  class="border-b border-ink-100 last:border-0"
                  :class="rowHighlight(t)"
                >
                  <td class="px-2 py-1 font-mono text-xs text-ink-900">{{ t.token }}</td>
                  <td class="px-2 py-1 text-xs text-ink-800">{{ t.full_name }}</td>
                  <td class="px-2 py-1 text-center text-xs tabular-nums text-ink-900">
                    {{ t.main_count }}
                  </td>
                  <td class="px-2 py-1 text-center text-xs tabular-nums text-ink-900">
                    {{ t.secondary_count }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>
