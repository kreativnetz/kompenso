<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
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

const loading = ref(true)
const loadError = ref('')
const board = ref(null)
const teacher = ref(getUser())
const actionError = ref('')
const acting = ref(false)
const expandedTheses = ref({})

const phaseHint = computed(() => {
  const idx = board.value?.phase?.index
  if (idx == null) {
    return ''
  }
  if (idx <= 1) {
    return 'Die Themensliste ist für Lehrpersonen noch nicht freigegeben.'
  }
  if (idx === 2) {
    return 'Lesephase: du kannst Themen einsehen, aber noch nicht eintragen.'
  }
  if (idx === 3) {
    return 'Eintragen und Austragen sind möglich.'
  }
  return 'Zuweisung durch Schulleitung / Administration; Buchungen sind abgeschlossen.'
})

const listModeLabel = computed(() =>
  board.value?.list_mode === 'mine'
    ? 'Nur deine zugewiesenen Arbeiten (abgeschlossene Session)'
    : 'Alle Arbeiten dieser Session',
)

function toggleThesis(id) {
  expandedTheses.value = { ...expandedTheses.value, [id]: !expandedTheses.value[id] }
}

function mySupervisionForType(rows) {
  const tid = teacher.value?.id
  if (tid == null) {
    return null
  }
  return (rows || []).find((s) => s.teacher_id === tid && [1, 2].includes(s.status))
}

function canBookType(thesis, type) {
  if (!board.value?.phase?.can_book) {
    return false
  }
  const key = type === 1 ? 'main' : 'secondary'
  const rows = thesis.supervisions?.[key] || []
  if (rows.some((s) => s.status === 2)) {
    return false
  }
  return !mySupervisionForType(rows)
}

function canWithdrawType(thesis, type) {
  if (!board.value?.phase?.can_book) {
    return false
  }
  const key = type === 1 ? 'main' : 'secondary'
  const rows = thesis.supervisions?.[key] || []
  const mine = mySupervisionForType(rows)
  return mine && [1, 2].includes(mine.status)
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

async function loadBoard() {
  const id = sessionId.value
  if (id == null || Number.isNaN(id)) {
    await router.replace({ name: 'home', query: { board_missing: '1' } })
    return
  }
  loading.value = true
  loadError.value = ''
  const res = await api.thesisSessionTeacherBoard(id)
  loading.value = false
  if (res.status === 403) {
    const err = await res.json().catch(() => ({}))
    loadError.value = err.message || 'Kein Zugriff auf diese Session.'
    board.value = null
    return
  }
  if (!res.ok) {
    loadError.value = 'Die Themensliste konnte nicht geladen werden.'
    board.value = null
    return
  }
  board.value = await res.json()
}

async function book(thesisId, type) {
  actionError.value = ''
  acting.value = true
  const res = await api.bookSupervision(sessionId.value, { thesis_id: thesisId, type })
  acting.value = false
  if (!res.ok) {
    const err = await res.json().catch(() => ({}))
    actionError.value = err.message || err.errors?.type?.[0] || 'Eintragen fehlgeschlagen.'
    return
  }
  await loadBoard()
}

async function withdraw(supervisionId) {
  actionError.value = ''
  acting.value = true
  const res = await api.withdrawSupervision(sessionId.value, supervisionId)
  acting.value = false
  if (!res.ok) {
    const err = await res.json().catch(() => ({}))
    actionError.value = err.message || 'Austragen fehlgeschlagen.'
    return
  }
  await loadBoard()
}

const assignOpen = ref({})
const assignTeacherId = ref({})

function toggleAssign(key) {
  assignOpen.value = { ...assignOpen.value, [key]: !assignOpen.value[key] }
}

async function assignWinner(thesisId, type) {
  const key = `${thesisId}-${type}`
  const tid = Number(assignTeacherId.value[key])
  if (!tid) {
    actionError.value = 'Bitte eine Lehrperson wählen.'
    return
  }
  actionError.value = ''
  acting.value = true
  const res = await api.assignSupervision(sessionId.value, {
    thesis_id: thesisId,
    type,
    teacher_id: tid,
  })
  acting.value = false
  if (!res.ok) {
    const err = await res.json().catch(() => ({}))
    actionError.value = err.message || err.errors?.type?.[0] || 'Zuweisung fehlgeschlagen.'
    return
  }
  assignOpen.value = { ...assignOpen.value, [key]: false }
  await loadBoard()
}

onMounted(async () => {
  const u = await ensureUser()
  if (!u) {
    await router.replace({ name: 'login', query: { redirect: route.fullPath } })
    return
  }
  await loadBoard()
})

watch(
  () => route.query.thesis_session_id,
  async () => {
    if (teacher.value?.id) {
      await loadBoard()
    }
  },
)
</script>

<template>
  <div class="min-h-dvh bg-gradient-to-br from-ink-100 via-white to-emerald-50/30">
    <div
      class="pointer-events-none fixed inset-0 bg-[radial-gradient(ellipse_70%_40%_at_100%_0%,rgba(16,185,129,0.08),transparent)]"
    />

    <div class="relative mx-auto max-w-4xl px-4 py-8 sm:px-6">
      <header class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
          <button
            type="button"
            class="mb-2 text-sm font-medium text-emerald-700 hover:text-emerald-800"
            @click="router.push({ name: 'home' })"
          >
            ← Zurück
          </button>
          <h1 class="text-2xl font-bold tracking-tight text-ink-900">
            {{ board?.thesis_session?.name || 'Themensliste' }}
          </h1>
          <p v-if="board?.thesis_session?.schoolyear_label" class="mt-1 text-sm text-ink-600">
            Schuljahr {{ board.thesis_session.schoolyear_label }}
          </p>
          <p v-if="board" class="mt-2 text-sm text-ink-600">{{ listModeLabel }}</p>
        </div>
        <div v-if="board" class="flex flex-col items-end gap-2">
          <span
            class="inline-flex rounded-full bg-ink-900 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white"
          >
            Phase {{ board.phase.index }}
          </span>
          <p class="max-w-xs text-right text-xs text-ink-600">{{ phaseHint }}</p>
        </div>
      </header>

      <p v-if="loadError" class="mb-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-800 ring-1 ring-rose-200">
        {{ loadError }}
      </p>
      <p
        v-else-if="actionError"
        class="mb-4 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200"
      >
        {{ actionError }}
      </p>

      <p v-if="loading" class="text-center text-sm text-ink-500">Laden …</p>

      <template v-else-if="board">
        <div class="space-y-8">
          <section
            v-for="sec in board.sections"
            :key="sec.key"
            class="overflow-hidden rounded-3xl bg-white/90 shadow-card ring-1 ring-ink-200/60"
          >
            <div class="border-b border-ink-100 bg-ink-50/80 px-5 py-3">
              <h2 class="text-lg font-semibold text-ink-900">{{ sec.name }}</h2>
            </div>
            <div class="divide-y divide-ink-100">
              <div v-for="cl in sec.classes" :key="cl.class_label" class="px-4 py-4 sm:px-5">
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-ink-500">
                  Klasse {{ cl.class_label }}
                </h3>
                <ul class="space-y-4">
                  <li
                    v-for="th in cl.theses"
                    :key="th.id"
                    class="rounded-2xl border border-ink-100 bg-white p-4 shadow-sm"
                  >
                    <div class="flex flex-wrap items-start justify-between gap-2">
                      <div class="min-w-0 flex-1">
                        <p class="font-semibold text-ink-900">{{ th.title }}</p>
                        <button
                          v-if="th.description"
                          type="button"
                          class="mt-1 text-xs font-medium text-emerald-700 hover:text-emerald-800"
                          @click="toggleThesis(th.id)"
                        >
                          {{ expandedTheses[th.id] ? 'Beschreibung ausblenden' : 'Beschreibung anzeigen' }}
                        </button>
                        <p v-if="expandedTheses[th.id] && th.description" class="mt-2 text-sm text-ink-600">
                          {{ th.description }}
                        </p>
                        <ul class="mt-2 text-sm text-ink-700">
                          <li v-for="(a, i) in th.authors" :key="i">
                            {{ a.first_name }} {{ a.last_name }}
                            <span v-if="a.class" class="text-ink-500">({{ a.class }})</span>
                          </li>
                        </ul>
                      </div>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                      <div class="rounded-xl bg-ink-50/80 p-3">
                        <p class="text-xs font-semibold uppercase text-ink-500">Hauptbetreuung</p>
                        <ul class="mt-2 space-y-1 text-sm text-ink-800">
                          <li v-for="s in th.supervisions.main" :key="s.id">
                            {{ s.teacher_name }}
                            <span v-if="s.status === 1" class="text-amber-700"> (beantragt)</span>
                            <span v-else-if="s.status === 2" class="text-emerald-700"> (bestätigt)</span>
                          </li>
                          <li v-if="!th.supervisions.main?.length" class="text-ink-500">—</li>
                        </ul>
                        <div class="mt-3 flex flex-wrap gap-2">
                          <button
                            v-if="canBookType(th, 1)"
                            type="button"
                            :disabled="acting"
                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500 disabled:opacity-50"
                            @click="book(th.id, 1)"
                          >
                            Eintragen
                          </button>
                          <button
                            v-if="canWithdrawType(th, 1)"
                            type="button"
                            :disabled="acting"
                            class="rounded-lg border border-ink-200 bg-white px-3 py-1.5 text-xs font-semibold text-ink-800 hover:bg-ink-50 disabled:opacity-50"
                            @click="withdraw(mySupervisionForType(th.supervisions.main).id)"
                          >
                            Austragen
                          </button>
                          <template v-if="board.phase.can_admin_assign && board.teachers?.length">
                            <button
                              type="button"
                              class="rounded-lg border border-violet-200 bg-violet-50 px-3 py-1.5 text-xs font-semibold text-violet-900 hover:bg-violet-100"
                              @click="toggleAssign(`${th.id}-1`)"
                            >
                              Zuweisen
                            </button>
                          </template>
                        </div>
                        <div
                          v-if="board.phase.can_admin_assign && assignOpen[`${th.id}-1`]"
                          class="mt-2 flex flex-wrap items-end gap-2"
                        >
                          <label class="flex min-w-[12rem] flex-1 flex-col text-xs text-ink-600">
                            Lehrperson
                            <select
                              v-model="assignTeacherId[`${th.id}-1`]"
                              class="mt-1 rounded-lg border border-ink-200 px-2 py-1.5 text-sm"
                            >
                              <option value="">Wählen …</option>
                              <option v-for="t in board.teachers" :key="t.id" :value="t.id">
                                {{ t.full_name }} ({{ t.token }})
                              </option>
                            </select>
                          </label>
                          <button
                            type="button"
                            :disabled="acting"
                            class="rounded-lg bg-violet-700 px-3 py-2 text-xs font-semibold text-white hover:bg-violet-600 disabled:opacity-50"
                            @click="assignWinner(th.id, 1)"
                          >
                            Gewinner setzen
                          </button>
                        </div>
                      </div>

                      <div class="rounded-xl bg-ink-50/80 p-3">
                        <p class="text-xs font-semibold uppercase text-ink-500">Gegenbetreuung</p>
                        <ul class="mt-2 space-y-1 text-sm text-ink-800">
                          <li v-for="s in th.supervisions.secondary" :key="s.id">
                            {{ s.teacher_name }}
                            <span v-if="s.status === 1" class="text-amber-700"> (beantragt)</span>
                            <span v-else-if="s.status === 2" class="text-emerald-700"> (bestätigt)</span>
                          </li>
                          <li v-if="!th.supervisions.secondary?.length" class="text-ink-500">—</li>
                        </ul>
                        <div class="mt-3 flex flex-wrap gap-2">
                          <button
                            v-if="canBookType(th, 2)"
                            type="button"
                            :disabled="acting"
                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500 disabled:opacity-50"
                            @click="book(th.id, 2)"
                          >
                            Eintragen
                          </button>
                          <button
                            v-if="canWithdrawType(th, 2)"
                            type="button"
                            :disabled="acting"
                            class="rounded-lg border border-ink-200 bg-white px-3 py-1.5 text-xs font-semibold text-ink-800 hover:bg-ink-50 disabled:opacity-50"
                            @click="withdraw(mySupervisionForType(th.supervisions.secondary).id)"
                          >
                            Austragen
                          </button>
                          <template v-if="board.phase.can_admin_assign && board.teachers?.length">
                            <button
                              type="button"
                              class="rounded-lg border border-violet-200 bg-violet-50 px-3 py-1.5 text-xs font-semibold text-violet-900 hover:bg-violet-100"
                              @click="toggleAssign(`${th.id}-2`)"
                            >
                              Zuweisen
                            </button>
                          </template>
                        </div>
                        <div
                          v-if="board.phase.can_admin_assign && assignOpen[`${th.id}-2`]"
                          class="mt-2 flex flex-wrap items-end gap-2"
                        >
                          <label class="flex min-w-[12rem] flex-1 flex-col text-xs text-ink-600">
                            Lehrperson
                            <select
                              v-model="assignTeacherId[`${th.id}-2`]"
                              class="mt-1 rounded-lg border border-ink-200 px-2 py-1.5 text-sm"
                            >
                              <option value="">Wählen …</option>
                              <option v-for="t in board.teachers" :key="t.id" :value="t.id">
                                {{ t.full_name }} ({{ t.token }})
                              </option>
                            </select>
                          </label>
                          <button
                            type="button"
                            :disabled="acting"
                            class="rounded-lg bg-violet-700 px-3 py-2 text-xs font-semibold text-white hover:bg-violet-600 disabled:opacity-50"
                            @click="assignWinner(th.id, 2)"
                          >
                            Gewinner setzen
                          </button>
                        </div>
                      </div>
                    </div>
                  </li>
                </ul>
              </div>
            </div>
          </section>
        </div>
      </template>
    </div>
  </div>
</template>
