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
        $data = $this->validatedSession($request, $copyDefaults);
        unset($data['copy_defaults']);

        if ($copyDefaults) {
            $defaults = $this->resolveDefaultRulesAndCompensation((int) $data['schoolyear_id']);
            $data['section_author_rules'] = $defaults['section_author_rules'];
            $data['compensation'] = $defaults['compensation'];
        } else {
            $data['section_author_rules'] = $data['section_author_rules'] ?? [];
            $data['compensation'] = $data['compensation'] ?? [];
        }

        $schoolyear = Schoolyear::findOrFail((int) $data['schoolyear_id']);
        $this->assertAuthorRulesMatchYear($data['section_author_rules'] ?? [], $schoolyear);
        $this->assertCompensationShape($data['compensation'] ?? null);

        $session = ThesisSession::create($data);
        $session->load('schoolyear');

        return response()->json([
            'thesis_session' => $this->sessionPayload($session),
        ], 201);
    }

    public function update(Request $request, ThesisSession $thesisSession)
    {
        $this->ensureManager($request);

        $data = $this->validatedSession($request, false);
        unset($data['copy_defaults']);

        $data['section_author_rules'] = $data['section_author_rules'] ?? [];
        $data['compensation'] = $data['compensation'] ?? [];

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

    private function validatedSession(Request $request, bool $skipRulesAndCompChecks = false): array
    {
        $validated = $request->validate([
            'schoolyear_id' => ['required', 'integer', 'exists:schoolyears,id'],
            'name' => ['required', 'string', 'max:255'],
            'phase_1_at' => ['required', 'date'],
            'phase_2_at' => ['required', 'date'],
            'phase_3_at' => ['required', 'date'],
            'phase_4_at' => ['required', 'date'],
            'phase_5_at' => ['required', 'date'],
            'section_author_rules' => ['nullable', 'array'],
            'compensation' => ['nullable', 'array'],
            'copy_defaults' => ['sometimes', 'boolean'],
        ]);

        if (! $skipRulesAndCompChecks) {
            $schoolyear = Schoolyear::findOrFail((int) $validated['schoolyear_id']);
            $this->assertAuthorRulesMatchYear($validated['section_author_rules'] ?? [], $schoolyear);
            $this->assertCompensationShape($validated['compensation'] ?? null);
        }

        return $validated;
    }

    /**
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
                        'section_author_rules' => ['Werte pro Autorenzahl: 0 = nein, 1 = ja, 2 = Bewilligung.'],
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
     * @return array{section_author_rules: array, compensation: array}
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
                ];
            }
        }

        return [
            'section_author_rules' => [],
            'compensation' => [],
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
        ];
    }

    private function formatPhaseInput(?\Illuminate\Support\Carbon $dt): ?string
    {
        return $dt?->format('Y-m-d\TH:i');
    }
}
