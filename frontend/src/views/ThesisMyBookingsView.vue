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

const lessonFmt = new Intl.NumberFormat('de-CH', {
  minimumFractionDigits: 0,
  maximumFractionDigits: 2,
})

function formatLessons(n) {
  if (n == null || Number.isNaN(Number(n))) {
    return '—'
  }
  const v = Number(n)
  const num = lessonFmt.format(v)
  if (v === 1) {
    return '1 Lektion'
  }
  return `${num} Lektionen`
}

function authorLabel(a) {
  const name = [a.first_name, a.last_name].filter(Boolean).join(' ').trim()
  const cls = String(a.class ?? '').trim()
  if (!name && !cls) {
    return ''
  }
  return cls ? `${name} (${cls})` : name
}

function authorsLine(card) {
  const list = card.authors || []
  if (!list.length) {
    return '—'
  }
  const parts = list.map(authorLabel).filter(Boolean)
  return parts.length ? parts.join(', ') : '—'
}

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

async function loadBookings() {
  const id = sessionId.value
  if (id == null || Number.isNaN(id)) {
    await router.replace({ name: 'home', query: { board_missing: '1' } })
    return
  }
  loading.value = true
  loadError.value = ''
  const res = await api.thesisSessionMyBookings(id)
  loading.value = false
  if (res.status === 403) {
    const err = await res.json().catch(() => ({}))
    loadError.value = err.message || 'Kein Zugriff auf diese Session.'
    payload.value = null
    return
  }
  if (!res.ok) {
    loadError.value = 'Deine Buchungen konnten nicht geladen werden.'
    payload.value = null
    return
  }
  payload.value = await res.json()
}

onMounted(async () => {
  const u = await ensureUser()
  if (!u) {
    await router.replace({ name: 'login', query: { redirect: route.fullPath } })
    return
  }
  await loadBookings()
})

watch(
  () => route.query.thesis_session_id,
  async () => {
    if (teacher.value?.id) {
      await loadBookings()
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
          <h1 class="text-xl font-bold tracking-tight text-ink-900 sm:text-2xl">Meine Buchungen</h1>
          <p v-if="payload?.thesis_session?.name" class="mt-1 text-sm text-ink-600">
            Schuljahr {{ payload.thesis_session.schoolyear_label }}
            <span v-if="payload.thesis_session.schoolyear_label" class="text-ink-500">
              · {{ payload.thesis_session.name }}
            </span>
          </p>
          <p class="mt-2 text-xs text-ink-500 sm:text-sm">
            Übersicht deiner bestätigten Betreuungen in dieser Session.
          </p>
        </div>
        <RouterLink
          v-if="Object.keys(sessionQuery).length"
          :to="{ name: 'thesis-teacher-board', query: sessionQuery }"
          class="inline-flex items-center rounded-xl border border-emerald-200 bg-white px-4 py-2 text-sm font-semibold text-emerald-800 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50"
        >
          Zur Themensliste
        </RouterLink>
      </header>

      <p v-if="loadError" class="mb-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-800 ring-1 ring-rose-200">
        {{ loadError }}
      </p>

      <p v-if="loading" class="text-center text-sm text-ink-500">Laden …</p>

      <template v-else-if="payload">
        <div v-if="!payload.cards?.length" class="rounded-2xl bg-white/90 px-5 py-8 text-center text-sm text-ink-600 shadow-card ring-1 ring-ink-200/60">
          In dieser Session hast du noch keine bestätigte Betreuung.
        </div>

        <ul v-else class="space-y-4">
          <li
            v-for="card in payload.cards"
            :key="card.thesis_id"
            class="overflow-hidden rounded-2xl bg-white/95 shadow-card ring-1 ring-ink-200/60"
          >
            <div class="border-b border-ink-100 bg-gradient-to-r from-emerald-50/90 to-teal-50/50 px-4 py-3 sm:px-5">
              <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-800/90">
                {{ card.section_name }}
              </p>
              <h2 class="mt-1 text-base font-semibold leading-snug text-ink-900 sm:text-lg">
                {{ card.title }}
              </h2>
            </div>
            <div class="space-y-3 px-4 py-4 sm:px-5">
              <div>
                <p class="text-[10px] font-semibold uppercase tracking-wide text-ink-500">Lernende</p>
                <p class="mt-0.5 text-sm text-ink-800">{{ authorsLine(card) }}</p>
              </div>
              <div
                v-for="(role, ri) in card.roles"
                :key="ri"
                class="rounded-xl border border-ink-100 bg-ink-50/50 px-3 py-2.5"
              >
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                  <span class="text-sm font-semibold text-ink-900">{{ role.role_label }}</span>
                  <span class="text-sm font-medium tabular-nums text-emerald-800">
                    {{ formatLessons(role.compensation_amount) }}
                  </span>
                </div>
                <p v-if="role.other_supervisor?.full_name" class="mt-1 text-xs text-ink-600">
                  {{ role.type === 1 ? 'Gegenbetreuung' : 'Hauptbetreuung' }}:
                  <span class="font-medium text-ink-800">
                    {{ role.other_supervisor.full_name }}
                  </span>
                  <span v-if="role.other_supervisor.token" class="font-mono text-ink-500">
                    ({{ role.other_supervisor.token }})
                  </span>
                </p>
                <p v-else class="mt-1 text-xs text-ink-500">Keine zweite Betreuung eingetragen.</p>
              </div>
            </div>
          </li>
        </ul>

        <footer
          v-if="payload.cards?.length && payload.totals"
          class="mt-8 overflow-hidden rounded-2xl border border-emerald-200/80 bg-emerald-950 px-4 py-5 text-white shadow-lg sm:px-6"
        >
          <p class="text-xs font-semibold uppercase tracking-wider text-emerald-200/90">Summe</p>
          <dl class="mt-4 grid gap-3 sm:grid-cols-2">
            <div>
              <dt class="text-xs text-emerald-200/80">Arbeiten</dt>
              <dd class="text-lg font-semibold tabular-nums">{{ payload.totals.theses_count }}</dd>
            </div>
            <div>
              <dt class="text-xs text-emerald-200/80">Lektionen gesamt</dt>
              <dd class="text-lg font-semibold tabular-nums">{{ formatLessons(payload.totals.compensation_total) }}</dd>
            </div>
            <div>
              <dt class="text-xs text-emerald-200/80">Hauptbetreuungen</dt>
              <dd class="text-base font-medium tabular-nums">{{ payload.totals.main_supervisions }}</dd>
            </div>
            <div>
              <dt class="text-xs text-emerald-200/80">Gegenbetreuungen</dt>
              <dd class="text-base font-medium tabular-nums">{{ payload.totals.secondary_supervisions }}</dd>
            </div>
          </dl>
        </footer>
      </template>
    </div>
  </div>
</template>
