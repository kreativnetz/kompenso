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

/** 'learners' = eine Zeile pro Lernende/r, Blöcke pro Klasse; sonst eine Zeile pro Thesis */
const sortKey = ref('main')

function authorFullName(a) {
  return [a.first_name, a.last_name].filter(Boolean).join(' ').trim() || '—'
}

function cmpStr(x, y) {
  const vx = (x || '').toLowerCase()
  const vy = (y || '').toLowerCase()
  if (vx < vy) {
    return -1
  }
  if (vx > vy) {
    return 1
  }
  return 0
}

function toggleSort(col) {
  if (col === 2) {
    sortKey.value = 'learners'
  } else if (col === 3) {
    sortKey.value = 'main'
  } else if (col === 4) {
    sortKey.value = 'secondary'
  }
}

const thesisRows = computed(() => {
  const list = payload.value?.items ?? []
  const rows = list.map((it) => ({
    thesisId: it.thesis_id,
    title: it.title,
    authors: it.authors ?? [],
    main: it.main_supervision_token || '',
    secondary: it.secondary_supervision_token || '',
  }))

  if (sortKey.value === 'learners') {
    return rows
  }

  return [...rows].sort((r1, r2) => {
    if (sortKey.value === 'main') {
      let c = cmpStr(r1.main, r2.main)
      if (c !== 0) {
        return c
      }
      c = cmpStr(r1.secondary, r2.secondary)
      if (c !== 0) {
        return c
      }
      return cmpStr(r1.title, r2.title)
    }
    let c = cmpStr(r1.secondary, r2.secondary)
    if (c !== 0) {
      return c
    }
    c = cmpStr(r1.main, r2.main)
    if (c !== 0) {
      return c
    }
    return cmpStr(r1.title, r2.title)
  })
})

const classBlocks = computed(() => {
  if (sortKey.value !== 'learners') {
    return []
  }
  const list = payload.value?.items ?? []
  const byClass = new Map()
  for (const it of list) {
    for (const a of it.authors ?? []) {
      const cls = String(a.class ?? '').trim() || '—'
      if (!byClass.has(cls)) {
        byClass.set(cls, [])
      }
      byClass.get(cls).push({
        thesisId: it.thesis_id,
        title: it.title,
        main: it.main_supervision_token || '',
        secondary: it.secondary_supervision_token || '',
        author: a,
        learnerName: authorFullName(a),
      })
    }
  }

  const classKeys = [...byClass.keys()].sort((a, b) => {
    if (a === '—') {
      return 1
    }
    if (b === '—') {
      return -1
    }
    return a.localeCompare(b, 'de-CH')
  })

  return classKeys.map((cls) => {
    const rows = [...byClass.get(cls)].sort((r1, r2) => {
      let c = cmpStr(r1.learnerName, r2.learnerName)
      if (c !== 0) {
        return c
      }
      c = cmpStr(r1.title, r2.title)
      if (c !== 0) {
        return c
      }
      c = cmpStr(r1.author.last_name, r2.author.last_name)
      if (c !== 0) {
        return c
      }
      return cmpStr(r1.author.first_name, r2.author.first_name)
    })
    return { classLabel: cls, rows }
  })
})

const isLearnerLayout = computed(() => sortKey.value === 'learners')

function printPdf() {
  window.print()
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

async function loadList() {
  const id = sessionId.value
  if (id == null || Number.isNaN(id)) {
    await router.replace({ name: 'home', query: { board_missing: '1' } })
    return
  }
  loading.value = true
  loadError.value = ''
  const res = await api.thesisSessionSupervisionList(id)
  loading.value = false
  if (res.status === 403) {
    const err = await res.json().catch(() => ({}))
    loadError.value = err.message || 'Kein Zugriff auf diese Session.'
    payload.value = null
    return
  }
  if (!res.ok) {
    loadError.value = 'Die Betreuungsliste konnte nicht geladen werden.'
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
  await loadList()
})

watch(
  () => route.query.thesis_session_id,
  async () => {
    if (teacher.value?.id) {
      await loadList()
    }
  },
)
</script>

<template>
  <div class="sl-root min-h-dvh bg-gradient-to-br from-ink-100 via-white to-emerald-50/30">
    <div
      class="pointer-events-none fixed inset-0 bg-[radial-gradient(ellipse_70%_40%_at_100%_0%,rgba(16,185,129,0.08),transparent)]"
    />

    <div class="relative mx-auto max-w-5xl px-3 py-6 sm:px-5">
      <header class="sl-noprint mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
          <button
            type="button"
            class="mb-1 text-sm font-medium text-emerald-700 hover:text-emerald-800"
            @click="router.push({ name: 'home' })"
          >
            ← Zurück
          </button>
          <h1 class="text-xl font-bold tracking-tight text-ink-900 sm:text-2xl">Betreuungsliste</h1>
          <p v-if="payload?.thesis_session?.name" class="mt-1 text-sm text-ink-600">
            {{ payload.thesis_session.name }}
            <span v-if="payload.thesis_session.schoolyear_label" class="text-ink-500">
              · Schuljahr {{ payload.thesis_session.schoolyear_label }}
            </span>
          </p>
          <p class="mt-2 text-xs text-ink-500 sm:text-sm">
            <template v-if="isLearnerLayout">Darstellung: nach Klassen, eine Zeile pro Lernende/r.</template>
            <template v-else>Darstellung: eine Zeile pro Arbeit.</template>
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
          <button
            v-if="payload?.items?.length"
            type="button"
            class="inline-flex items-center rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600"
            @click="printPdf"
          >
            PDF / Drucken
          </button>
        </div>
      </header>

      <p v-if="loadError" class="sl-noprint mb-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-800 ring-1 ring-rose-200">
        {{ loadError }}
      </p>

      <p v-if="loading" class="sl-noprint text-center text-sm text-ink-500">Laden …</p>

      <div v-else-if="payload" class="sl-print-area rounded-2xl bg-white/95 p-4 shadow-card ring-1 ring-ink-200/60 sm:p-6">
        <h2 class="sl-print-title mb-4 text-lg font-semibold text-ink-900">
          Betreuungsliste
          <template v-if="payload.thesis_session?.name"> – {{ payload.thesis_session.name }}</template>
          <template v-if="payload.thesis_session?.schoolyear_label">
            ({{ payload.thesis_session.schoolyear_label }})
          </template>
        </h2>

        <p v-if="!payload.items?.length" class="text-sm text-ink-600">Keine Arbeiten in dieser Session.</p>

        <template v-else-if="!isLearnerLayout">
          <div class="sl-noprint mb-3 flex flex-wrap gap-2 text-xs text-ink-600">
            <span>Spaltenköpfe 2–4 sortieren; Spalte 2 schaltet auf Ansicht pro Klasse.</span>
          </div>
          <div class="overflow-x-auto rounded-xl border border-ink-200">
            <table class="sl-table sl-table-fixed w-full border-collapse text-left text-sm">
              <colgroup>
                <col class="sl-col-6" />
                <col class="sl-col-4" />
                <col class="sl-col-1" />
                <col class="sl-col-1" />
              </colgroup>
              <thead>
                <tr class="border-b border-ink-200 bg-ink-50">
                  <th class="sl-th border border-ink-200 px-3 py-2 font-semibold text-ink-800">
                    Titel der Arbeit
                  </th>
                  <th
                    class="sl-th border border-ink-200 px-3 py-2 font-semibold text-ink-800 cursor-pointer select-none hover:bg-ink-100/80"
                    @click="toggleSort(2)"
                  >
                    Lernende
                  </th>
                  <th
                    class="sl-th border border-ink-200 px-3 py-2 font-semibold text-ink-800 cursor-pointer select-none hover:bg-ink-100/80"
                    @click="toggleSort(3)"
                  >
                    HB
                  </th>
                  <th
                    class="sl-th border border-ink-200 px-3 py-2 font-semibold text-ink-800 cursor-pointer select-none hover:bg-ink-100/80"
                    @click="toggleSort(4)"
                  >
                    GB
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in thesisRows" :key="row.thesisId" class="sl-tr border-b border-ink-100 odd:bg-white even:bg-ink-50/40">
                  <td class="border border-ink-200 px-3 py-2 align-top text-ink-900">{{ row.title }}</td>
                  <td class="border border-ink-200 px-3 py-2 align-top text-ink-800">
                    <template v-for="(a, ai) in row.authors" :key="ai">
                      <br v-if="ai > 0" />
                      {{ authorFullName(a) }}
                    </template>
                    <template v-if="!row.authors?.length">—</template>
                  </td>
                  <td class="border border-ink-200 px-3 py-2 align-top font-mono text-sm text-ink-800">
                    {{ row.main || '—' }}
                  </td>
                  <td class="border border-ink-200 px-3 py-2 align-top font-mono text-sm text-ink-800">
                    {{ row.secondary || '—' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>

        <template v-else>
          <div class="sl-noprint mb-3 text-xs text-ink-600">
            Sortierung nach Lernende/r innerhalb jeder Klasse; Spalte 3 oder 4 wechselt zurück zur Ansicht pro Arbeit.
          </div>
          <section
            v-for="(block, bi) in classBlocks"
            :key="block.classLabel"
            class="sl-class-block mb-8"
            :class="{ 'sl-class-break': bi > 0 }"
          >
            <h3 class="sl-class-title mb-2 border-b-2 border-ink-800 pb-1 text-base font-semibold text-ink-900">
              Klasse {{ block.classLabel }}
            </h3>
            <div class="overflow-x-auto rounded-xl border border-ink-200">
              <table class="sl-table sl-table-fixed w-full border-collapse text-left text-sm">
                <colgroup>
                  <col class="sl-col-6" />
                  <col class="sl-col-4" />
                  <col class="sl-col-1" />
                  <col class="sl-col-1" />
                </colgroup>
                <thead>
                  <tr class="border-b border-ink-200 bg-ink-50">
                    <th class="border border-ink-200 px-3 py-2 font-semibold text-ink-800">Titel der Arbeit</th>
                    <th
                      class="border border-ink-200 px-3 py-2 font-semibold text-ink-800 cursor-pointer select-none hover:bg-ink-100/80"
                      @click="toggleSort(2)"
                    >
                      Lernende
                    </th>
                    <th
                      class="border border-ink-200 px-3 py-2 font-semibold text-ink-800 cursor-pointer select-none hover:bg-ink-100/80"
                      @click="toggleSort(3)"
                    >
                      HB
                    </th>
                    <th
                      class="border border-ink-200 px-3 py-2 font-semibold text-ink-800 cursor-pointer select-none hover:bg-ink-100/80"
                      @click="toggleSort(4)"
                    >
                      GB
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(r, ri) in block.rows"
                    :key="`${r.thesisId}-${r.learnerName}-${ri}`"
                    class="sl-tr border-b border-ink-100 odd:bg-white even:bg-ink-50/40"
                  >
                    <td class="border border-ink-200 px-3 py-2 align-top text-ink-900">{{ r.title }}</td>
                    <td class="border border-ink-200 px-3 py-2 align-top text-ink-800">{{ r.learnerName }}</td>
                    <td class="border border-ink-200 px-3 py-2 align-top font-mono text-sm text-ink-800">
                      {{ r.main || '—' }}
                    </td>
                    <td class="border border-ink-200 px-3 py-2 align-top font-mono text-sm text-ink-800">
                      {{ r.secondary || '—' }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* 12er-Raster: Titel 6, Lernende 4, HB 1, GB 1 */
.sl-table-fixed {
  table-layout: fixed;
}
.sl-col-6 {
  width: 50%;
}
.sl-col-4 {
  width: 33.333333%;
}
.sl-col-1 {
  width: 8.333333%;
}
</style>

<style>
@media print {
  body {
    background: #fff !important;
  }

  .sl-noprint {
    display: none !important;
  }

  .sl-root {
    background: #fff !important;
    min-height: auto !important;
  }

  .sl-root > div.pointer-events-none {
    display: none !important;
  }

  .sl-print-area {
    box-shadow: none !important;
    border: none !important;
    padding: 0 !important;
    max-width: 100% !important;
  }

  .sl-print-title {
    font-size: 14pt;
    margin-bottom: 12pt;
  }

  .sl-table {
    font-size: 9.5pt;
  }

  .sl-th,
  .sl-table td {
    padding: 8pt 10pt !important;
  }

  .sl-tr {
    break-inside: avoid;
    page-break-inside: avoid;
  }

  .sl-class-block {
    break-inside: avoid;
  }

  .sl-class-break {
    break-before: page;
    page-break-before: always;
  }

  .sl-class-title {
    break-after: avoid;
    page-break-after: avoid;
  }
}
</style>
