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
const expandedDescId = ref(null)

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
    return 'Eintragen und Austragen sind möglich (je Rolle nur eine Person).'
  }
  return 'Zuweisung durch Schulleitung / Administration; Buchungen sind abgeschlossen.'
})

const listModeLabel = computed(() =>
  board.value?.list_mode === 'mine'
    ? 'Nur deine zugewiesenen Arbeiten (abgeschlossene Session)'
    : 'Alle Arbeiten dieser Session',
)

function authorsShort(th) {
  const list = th.authors || []
  if (!list.length) {
    return '—'
  }
  const names = list.map((a) =>
    [a.last_name, a.first_name].filter(Boolean).join(', ').trim(),
  )
  if (names.length <= 2) {
    return names.join(', ')
  }
  return `${names[0]}, ${names[1]}, …`
}

function toggleDesc(id) {
  expandedDescId.value = expandedDescId.value === id ? null : id
}

function slot(th, type) {
  return type === 1 ? th.main_supervision : th.secondary_supervision
}

function canBookType(th, type) {
  if (!board.value?.phase?.can_book) {
    return false
  }
  return slot(th, type) == null
}

function canWithdrawType(th, type) {
  if (!board.value?.phase?.can_book) {
    return false
  }
  const s = slot(th, type)
  const tid = teacher.value?.id
  return s != null && tid != null && s.teacher_id === tid
}

const assignOpen = ref({})
const assignTeacherId = ref({})

function toggleAssign(key) {
  assignOpen.value = { ...assignOpen.value, [key]: !assignOpen.value[key] }
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

async function submitAssign(thesisId, type, teacherId) {
  actionError.value = ''
  acting.value = true
  const res = await api.assignSupervision(sessionId.value, {
    thesis_id: thesisId,
    type,
    teacher_id: teacherId,
  })
  acting.value = false
  if (!res.ok) {
    const err = await res.json().catch(() => ({}))
    actionError.value = err.message || err.errors?.type?.[0] || 'Zuweisung fehlgeschlagen.'
    return
  }
  const key = `${thesisId}-${type}`
  assignOpen.value = { ...assignOpen.value, [key]: false }
  await loadBoard()
}

async function assignWinner(thesisId, type) {
  const key = `${thesisId}-${type}`
  const raw = assignTeacherId.value[key]
  if (raw === '' || raw == null) {
    actionError.value = 'Bitte eine Lehrperson wählen (oder H∅/G∅ zum Leeren).'
    return
  }
  const tid = Number(raw)
  if (Number.isNaN(tid)) {
    actionError.value = 'Ungültige Auswahl.'
    return
  }
  await submitAssign(thesisId, type, tid)
}

async function clearSlotAdmin(thesisId, type) {
  await submitAssign(thesisId, type, null)
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

    <div class="relative mx-auto max-w-6xl px-3 py-6 sm:px-5">
      <header class="mb-5 flex flex-wrap items-start justify-between gap-4">
        <div>
          <button
            type="button"
            class="mb-1 text-sm font-medium text-emerald-700 hover:text-emerald-800"
            @click="router.push({ name: 'home' })"
          >
            ← Zurück
          </button>
          <h1 class="text-xl font-bold tracking-tight text-ink-900 sm:text-2xl">
            {{ board?.thesis_session?.name || 'Themensliste' }}
          </h1>
          <p v-if="board?.thesis_session?.schoolyear_label" class="mt-0.5 text-sm text-ink-600">
            Schuljahr {{ board.thesis_session.schoolyear_label }}
          </p>
          <p v-if="board" class="mt-1 text-xs text-ink-600 sm:text-sm">{{ listModeLabel }}</p>
        </div>
        <div v-if="board" class="flex flex-col items-end gap-1.5">
          <span
            class="inline-flex rounded-full bg-ink-900 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white sm:text-xs"
          >
            Phase {{ board.phase.index }}
          </span>
          <p class="max-w-[16rem] text-right text-[10px] text-ink-600 sm:text-xs">{{ phaseHint }}</p>
        </div>
      </header>

      <p v-if="loadError" class="mb-3 rounded-2xl bg-rose-50 px-3 py-2 text-sm text-rose-800 ring-1 ring-rose-200">
        {{ loadError }}
      </p>
      <p
        v-else-if="actionError"
        class="mb-3 rounded-2xl bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-amber-200"
      >
        {{ actionError }}
      </p>

      <p v-if="loading" class="text-center text-sm text-ink-500">Laden …</p>

      <template v-else-if="board">
        <div class="space-y-6">
          <section
            v-for="sec in board.sections"
            :key="sec.key"
            class="overflow-hidden rounded-2xl bg-white/90 shadow-card ring-1 ring-ink-200/60"
          >
            <div class="border-b border-ink-100 bg-ink-50/80 px-4 py-2.5 sm:px-5">
              <h2 class="text-base font-semibold text-ink-900 sm:text-lg">{{ sec.name }}</h2>
            </div>
            <div class="divide-y divide-ink-100">
              <div v-for="cl in sec.classes" :key="cl.class_label" class="px-2 py-3 sm:px-4">
                <h3 class="mb-2 px-1 text-[10px] font-semibold uppercase tracking-wider text-ink-500">
                  Klasse {{ cl.class_label }}
                </h3>

                <div
                  class="overflow-x-auto rounded-xl border border-ink-100 bg-ink-50/40 ring-1 ring-ink-100/80"
                >
                  <div class="min-w-[36rem]">
                    <div
                      class="grid grid-cols-[minmax(8rem,1fr)_minmax(5rem,7rem)_2.75rem_2.75rem_minmax(4.5rem,auto)] gap-1 border-b border-ink-200/80 bg-ink-100/60 px-2 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-ink-600 sm:px-3 sm:text-xs"
                    >
                      <div>Thema</div>
                      <div>
                        <span class="hidden sm:inline">Lernende</span>
                        <span class="sm:hidden">L</span>
                      </div>
                      <div class="text-center">H</div>
                      <div class="text-center">G</div>
                      <div class="text-right">Aktion</div>
                    </div>

                    <template v-for="(th, idx) in cl.theses" :key="th.id">
                    <div
                      class="grid grid-cols-[minmax(8rem,1fr)_minmax(5rem,7rem)_2.75rem_2.75rem_minmax(4.5rem,auto)] gap-1 border-b border-ink-100/90 px-2 py-1.5 text-xs sm:px-3 sm:py-2 sm:text-sm"
                      :class="idx % 2 === 1 ? 'bg-white/70' : 'bg-white/40'"
                    >
                      <div class="min-w-0">
                        <p class="truncate font-medium leading-tight text-ink-900" :title="th.title">
                          {{ th.title }}
                        </p>
                        <div class="mt-0.5 flex flex-wrap items-center gap-1">
                          <button
                            v-if="th.description"
                            type="button"
                            class="rounded px-0.5 text-[10px] font-medium text-emerald-700 hover:bg-emerald-50 hover:underline sm:text-xs"
                            @click="toggleDesc(th.id)"
                          >
                            {{ expandedDescId === th.id ? 'Text ▲' : 'Text ▼' }}
                          </button>
                        </div>
                        <p
                          v-if="expandedDescId === th.id && th.description"
                          class="mt-1 max-h-24 overflow-y-auto text-[10px] leading-snug text-ink-600 sm:text-xs"
                        >
                          {{ th.description }}
                        </p>
                      </div>

                      <div class="hidden min-w-0 truncate text-ink-600 sm:block" :title="authorsShort(th)">
                        {{ authorsShort(th) }}
                      </div>
                      <div class="truncate text-[10px] text-ink-600 sm:hidden">
                        {{ (th.authors?.length && th.authors[0].last_name) || '—' }}
                      </div>

                      <div
                        class="flex flex-col items-center justify-center gap-0.5 font-mono text-[11px] font-semibold tracking-tight text-ink-800 sm:text-xs"
                      >
                        <span>{{ slot(th, 1)?.teacher_token || '—' }}</span>
                      </div>
                      <div
                        class="flex flex-col items-center justify-center gap-0.5 font-mono text-[11px] font-semibold tracking-tight text-ink-800 sm:text-xs"
                      >
                        <span>{{ slot(th, 2)?.teacher_token || '—' }}</span>
                      </div>

                      <div class="flex flex-wrap items-center justify-end gap-0.5">
                        <template v-if="board.phase.can_book">
                          <button
                            v-if="canBookType(th, 1)"
                            type="button"
                            :disabled="acting"
                            class="rounded border border-emerald-200 bg-emerald-50 px-1 py-0.5 text-[10px] font-semibold text-emerald-900 hover:bg-emerald-100 disabled:opacity-40 sm:px-1.5"
                            title="Hauptbetreuung"
                            @click="book(th.id, 1)"
                          >
                            +H
                          </button>
                          <button
                            v-if="canWithdrawType(th, 1)"
                            type="button"
                            :disabled="acting"
                            class="rounded border border-ink-200 bg-white px-1 py-0.5 text-[10px] font-semibold text-ink-800 hover:bg-ink-50 disabled:opacity-40 sm:px-1.5"
                            title="Hauptbetreuung austragen"
                            @click="withdraw(slot(th, 1).id)"
                          >
                            −H
                          </button>
                          <button
                            v-if="canBookType(th, 2)"
                            type="button"
                            :disabled="acting"
                            class="rounded border border-emerald-200 bg-emerald-50 px-1 py-0.5 text-[10px] font-semibold text-emerald-900 hover:bg-emerald-100 disabled:opacity-40 sm:px-1.5"
                            title="Gegenbetreuung"
                            @click="book(th.id, 2)"
                          >
                            +G
                          </button>
                          <button
                            v-if="canWithdrawType(th, 2)"
                            type="button"
                            :disabled="acting"
                            class="rounded border border-ink-200 bg-white px-1 py-0.5 text-[10px] font-semibold text-ink-800 hover:bg-ink-50 disabled:opacity-40 sm:px-1.5"
                            title="Gegenbetreuung austragen"
                            @click="withdraw(slot(th, 2).id)"
                          >
                            −G
                          </button>
                        </template>

                        <template v-if="board.phase.can_admin_assign && board.teachers?.length">
                          <button
                            type="button"
                            class="rounded border border-violet-200 bg-violet-50 px-1 py-0.5 text-[10px] font-semibold text-violet-900 hover:bg-violet-100 sm:px-1.5"
                            @click="toggleAssign(`${th.id}-1`)"
                          >
                            H*
                          </button>
                          <button
                            type="button"
                            class="rounded border border-violet-200 bg-violet-50 px-1 py-0.5 text-[10px] font-semibold text-violet-900 hover:bg-violet-100 sm:px-1.5"
                            @click="toggleAssign(`${th.id}-2`)"
                          >
                            G*
                          </button>
                          <button
                            v-if="slot(th, 1)"
                            type="button"
                            class="rounded border border-rose-200 bg-rose-50 px-1 py-0.5 text-[10px] font-semibold text-rose-900 hover:bg-rose-100 sm:px-1.5"
                            :disabled="acting"
                            title="Hauptbetreuung löschen"
                            @click="clearSlotAdmin(th.id, 1)"
                          >
                            H∅
                          </button>
                          <button
                            v-if="slot(th, 2)"
                            type="button"
                            class="rounded border border-rose-200 bg-rose-50 px-1 py-0.5 text-[10px] font-semibold text-rose-900 hover:bg-rose-100 sm:px-1.5"
                            :disabled="acting"
                            title="Gegenbetreuung löschen"
                            @click="clearSlotAdmin(th.id, 2)"
                          >
                            G∅
                          </button>
                        </template>
                      </div>
                    </div>

                    <div
                      v-if="
                        board.phase.can_admin_assign &&
                        (assignOpen[`${th.id}-1`] || assignOpen[`${th.id}-2`])
                      "
                      class="border-b border-ink-100/90 bg-violet-50/50 px-2 py-2 sm:px-3"
                    >
                      <div v-if="assignOpen[`${th.id}-1`]" class="mb-2 flex flex-wrap items-end gap-2">
                        <span class="text-[10px] font-semibold text-violet-900 sm:text-xs">Hauptbetreuung</span>
                        <select
                          v-model="assignTeacherId[`${th.id}-1`]"
                          class="min-w-[10rem] flex-1 rounded-lg border border-ink-200 px-2 py-1 text-xs"
                        >
                          <option value="">Lehrperson wählen …</option>
                          <option v-for="t in board.teachers" :key="t.id" :value="String(t.id)">
                            {{ t.token }} — {{ t.full_name }}
                          </option>
                        </select>
                        <button
                          type="button"
                          :disabled="acting"
                          class="rounded-lg bg-violet-700 px-2 py-1 text-[10px] font-semibold text-white hover:bg-violet-600 disabled:opacity-50 sm:text-xs"
                          @click="assignWinner(th.id, 1)"
                        >
                          Setzen
                        </button>
                      </div>
                      <div v-if="assignOpen[`${th.id}-2`]" class="flex flex-wrap items-end gap-2">
                        <span class="text-[10px] font-semibold text-violet-900 sm:text-xs">Gegenbetreuung</span>
                        <select
                          v-model="assignTeacherId[`${th.id}-2`]"
                          class="min-w-[10rem] flex-1 rounded-lg border border-ink-200 px-2 py-1 text-xs"
                        >
                          <option value="">Lehrperson wählen …</option>
                          <option v-for="t in board.teachers" :key="t.id" :value="String(t.id)">
                            {{ t.token }} — {{ t.full_name }}
                          </option>
                        </select>
                        <button
                          type="button"
                          :disabled="acting"
                          class="rounded-lg bg-violet-700 px-2 py-1 text-[10px] font-semibold text-white hover:bg-violet-600 disabled:opacity-50 sm:text-xs"
                          @click="assignWinner(th.id, 2)"
                        >
                          Setzen
                        </button>
                      </div>
                    </div>
                    </template>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </div>
      </template>
    </div>
  </div>
</template>
