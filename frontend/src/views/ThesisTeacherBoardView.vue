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

function authorLabel(a) {
  const name = [a.first_name, a.last_name].filter(Boolean).join(' ').trim()
  const cls = String(a.class ?? '').trim()
  if (!name && !cls) {
    return ''
  }
  return cls ? `${name} (${cls})` : name
}

function authorsShort(th) {
  const list = th.authors || []
  if (!list.length) {
    return '—'
  }
  const names = list.map((a) => authorLabel(a)).filter(Boolean)
  if (!names.length) {
    return '—'
  }
  if (names.length <= 2) {
    return names.join(', ')
  }
  return `${names[0]}, ${names[1]}, …`
}

function thesesInSectionOrder(sec) {
  const rows = []
  for (const cl of sec.classes || []) {
    for (const th of cl.theses || []) {
      rows.push(th)
    }
  }
  return rows
}

function toggleDesc(id) {
  expandedDescId.value = expandedDescId.value === id ? null : id
}

function slot(th, type) {
  return type === 1 ? th.main_supervision : th.secondary_supervision
}

function thesisAllowsSupervision(th) {
  return (th.workflow_status ?? 2) === 2
}

function teacherInOtherSlot(th, type) {
  const tid = teacher.value?.id
  if (tid == null) {
    return false
  }
  const otherType = type === 1 ? 2 : 1
  const s = slot(th, otherType)
  return s != null && s.teacher_id === tid
}

function canBookType(th, type) {
  if (!thesisAllowsSupervision(th)) {
    return false
  }
  if (!board.value?.phase?.can_book) {
    return false
  }
  if (slot(th, type) != null) {
    return false
  }
  if (teacherInOtherSlot(th, type) && !canAdminAssignUI()) {
    return false
  }
  return true
}

function canWithdrawType(th, type) {
  if (!thesisAllowsSupervision(th)) {
    return false
  }
  if (!board.value?.phase?.can_book) {
    return false
  }
  const s = slot(th, type)
  const tid = teacher.value?.id
  return s != null && tid != null && s.teacher_id === tid
}

function isOwnSupervisionSlot(th, type) {
  const tid = teacher.value?.id
  const s = slot(th, type)
  return tid != null && s != null && Number(s.teacher_id) === Number(tid)
}

function canAdminAssignUI() {
  return Boolean(board.value?.phase?.can_admin_assign && board.value?.teachers?.length)
}

const slotTypes = [
  { type: 1, bookLabel: 'eintragen', assignLabel: 'zuteilen', clearLabel: 'löschen', bookTitle: 'Hauptbetreuung eintragen', withdrawTitle: 'Hauptbetreuung austragen', clearTitle: 'Hauptbetreuung löschen' },
  { type: 2, bookLabel: 'eintragen', assignLabel: 'zuteilen', clearLabel: 'löschen', bookTitle: 'Gegenbetreuung eintragen', withdrawTitle: 'Gegenbetreuung austragen', clearTitle: 'Gegenbetreuung löschen' },
]

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

async function setThesisWorkflow(thesisId, newStatus) {
  actionError.value = ''
  acting.value = true
  const res = await api.setThesisWorkflowStatus(sessionId.value, thesisId, { status: newStatus })
  acting.value = false
  if (!res.ok) {
    const err = await res.json().catch(() => ({}))
    actionError.value = err.message || err.errors?.status?.[0] || 'Status konnte nicht gesetzt werden.'
    return
  }
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
            <div class="px-2 py-3 sm:px-4">
              <div
                class="overflow-x-auto rounded-xl border border-ink-100 bg-ink-50/40 ring-1 ring-ink-100/80"
              >
                <div class="min-w-0">
                  <div
                    class="grid grid-cols-12 gap-x-1 gap-y-0 border-b border-ink-200/80 bg-ink-100/60 px-2 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-ink-600 sm:px-3 sm:text-xs"
                  >
                    <div class="col-span-6">Thema</div>
                    <div class="col-span-4">
                      <span class="hidden sm:inline">Lernende</span>
                      <span class="sm:hidden">L</span>
                    </div>
                    <div class="col-span-1 text-center">H</div>
                    <div class="col-span-1 text-center">G</div>
                  </div>

                  <template v-for="(th, idx) in thesesInSectionOrder(sec)" :key="th.id">
                    <div
                      class="border-b border-ink-100/90"
                      :class="idx % 2 === 1 ? 'bg-white/70' : 'bg-white/40'"
                    >
                    <div
                      class="grid grid-cols-12 gap-x-1 gap-y-1 px-2 py-1.5 text-xs sm:px-3 sm:py-2 sm:text-sm"
                    >
                      <div class="col-span-6 min-w-0">
                        <p class="truncate font-medium leading-tight text-ink-900 cursor-pointer" :title="th.title" @click="toggleDesc(th.id)">
                          {{ th.title }}
                        </p>
                        <p
                          v-if="th.workflow_status === 1"
                          class="mt-1 text-[10px] font-medium text-amber-800 sm:text-xs"
                        >
                          Bewilligung durch Rektorat ausstehend — Betreuung noch nicht möglich.
                        </p>
                        <div
                          v-if="th.workflow_status === 1 && canAdminAssignUI()"
                          class="mt-1.5 flex flex-wrap gap-1"
                        >
                          <button
                            type="button"
                            :disabled="acting"
                            class="rounded border border-emerald-300 bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-900 hover:bg-emerald-100 disabled:opacity-40 sm:text-xs"
                            @click="setThesisWorkflow(th.id, 2)"
                          >
                            Bewilligen
                          </button>
                          <button
                            type="button"
                            :disabled="acting"
                            class="rounded border border-rose-300 bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-900 hover:bg-rose-100 disabled:opacity-40 sm:text-xs"
                            @click="setThesisWorkflow(th.id, 0)"
                          >
                            Ablehnen
                          </button>
                        </div>
                      </div>

                      <div class="col-span-4 min-w-0 truncate text-ink-600" :title="authorsShort(th)">
                        {{ authorsShort(th) }}
                      </div>

                      <div
                        v-for="meta in slotTypes"
                        :key="meta.type"
                        class="col-span-1 flex min-w-0 flex-col items-center justify-center gap-1 font-mono text-[11px] font-semibold tracking-tight text-ink-800 sm:text-xs"
                      >
                        <template v-if="slot(th, meta.type)">
                          <button
                            v-if="canWithdrawType(th, meta.type)"
                            type="button"
                            :disabled="acting"
                            class="max-w-full truncate rounded px-0.5 py-0.5 text-center text-[11px] disabled:opacity-40 sm:text-xs"
                            :class="
                              isOwnSupervisionSlot(th, meta.type)
                                ? 'border border-emerald-400 bg-emerald-100 font-bold text-emerald-950 shadow-sm ring-1 ring-emerald-400/70 hover:bg-emerald-200'
                                : 'border border-transparent hover:border-ink-200 hover:bg-ink-50'
                            "
                            :title="meta.withdrawTitle"
                            @click="withdraw(slot(th, meta.type).id)"
                          >
                            {{ slot(th, meta.type).teacher_token }}
                          </button>
                          <span
                            v-else
                            class="max-w-full truncate rounded px-0.5 py-0.5 text-center text-[11px] sm:text-xs"
                            :class="
                              isOwnSupervisionSlot(th, meta.type)
                                ? 'border border-emerald-400 bg-emerald-100 font-bold text-emerald-950 shadow-sm ring-1 ring-emerald-400/70'
                                : ''
                            "
                            :title="
                              isOwnSupervisionSlot(th, meta.type)
                                ? 'Deine Betreuung: ' + slot(th, meta.type).teacher_token
                                : slot(th, meta.type).teacher_token
                            "
                          >
                            {{ slot(th, meta.type).teacher_token }}
                          </span>
                          <button
                            v-if="canAdminAssignUI() && thesisAllowsSupervision(th)"
                            type="button"
                            class="rounded border border-rose-200 bg-rose-50 px-1 py-0.5 text-[9px] font-semibold leading-none text-rose-900 hover:bg-rose-100 sm:text-[10px]"
                            :disabled="acting"
                            :title="meta.clearTitle"
                            @click="clearSlotAdmin(th.id, meta.type)"
                          >
                            {{ meta.clearLabel }}
                          </button>
                        </template>
                        <template v-else>
                          <div class="flex flex-col items-center gap-0.5">
                            <button
                              v-if="canBookType(th, meta.type)"
                              type="button"
                              :disabled="acting"
                              class="rounded border border-emerald-200 bg-emerald-50 px-1 py-0.5 text-[10px] font-semibold text-emerald-900 hover:bg-emerald-100 disabled:opacity-40"
                              :title="meta.bookTitle"
                              @click="book(th.id, meta.type)"
                            >
                              {{ meta.bookLabel }}
                            </button>
                            <button
                              v-if="canAdminAssignUI() && thesisAllowsSupervision(th)"
                              type="button"
                              class="rounded border border-violet-200 bg-violet-50 px-1 py-0.5 text-[9px] font-semibold leading-none text-violet-900 hover:bg-violet-100 sm:text-[10px]"
                              :title="`${meta.assignLabel}: Lehrperson zuweisen`"
                              @click="toggleAssign(`${th.id}-${meta.type}`)"
                            >
                              {{ meta.assignLabel }}
                            </button>
                            <span
                              v-if="!canBookType(th, meta.type) && (!canAdminAssignUI() || !thesisAllowsSupervision(th))"
                              class="text-ink-400"
                            >
                              —
                            </span>
                          </div>
                        </template>
                      </div>
                    </div>

                    <div
                      v-if="expandedDescId === th.id && th.description"
                      class="border-ink-100/80 bg-ink-50/50 px-2 py-2 text-[10px] leading-relaxed break-words whitespace-pre-wrap text-ink-700 sm:px-3 sm:text-xs"
                    >
                      {{ th.description }}
                    </div>

                    <div
                      v-if="
                        board.phase.can_admin_assign &&
                        thesisAllowsSupervision(th) &&
                        (assignOpen[`${th.id}-1`] || assignOpen[`${th.id}-2`])
                      "
                      class="border-t border-ink-100/90 bg-violet-50/50 px-2 py-2 sm:px-3"
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
                    </div>
                  </template>
                </div>
              </div>
            </div>
          </section>
        </div>
      </template>
    </div>
  </div>
</template>
