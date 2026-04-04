<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Thesis;
use App\Models\ThesisSession;
use App\Services\ThesisSessionPhase;
use App\Support\SectionClassCodes;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicThesisSubmissionController extends Controller
{
    /**
     * Phasenlogik (abgestimmt mit {@see \App\Services\ThesisSessionPhase} / Stichtage phase_1_at … phase_5_at):
     *
     * - Ab phase_1_at bis vor phase_4_at: neue Themen einreichen (allowsNew; Phasenindex 1–3).
     * - Ab phase_1_at bis vor phase_4_at: bestehende Arbeiten per Bearbeitungscode ändern (allowsEdit; gleiches Fenster wie Phasenindex 1–3).
     * - Ab phase_4_at: keine öffentlichen Neueinreichungen mehr.
     *
     * Die öffentliche Themeneingabe ist nur verfügbar, wenn für eine Session das Einschreibefenster
     * für neue Arbeiten aktiv ist (allowsNew). Es wird keine Session ohne offenes Fenster ausgewählt.
     *
     * Autorenzahl / Bewilligung: thesis_sessions.section_author_rules (pro Abteilung, Spalten 1–3 Autoren).
     * Thesis-Status nach Speicherung: 1 = bewilligungspflichtig, 2 = aktiv (siehe Model Thesis).
     */
    public function context(Request $request)
    {
        $session = $this->resolveSession($request);
        if ($session === null) {
            return response()->json([
                'thesis_session' => null,
                'sections' => [],
                'section_author_rules' => [],
                'phase' => [
                    'allows_new_submission' => false,
                    'allows_edit_by_code' => false,
                    'phase_index' => 0,
                ],
                'message' => 'Aktuell ist kein Einschreibefenster für die Themeneingabe aktiv.',
            ]);
        }

        $session->loadMissing('schoolyear');
        $now = Carbon::now();
        $phase = $this->phaseFlags($session, $now);
        $sections = $this->sectionsPayload($session);

        return response()->json([
            'thesis_session' => [
                'id' => $session->id,
                'name' => $session->name,
                'schoolyear_label' => $session->schoolyear?->label,
            ],
            'sections' => $sections,
            'section_author_rules' => is_array($session->section_author_rules) ? $session->section_author_rules : [],
            'phase' => [
                'allows_new_submission' => $phase['allowsNew'],
                'allows_edit_by_code' => $phase['allowsEdit'],
                'phase_index' => ThesisSessionPhase::currentPhaseIndex($session, $now),
                'phase_2_at' => $session->phase_2_at?->toIso8601String(),
                'phase_3_at' => $session->phase_3_at?->toIso8601String(),
            ],
            'message' => null,
        ]);
    }

    public function store(Request $request)
    {
        $session = $this->resolveSession($request);
        if ($session === null) {
            throw ValidationException::withMessages([
                'thesis_session' => ['Keine gültige Zuordnungssession oder kein aktives Einschreibefenster.'],
            ]);
        }

        $now = Carbon::now();
        $phase = $this->phaseFlags($session, $now);
        if (! $phase['allowsNew']) {
            throw ValidationException::withMessages([
                'phase' => ['Die Themeneingabe ist für neue Arbeiten derzeit geschlossen.'],
            ]);
        }

        $session->loadMissing('schoolyear');
        $sectionsRaw = $session->schoolyear?->sections;
        if (! is_array($sectionsRaw) || $sectionsRaw === []) {
            throw ValidationException::withMessages([
                'section_key' => ['Für dieses Schuljahr sind keine Abteilungen konfiguriert.'],
            ]);
        }

        $validated = $request->validate([
            'thesis_session_id' => ['sometimes', 'integer'],
            'section_key' => ['required', 'string', 'max:32'],
            'title' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'min:10'],
            'authors' => ['required', 'array', 'min:1', 'max:3'],
            'authors.*.first_name' => ['required', 'string', 'max:50'],
            'authors.*.last_name' => ['required', 'string', 'max:50'],
            'authors.*.class' => ['required', 'string', 'max:16'],
            'authors.*.email' => ['required', 'string', 'email', 'max:50'],
            'authors.*.handy' => ['nullable', 'string', 'max:20'],
        ]);

        $key = strtolower(trim($validated['section_key']));

        if (! $this->isSectionAllowedForSubmission($session, $key)) {
            throw ValidationException::withMessages([
                'section_key' => ['Diese Abteilung ist für diese Session nicht freigegeben.'],
            ]);
        }

        $sectionDef = null;
        foreach ($sectionsRaw as $rawK => $def) {
            if (! is_array($def)) {
                continue;
            }
            if (strtolower((string) $rawK) === $key) {
                $sectionDef = $def;
                break;
            }
        }
        if ($sectionDef === null) {
            throw ValidationException::withMessages([
                'section_key' => ['Ungültige Abteilung.'],
            ]);
        }

        $allowedClasses = SectionClassCodes::forSection($sectionDef);
        $allowedSet = array_fill_keys($allowedClasses, true);

        foreach ($validated['authors'] as $i => $author) {
            $class = (string) ($author['class'] ?? '');
            if (! isset($allowedSet[$class])) {
                throw ValidationException::withMessages([
                    "authors.$i.class" => ['Ungültige Klasse für diese Abteilung.'],
                ]);
            }
        }

        $authorCount = count($validated['authors']);
        $thesisStatus = $this->thesisInitialStatusFromAuthorRules($session, $key, $authorCount);

        $password = $this->uniqueEditCode();

        $thesis = DB::transaction(function () use ($validated, $session, $password, $key, $thesisStatus) {
            $t = Thesis::create([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'type' => 1,
                'password' => $password,
                'session' => $session->id,
                'status' => $thesisStatus,
                'section' => $key,
            ]);

            foreach ($validated['authors'] as $author) {
                Author::create([
                    'first_name' => $author['first_name'],
                    'last_name' => $author['last_name'],
                    'class' => $author['class'],
                    'thesis' => $t->id,
                    'email' => $author['email'],
                    'handy' => trim((string) ($author['handy'] ?? '')) ?: '',
                    'status' => 1,
                ]);
            }

            return $t;
        });

        return response()->json([
            'thesis' => [
                'id' => $thesis->id,
                'edit_code' => $password,
                'requires_rector_approval' => (int) $thesis->status === 1,
            ],
        ], 201);
    }

    /**
     * Lädt eine Arbeit für die Bearbeitung per Code (nur wenn allowsEdit für die Session gilt und Code zur Session passt).
     */
    public function thesisForEdit(Request $request)
    {
        $request->validate([
            'edit_code' => ['required', 'string', 'max:32'],
            'thesis_session_id' => ['required', 'integer'],
        ]);

        $code = trim((string) $request->query('edit_code'));
        $sessionId = (int) $request->query('thesis_session_id');
        $now = Carbon::now();

        $session = ThesisSession::query()->with('schoolyear')->whereNotNull('schoolyear_id')->find($sessionId);
        if ($session === null) {
            return response()->json(['message' => 'Unbekannte Zuordnungssession.'], 404);
        }

        if (! $this->phaseFlags($session, $now)['allowsEdit']) {
            return response()->json(['message' => 'Bearbeiten mit Code ist derzeit nicht möglich.'], 403);
        }

        $thesis = Thesis::query()
            ->where('password', $code)
            ->where('session', $session->id)
            ->with('authors')
            ->first();

        if ($thesis === null) {
            return response()->json([
                'message' => 'Dieser Bearbeitungscode passt nicht zur aktuellen Ausschreibung oder ist unbekannt.',
            ], 404);
        }

        if ((int) $thesis->status === 0) {
            return response()->json(['message' => 'Diese Arbeit kann nicht mehr bearbeitet werden.'], 403);
        }

        $phase = $this->phaseFlags($session, $now);

        return response()->json([
            'thesis_session' => [
                'id' => $session->id,
                'name' => $session->name,
                'schoolyear_label' => $session->schoolyear?->label,
            ],
            'sections' => $this->sectionsPayload($session),
            'section_author_rules' => is_array($session->section_author_rules) ? $session->section_author_rules : [],
            'phase' => [
                'allows_new_submission' => $phase['allowsNew'],
                'allows_edit_by_code' => $phase['allowsEdit'],
                'phase_index' => ThesisSessionPhase::currentPhaseIndex($session, $now),
            ],
            'thesis' => [
                'id' => $thesis->id,
                'title' => $thesis->title,
                'description' => $thesis->description,
                'section_key' => strtolower((string) $thesis->section),
                'edit_code' => $thesis->password,
            ],
            'authors' => $thesis->authors->map(fn (Author $a) => [
                'first_name' => $a->first_name,
                'last_name' => $a->last_name,
                'class' => $a->class,
                'email' => $a->email,
                'handy' => $a->handy ?? '',
            ])->values()->all(),
        ]);
    }

    /**
     * Aktualisiert eine Arbeit per Bearbeitungscode (gleiche Validierung wie bei Neuanmeldung).
     */
    public function updateByCode(Request $request)
    {
        $validated = $request->validate([
            'edit_code' => ['required', 'string', 'max:32'],
            'thesis_session_id' => ['required', 'integer'],
            'section_key' => ['required', 'string', 'max:32'],
            'title' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'min:10'],
            'authors' => ['required', 'array', 'min:1', 'max:3'],
            'authors.*.first_name' => ['required', 'string', 'max:50'],
            'authors.*.last_name' => ['required', 'string', 'max:50'],
            'authors.*.class' => ['required', 'string', 'max:16'],
            'authors.*.email' => ['required', 'string', 'email', 'max:50'],
            'authors.*.handy' => ['nullable', 'string', 'max:20'],
        ]);

        $code = trim($validated['edit_code']);
        $sessionId = (int) $validated['thesis_session_id'];
        $now = Carbon::now();

        $session = ThesisSession::query()->with('schoolyear')->whereNotNull('schoolyear_id')->find($sessionId);
        if ($session === null) {
            throw ValidationException::withMessages([
                'thesis_session_id' => ['Unbekannte Zuordnungssession.'],
            ]);
        }

        if (! $this->phaseFlags($session, $now)['allowsEdit']) {
            throw ValidationException::withMessages([
                'phase' => ['Bearbeiten mit Code ist derzeit nicht möglich.'],
            ]);
        }

        $thesis = Thesis::query()
            ->where('password', $code)
            ->where('session', $session->id)
            ->first();

        if ($thesis === null) {
            throw ValidationException::withMessages([
                'edit_code' => ['Bearbeitungscode ungültig oder passt nicht zu dieser Session.'],
            ]);
        }

        if ((int) $thesis->status === 0) {
            throw ValidationException::withMessages([
                'edit_code' => ['Diese Arbeit kann nicht mehr bearbeitet werden.'],
            ]);
        }

        $session->loadMissing('schoolyear');
        $sectionsRaw = $session->schoolyear?->sections;
        if (! is_array($sectionsRaw) || $sectionsRaw === []) {
            throw ValidationException::withMessages([
                'section_key' => ['Für dieses Schuljahr sind keine Abteilungen konfiguriert.'],
            ]);
        }

        $key = strtolower(trim($validated['section_key']));

        if (! $this->isSectionAllowedForSubmission($session, $key)) {
            throw ValidationException::withMessages([
                'section_key' => ['Diese Abteilung ist für diese Session nicht freigegeben.'],
            ]);
        }

        $sectionDef = null;
        foreach ($sectionsRaw as $rawK => $def) {
            if (! is_array($def)) {
                continue;
            }
            if (strtolower((string) $rawK) === $key) {
                $sectionDef = $def;
                break;
            }
        }
        if ($sectionDef === null) {
            throw ValidationException::withMessages([
                'section_key' => ['Ungültige Abteilung.'],
            ]);
        }

        $allowedClasses = SectionClassCodes::forSection($sectionDef);
        $allowedSet = array_fill_keys($allowedClasses, true);

        foreach ($validated['authors'] as $i => $author) {
            $class = (string) ($author['class'] ?? '');
            if (! isset($allowedSet[$class])) {
                throw ValidationException::withMessages([
                    "authors.$i.class" => ['Ungültige Klasse für diese Abteilung.'],
                ]);
            }
        }

        $authorCount = count($validated['authors']);
        $thesisStatus = $this->thesisInitialStatusFromAuthorRules($session, $key, $authorCount);

        DB::transaction(function () use ($validated, $thesis, $key, $thesisStatus) {
            $thesis->update([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'section' => $key,
                'status' => $thesisStatus,
            ]);

            Author::query()->where('thesis', $thesis->id)->delete();

            foreach ($validated['authors'] as $author) {
                Author::create([
                    'first_name' => $author['first_name'],
                    'last_name' => $author['last_name'],
                    'class' => $author['class'],
                    'thesis' => $thesis->id,
                    'email' => $author['email'],
                    'handy' => trim((string) ($author['handy'] ?? '')) ?: '',
                    'status' => 1,
                ]);
            }
        });

        $thesis->refresh();

        return response()->json([
            'thesis' => [
                'id' => $thesis->id,
                'edit_code' => $thesis->password,
                'requires_rector_approval' => (int) $thesis->status === 1,
            ],
        ]);
    }

    /**
     * Nutzt thesis_sessions.section_author_rules: nur Themeneingabe (Lernende), nicht LP-Betreuung.
     * Matrix 0/1/2: 0 = Einreichung nicht erlaubt, 1 = sofort aktiv (Thesis-Status 2), 2 = bewilligungspflichtig (Thesis-Status 1).
     * Leeres Regelwerk, fehlende Abteilungszeile oder fehlender Schlüssel für n: Thesis-Status 2 (aktiv).
     *
     * @return int thesis.status (1 = bewilligungspflichtig, 2 = aktiv)
     */
    private function thesisInitialStatusFromAuthorRules(ThesisSession $session, string $sectionKeyLower, int $authorCount): int
    {
        $rules = $session->section_author_rules ?? [];
        if (! is_array($rules) || $rules === []) {
            return 2;
        }

        $row = null;
        foreach ($rules as $k => $r) {
            if (strtolower((string) $k) === $sectionKeyLower && is_array($r)) {
                $row = $r;
                break;
            }
        }
        if ($row === null) {
            return 2;
        }

        $slot = (string) max(1, min(3, $authorCount));
        $raw = $row[$slot] ?? $row[(int) $slot] ?? null;
        if ($raw === null) {
            return 2;
        }

        $code = (int) $raw;
        if ($code === 0) {
            throw ValidationException::withMessages([
                'authors' => ['Für diese Abteilung ist eine Arbeit mit dieser Anzahl Lernende nicht vorgesehen.'],
            ]);
        }
        if ($code === 2) {
            return 1;
        }

        return 2;
    }

    private function resolveSession(Request $request): ?ThesisSession
    {
        $now = Carbon::now();
        $candidates = $this->orderedSessionsWithSchoolyear();

        if ($request->filled('thesis_session_id')) {
            $id = (int) $request->input('thesis_session_id');
            $session = $candidates->firstWhere('id', $id)
                ?? ThesisSession::query()->with('schoolyear')->whereNotNull('schoolyear_id')->find($id);

            if ($session === null) {
                return null;
            }

            $phase = $this->phaseFlags($session, $now);

            return $phase['allowsNew'] ? $session : null;
        }

        foreach ($candidates as $session) {
            if ($this->phaseFlags($session, $now)['allowsNew']) {
                return $session;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, ThesisSession>
     */
    private function orderedSessionsWithSchoolyear(): Collection
    {
        return ThesisSession::query()
            ->with('schoolyear')
            ->whereNotNull('schoolyear_id')
            ->join('schoolyears', 'schoolyears.id', '=', 'thesis_sessions.schoolyear_id')
            ->orderByDesc('schoolyears.starts_on')
            ->orderByDesc('thesis_sessions.id')
            ->select('thesis_sessions.*')
            ->get();
    }

    private function isSectionAllowedForSubmission(ThesisSession $session, string $sectionKeyLower): bool
    {
        $raw = $session->submission_section_keys;
        if ($raw === null) {
            return true;
        }
        if ($raw === []) {
            return false;
        }
        foreach ($raw as $k) {
            if (strtolower(trim((string) $k)) === $sectionKeyLower) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{allowsNew: bool, allowsEdit: bool}
     */
    private function phaseFlags(ThesisSession $session, Carbon $now): array
    {
        $p1 = $session->phase_1_at;
        $p4 = $session->phase_4_at;

        $allowsNew = $now->greaterThanOrEqualTo($p1) && $now->lessThan($p4);
        $allowsEdit = $allowsNew;

        return [
            'allowsNew' => $allowsNew,
            'allowsEdit' => $allowsEdit,
        ];
    }

    /**
     * @return list<array{key: string, name: string, class_codes: list<string>}>
     */
    private function sectionsPayload(ThesisSession $session): array
    {
        $sections = $session->schoolyear?->sections;
        if (! is_array($sections)) {
            return [];
        }

        $submissionKeys = $session->submission_section_keys;
        $allow = null;
        if (is_array($submissionKeys)) {
            $allow = [];
            foreach ($submissionKeys as $k) {
                $allow[strtolower(trim((string) $k))] = true;
            }
        }

        $out = [];
        foreach ($sections as $rawKey => $def) {
            $key = strtolower((string) $rawKey);
            if ($key === '' || ! is_array($def)) {
                continue;
            }
            if (is_array($allow) && ! isset($allow[$key])) {
                continue;
            }
            $name = trim((string) ($def['name'] ?? ''));
            $out[] = [
                'key' => $key,
                'name' => $name !== '' ? $name : $key,
                'class_codes' => SectionClassCodes::forSection($def),
            ];
        }

        return $out;
    }

    private function uniqueEditCode(): string
    {
        do {
            $code = substr(bin2hex(random_bytes(8)), 0, 10);
        } while (Thesis::query()->where('password', $code)->exists());

        return $code;
    }
}
