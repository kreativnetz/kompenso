<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Schoolyear;
use App\Models\ThesisSession;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ThesisSessionAdminController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureManager($request);

        $sessions = ThesisSession::query()
            ->with('schoolyear')
            ->get()
            ->sortBy([
                fn (ThesisSession $s) => -($s->schoolyear?->starts_on?->timestamp ?? 0),
                fn (ThesisSession $s) => $s->name,
            ])
            ->values();

        return response()->json([
            'thesis_sessions' => $sessions->map(fn (ThesisSession $s) => $this->sessionPayload($s)),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureManager($request);

        $copyDefaults = $request->boolean('copy_defaults');
        $data = $this->validatedSession($request);

        if ($copyDefaults) {
            $defaults = $this->resolveDefaultRulesAndCompensation((int) $data['schoolyear_id']);
            $data['section_author_rules'] = $defaults['section_author_rules'];
            $data['compensation'] = $defaults['compensation'];
            $data['submission_section_keys'] = $defaults['submission_section_keys'] ?? null;
        } else {
            $data['section_author_rules'] = $data['section_author_rules'] ?? [];
            $data['compensation'] = $data['compensation'] ?? [];
            $data['submission_section_keys'] = $this->normalizeSubmissionSectionKeysForStorage(
                $request->has('submission_section_keys') ? $request->input('submission_section_keys') : null
            );
        }

        unset($data['copy_defaults']);

        $schoolyear = Schoolyear::findOrFail((int) $data['schoolyear_id']);
        $this->applySessionBusinessRules($data, $schoolyear);

        $session = ThesisSession::create($data);
        $session->load('schoolyear');

        return response()->json([
            'thesis_session' => $this->sessionPayload($session),
        ], 201);
    }

    public function update(Request $request, ThesisSession $thesisSession)
    {
        $this->ensureManager($request);

        $data = $this->validatedSession($request);

        $data['section_author_rules'] = $data['section_author_rules'] ?? [];
        $data['compensation'] = $data['compensation'] ?? [];
        $data['submission_section_keys'] = $this->normalizeSubmissionSectionKeysForStorage(
            $request->has('submission_section_keys') ? $request->input('submission_section_keys') : null
        );

        unset($data['copy_defaults']);

        $schoolyear = Schoolyear::findOrFail((int) $data['schoolyear_id']);
        $this->applySessionBusinessRules($data, $schoolyear);

        $thesisSession->update($data);
        $thesisSession->refresh();
        $thesisSession->load('schoolyear');

        return response()->json([
            'thesis_session' => $this->sessionPayload($thesisSession),
        ]);
    }

    public function destroy(Request $request, ThesisSession $thesisSession)
    {
        $this->ensureManager($request);
        $thesisSession->delete();

        return response()->json(['status' => 'ok']);
    }

    private function ensureManager(Request $request): void
    {
        if ((int) $request->user()->status < 3) {
            abort(403, 'Keine Berechtigung für die Session-Verwaltung.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedSession(Request $request): array
    {
        return $request->validate([
            'schoolyear_id' => ['required', 'integer', 'exists:schoolyears,id'],
            'name' => ['required', 'string', 'max:255'],
            'phase_1_at' => ['required', 'date'],
            'phase_2_at' => ['required', 'date'],
            'phase_3_at' => ['required', 'date'],
            'phase_4_at' => ['required', 'date'],
            'phase_5_at' => ['required', 'date'],
            'section_author_rules' => ['nullable', 'array'],
            'compensation' => ['nullable', 'array'],
            'submission_section_keys' => ['sometimes', 'nullable', 'array'],
            'submission_section_keys.*' => ['string', 'max:32'],
            'copy_defaults' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applySessionBusinessRules(array &$data, Schoolyear $schoolyear): void
    {
        $this->assertSubmissionSectionKeys($data['submission_section_keys'] ?? null, $schoolyear);
        $data['section_author_rules'] = $this->filterAuthorRulesToAllowedSections(
            $data['section_author_rules'] ?? [],
            $data['submission_section_keys'] ?? null,
            $schoolyear,
        );
        $this->assertAuthorRulesMatchYear($data['section_author_rules'] ?? [], $schoolyear);
        $this->assertCompensationShape($data['compensation'] ?? null);
    }

    /**
     * @param  list<string>|null  $keys  null = alle Sektionen; [] = keine; sonst nur diese (lowercase)
     */
    private function assertSubmissionSectionKeys(?array $keys, Schoolyear $schoolyear): void
    {
        if ($keys === null) {
            return;
        }

        $yearKeyLower = [];
        foreach (array_keys($schoolyear->sections ?? []) as $yk) {
            $yearKeyLower[strtolower((string) $yk)] = true;
        }

        foreach ($keys as $k) {
            $lk = strtolower(trim((string) $k));
            if ($lk === '') {
                continue;
            }
            if (! isset($yearKeyLower[$lk])) {
                throw ValidationException::withMessages([
                    'submission_section_keys' => ["Unbekannte Sektion „{$k}“ (nicht im Schuljahr)."],
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  list<string>|null  $submissionKeysLower  null = keine Filterung
     * @return array<string, mixed>
     */
    private function filterAuthorRulesToAllowedSections(array $rules, ?array $submissionKeysLower, Schoolyear $schoolyear): array
    {
        if ($submissionKeysLower === null) {
            return $rules;
        }

        $allow = array_fill_keys($submissionKeysLower, true);
        $out = [];
        foreach ($rules as $sk => $row) {
            if (! is_array($row)) {
                continue;
            }
            $lk = strtolower((string) $sk);
            if (isset($allow[$lk])) {
                foreach (array_keys($schoolyear->sections ?? []) as $canonical) {
                    if (strtolower((string) $canonical) === $lk) {
                        $out[$canonical] = $row;
                        break;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * @return list<string>|null
     */
    private function normalizeSubmissionSectionKeysForStorage(mixed $raw): ?array
    {
        if ($raw === null) {
            return null;
        }
        if (! is_array($raw)) {
            throw ValidationException::withMessages([
                'submission_section_keys' => ['Muss ein Array sein.'],
            ]);
        }
        $out = [];
        foreach ($raw as $k) {
            $lk = strtolower(trim((string) $k));
            if ($lk !== '') {
                $out[$lk] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * section_author_rules steuert nur die öffentliche Themeneingabe (erlaubte Autorenzahl / Bewilligung), nicht LP-Betreuung.
     *
     * @param  array<string, mixed>  $rules
     */
    private function assertAuthorRulesMatchYear(array $rules, Schoolyear $schoolyear): void
    {
        $sectionKeys = array_keys($schoolyear->sections ?? []);

        foreach (array_keys($rules) as $sectionKey) {
            if (! in_array($sectionKey, $sectionKeys, true)) {
                throw ValidationException::withMessages([
                    'section_author_rules' => ["Unbekannte Sektion „{$sectionKey}“ (nicht in Schuljahr-Sektionen)."],
                ]);
            }
            $row = $rules[$sectionKey];
            if (! is_array($row)) {
                throw ValidationException::withMessages([
                    'section_author_rules' => ["Regeln für „{$sectionKey}“ müssen ein Objekt sein."],
                ]);
            }
            foreach ($row as $k => $v) {
                if (! in_array((string) $k, ['1', '2', '3'], true)) {
                    throw ValidationException::withMessages([
                        'section_author_rules' => ["Ungültiger Autoren-Schlüssel „{$k}“ (nur 1, 2, 3)."],
                    ]);
                }
                if (! in_array((int) $v, [0, 1, 2], true)) {
                    throw ValidationException::withMessages([
                        'section_author_rules' => ['Themeneingabe — Werte pro Autorenzahl: 0 = nicht erlaubt, 1 = sofort aktiv (Thesis-Status 2), 2 = bewilligungspflichtig (Thesis-Status 1 bis Freigabe).'],
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>|null  $compensation
     */
    private function assertCompensationShape(?array $compensation): void
    {
        if ($compensation === null || $compensation === []) {
            return;
        }

        foreach (['haupt', 'gegen'] as $role) {
            if (! isset($compensation[$role]) || ! is_array($compensation[$role])) {
                throw ValidationException::withMessages([
                    'compensation' => ["Entschädigung: „{$role}“ fehlt oder ist kein Objekt."],
                ]);
            }
            foreach (['1', '2', '3'] as $k) {
                $row = $compensation[$role];
                $val = $row[$k] ?? $row[(int) $k] ?? null;
                if ($val === null) {
                    throw ValidationException::withMessages([
                        'compensation' => ["Entschädigung „{$role}“: Wert für {$k} Autor(en) fehlt."],
                    ]);
                }
                if (! is_numeric($val)) {
                    throw ValidationException::withMessages([
                        'compensation' => ["Entschädigung „{$role}“.{$k} muss numerisch sein."],
                    ]);
                }
            }
        }
    }

    /**
     * @return array{section_author_rules: array, compensation: array, submission_section_keys: ?array}
     */
    private function resolveDefaultRulesAndCompensation(int $schoolyearId): array
    {
        $schoolyear = Schoolyear::findOrFail($schoolyearId);

        $latest = ThesisSession::query()
            ->where('schoolyear_id', $schoolyearId)
            ->orderByDesc('id')
            ->first();

        if ($latest) {
            return [
                'section_author_rules' => $latest->section_author_rules ?? [],
                'compensation' => $latest->compensation ?? [],
                'submission_section_keys' => $latest->submission_section_keys,
            ];
        }

        $prevYear = Schoolyear::query()
            ->where('ends_on', '<', $schoolyear->starts_on)
            ->orderByDesc('ends_on')
            ->first();

        if ($prevYear) {
            $latestPrev = ThesisSession::query()
                ->where('schoolyear_id', $prevYear->id)
                ->orderByDesc('id')
                ->first();
            if ($latestPrev) {
                return [
                    'section_author_rules' => $latestPrev->section_author_rules ?? [],
                    'compensation' => $latestPrev->compensation ?? [],
                    'submission_section_keys' => $latestPrev->submission_section_keys,
                ];
            }
        }

        return [
            'section_author_rules' => [],
            'compensation' => [],
            'submission_section_keys' => null,
        ];
    }

    private function sessionPayload(ThesisSession $session): array
    {
        return [
            'id' => $session->id,
            'schoolyear_id' => $session->schoolyear_id,
            'schoolyear' => $session->schoolyear
                ? [
                    'id' => $session->schoolyear->id,
                    'label' => $session->schoolyear->label,
                ]
                : null,
            'name' => $session->name,
            'phase_1_at' => $this->formatPhaseInput($session->phase_1_at),
            'phase_2_at' => $this->formatPhaseInput($session->phase_2_at),
            'phase_3_at' => $this->formatPhaseInput($session->phase_3_at),
            'phase_4_at' => $this->formatPhaseInput($session->phase_4_at),
            'phase_5_at' => $this->formatPhaseInput($session->phase_5_at),
            'section_author_rules' => $session->section_author_rules ?? [],
            'compensation' => $session->compensation ?? [],
            'submission_section_keys' => $session->submission_section_keys,
        ];
    }

    private function formatPhaseInput(?\Illuminate\Support\Carbon $dt): ?string
    {
        return $dt?->format('Y-m-d\TH:i');
    }
}
