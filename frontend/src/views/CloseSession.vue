<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api } from '../api'
import { getUser, setUser } from '../lib/auth'

const route = useRoute()

const sessionId = computed(() => {
  const q = route.query.thesis_session_id
  if (q == null || String(q).trim() === '') {
    return null
  }
  const n = Number(q)
  return Number.isFinite(n) ? n : null
})

const loading = ref(true)
const loadError = ref('')
const sessionLabel = ref('')
const closedAt = ref(null)
const closing = ref(false)
const copying = ref(false)
const currentUser = ref(getUser())

let toastTimer
const toast = ref({ type: '', text: '' })

function showToast(type, text) {
  clearTimeout(toastTimer)
  toast.value = { type, text }
  toastTimer = setTimeout(() => {
    toast.value = { type: '', text: '' }
  }, 4500)
}

async function refreshMe() {
  const res = await api.me()
  if (res.ok) {
    const data = await res.json()
    currentUser.value = data.teacher
    setUser(data.teacher)
  }
}

async function loadSessionMeta() {
  if (sessionId.value == null) {
    loading.value = false
    loadError.value = 'Keine oder ungültige Session-ID.'
    sessionLabel.value = ''
    closedAt.value = null
    return
  }
  loading.value = true
  loadError.value = ''
  const res = await api.thesisSessions()
  loading.value = false
  if (!res.ok) {
    loadError.value = 'Sessions konnten nicht geladen werden.'
    return
  }
  const data = await res.json()
  const row = (data.thesis_sessions ?? []).find((s) => s.id === sessionId.value)
  if (!row) {
    loadError.value = 'Diese Zuordnungssession wurde nicht gefunden.'
    return
  }
  sessionLabel.value = [row.schoolyear?.label, row.name].filter(Boolean).join(' · ') || row.name || '—'
  closedAt.value = row.closed_at || null
}

async function closeCycle() {
  if (sessionId.value == null || closing.value) {
    return
  }
  closing.value = true
  const res = await api.thesisSessionClose(sessionId.value)
  closing.value = false
  if (!res.ok) {
    const err = await res.json().catch(() => ({}))
    const msg =
      err.message ||
      (err.errors && Object.values(err.errors).flat().join(' ')) ||
      'Schliessen fehlgeschlagen.'
    showToast('error', msg)
    return
  }
  const data = await res.json()
  const ts = data.thesis_session
  closedAt.value = ts?.closed_at || new Date().toISOString()
  showToast('success', 'Zyklus ist geschlossen. Änderungen durch Lernende und Lehrpersonen sind damit gesperrt (wie in den Phasen vorgesehen).')
}

async function copyExcel() {
  if (sessionId.value == null || copying.value) {
    return
  }
  copying.value = true
  const res = await api.thesisSessionExcelExport(sessionId.value)
  copying.value = false
  if (!res.ok) {
    showToast('error', 'Excel-Daten konnten nicht geladen werden.')
    return
  }
  const text = await res.text()
  try {
    await navigator.clipboard.writeText(text)
    showToast('success', 'TSV in die Zwischenablage kopiert — in Excel einfügen (Strg+V / ⌘V).')
  } catch {
    showToast('error', 'Zwischenablage nicht verfügbar (HTTPS erforderlich oder Berechtigung fehlt).')
  }
}

function initials(u) {
  const a = (u?.first_name || '').trim().charAt(0)
  const b = (u?.last_name || '').trim().charAt(0)
  return (a + b).toUpperCase() || '?'
}

onMounted(async () => {
  await refreshMe()
  await loadSessionMeta()
})

watch(
  () => route.query.thesis_session_id,
  () => {
    loadSessionMeta()
  },
)
</script>

<template>
  <div class="min-h-dvh bg-ink-50">
    <header class="sticky top-0 z-20 border-b border-ink-200 bg-white/95 backdrop-blur">
      <div class="mx-auto flex max-w-2xl flex-wrap items-center justify-between gap-2 px-3 py-2 sm:px-4">
        <div class="flex min-w-0 flex-wrap items-center gap-2">
          <RouterLink
            to="/zuordnungssessions"
            class="shrink-0 rounded-lg px-2 py-1 text-sm text-ink-600 hover:bg-ink-100 hover:text-ink-900"
          >
            ← Sessions
          </RouterLink>
          <h1 class="truncate text-base font-semibold text-ink-900">Zyklus abschliessen</h1>
        </div>
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
        <div class="h-32 animate-pulse rounded-lg bg-ink-200/60" />
      </div>

      <article v-else class="rounded-lg border border-ink-200 bg-white p-4 shadow-sm sm:p-5">
        <p class="text-[11px] font-medium uppercase tracking-wide text-ink-500">Zuordnungssession</p>
        <h2 class="mt-0.5 text-lg font-semibold text-ink-900">{{ sessionLabel }}</h2>

        <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-700">
          <p>
            <strong class="text-ink-900">Schreibschutz:</strong>
            Wenn du den Zyklus abschliesst, wird <code class="rounded bg-ink-100 px-1 font-mono text-xs">closed_at</code>
            auf jetzt gesetzt. Danach gelten die üblichen Sperren (keine Änderungen mehr durch Lernende, LP und Manager an
            Arbeiten/Betreuungen — wie in der Session-Logik vorgesehen).
          </p>
          <p>
            <strong class="text-ink-900">Excel-Export:</strong>
            Der Button kopiert dieselbe tabulatorgetrennte Tabelle wie früher in <code class="text-xs">excel.php</code>:
            eine Zeile pro Lernende/r, Spalten u. a. Schuljahr, Thesis, Betreuungs-Tokens und Entschädigungsbeträge nach
            der in der Session hinterlegten Tabelle. In Excel einfügen, um weiterzuverarbeiten.
          </p>
        </div>

        <p
          v-if="closedAt"
          class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950"
        >
          Diese Session ist bereits geschlossen ({{ closedAt.replace('T', ' ').slice(0, 16) }}). Du kannst trotzdem
          erneut Excel-Daten kopieren. Zum Wiederöffnen das Datum unter
          <RouterLink to="/zuordnungssessions" class="font-semibold underline underline-offset-2">Zuordnungssessions</RouterLink>
          entfernen.
        </p>

        <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
          <button
            type="button"
            class="rounded-lg bg-ink-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-black disabled:opacity-50"
            :disabled="closing || sessionId == null"
            @click="closeCycle"
          >
            {{ closing ? '…' : 'Zyklus abschliessen' }}
          </button>
          <button
            type="button"
            class="rounded-lg border border-ink-300 bg-white px-4 py-2.5 text-sm font-semibold text-ink-900 hover:bg-ink-50 disabled:opacity-50"
            :disabled="copying || sessionId == null"
            @click="copyExcel"
          >
            {{ copying ? '…' : 'Exceldaten kopieren' }}
          </button>
        </div>
      </article>
    </main>
  </div>
</template>
