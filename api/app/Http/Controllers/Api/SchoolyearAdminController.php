<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Schoolyear;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SchoolyearAdminController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureManager($request);

        $years = Schoolyear::query()
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'schoolyears' => $years->map(fn (Schoolyear $y) => $this->yearPayload($y)),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureManager($request);

        $data = $this->validatedYear($request, false);

        $starts = \Illuminate\Support\Carbon::parse($data['starts_on']);
        $ends = \Illuminate\Support\Carbon::parse($data['ends_on']);
        if ($ends->lt($starts)) {
            throw ValidationException::withMessages([
                'ends_on' => ['Das Enddatum muss am oder nach dem Startdatum liegen.'],
            ]);
        }

        $sourceForExamYearBump = null;

        if ($request->filled('copy_from_schoolyear_id')) {
            $source = Schoolyear::findOrFail((int) $request->input('copy_from_schoolyear_id'));
            $data['sections'] = $source->sections ?? [];
            $sourceForExamYearBump = $source;
        } elseif ($request->boolean('copy_from_previous')) {
            $predecessor = Schoolyear::query()
                ->where('ends_on', '<', $data['starts_on'])
                ->orderByDesc('ends_on')
                ->first();
            if ($predecessor) {
                $data['sections'] = $predecessor->sections ?? [];
                $sourceForExamYearBump = $predecessor;
            }
        }

        if ($sourceForExamYearBump !== null) {
            $deltaYears = $starts->year - $sourceForExamYearBump->starts_on->year;
            $data['sections'] = $this->bumpSectionExamYears($data['sections'] ?? [], $deltaYears);
        }

        $data['sections'] = $this->normalizeSectionsForStorage($data['sections'] ?? []);
        $this->assertSectionsShape($data['sections']);

        $year = Schoolyear::create($data);

        return response()->json([
            'schoolyear' => $this->yearPayload($year),
        ], 201);
    }

    public function update(Request $request, Schoolyear $schoolyear)
    {
        $this->ensureManager($request);

        $data = $this->validatedYear($request, true);
        if ($data !== []) {
            $starts = isset($data['starts_on'])
                ? \Illuminate\Support\Carbon::parse($data['starts_on'])
                : $schoolyear->starts_on;
            $ends = isset($data['ends_on'])
                ? \Illuminate\Support\Carbon::parse($data['ends_on'])
                : $schoolyear->ends_on;
            if ($ends->lt($starts)) {
                throw ValidationException::withMessages([
                    'ends_on' => ['Das Enddatum muss am oder nach dem Startdatum liegen.'],
                ]);
            }
            if (array_key_exists('sections', $data)) {
                $data['sections'] = $this->normalizeSectionsForStorage($data['sections'] ?? []);
                $this->assertSectionsShape($data['sections']);
            }
            $schoolyear->update($data);
            $schoolyear->refresh();
        }

        return response()->json([
            'schoolyear' => $this->yearPayload($schoolyear),
        ]);
    }

    public function destroy(Request $request, Schoolyear $schoolyear)
    {
        $this->ensureManager($request);

        if ($schoolyear->thesisSessions()->exists()) {
            return response()->json([
                'message' => 'Schuljahr kann nicht gelöscht werden: Es existieren noch Zuordnungssessions.',
            ], 422);
        }

        $schoolyear->delete();

        return response()->json(['status' => 'ok']);
    }

    private function ensureManager(Request $request): void
    {
        if ((int) $request->user()->status < 3) {
            abort(403, 'Keine Berechtigung für die Schuljahr-Verwaltung.');
        }
    }

    private function validatedYear(Request $request, bool $partial): array
    {
        $rules = [
            'label' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'starts_on' => [$partial ? 'sometimes' : 'required', 'date'],
            'ends_on' => [$partial ? 'sometimes' : 'required', 'date'],
            'sections' => [$partial ? 'sometimes' : 'required', 'array'],
            'copy_from_previous' => ['sometimes', 'boolean'],
            'copy_from_schoolyear_id' => ['sometimes', 'nullable', 'integer', 'exists:schoolyears,id'],
        ];

        $validated = $request->validate($rules);

        unset($validated['copy_from_previous'], $validated['copy_from_schoolyear_id']);

        return $validated;
    }

    /**
     * Erhöht pro Abteilung den gespeicherten Abschlussjahrgang (zweistellig) um die Differenz der Kalenderjahre
     * des Schuljahres-Starts (neu minus Quelle).
     *
     * @param  array<string, mixed>  $sections
     * @return array<string, mixed>
     */
    private function bumpSectionExamYears(array $sections, int $deltaYears): array
    {
        if ($deltaYears === 0) {
            return $sections;
        }

        $out = [];
        foreach ($sections as $key => $row) {
            if (! is_array($row)) {
                $out[$key] = $row;

                continue;
            }
            if (array_key_exists('exam_year', $row)) {
                $ey = (int) $row['exam_year'];
                $row['exam_year'] = max(0, min(99, $ey + $deltaYears));
            }
            $out[$key] = $row;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $sections
     * @return array<string, array{name: string, prefix: string, terms: int, exam_year: int, finish_class_count: int}>
     */
    private function normalizeSectionsForStorage(array $sections): array
    {
        $out = [];
        foreach ($sections as $key => $raw) {
            if (! is_string($key) || ! is_array($raw)) {
                continue;
            }
            $key = strtolower(trim($key));
            if ($key === '' || ! preg_match('/^[a-z0-9_]+$/', $key)) {
                continue;
            }
            $finishCount = (int) ($raw['finish_class_count'] ?? 0);
            if ($finishCount < 1) {
                $ll = $raw['last_letter'] ?? '';
                if (is_string($ll) && strlen($ll) === 1) {
                    $c = strtolower($ll);
                    $ord = ord($c);
                    if ($ord >= 97 && $ord <= 122) {
                        $finishCount = $ord - 96;
                    }
                }
            }
            if ($finishCount < 1) {
                $finishCount = 1;
            }
            $finishCount = min(26, $finishCount);

            $examYear = array_key_exists('exam_year', $raw) ? (int) $raw['exam_year'] : 0;

            $defaultAuthorRules = ['1' => 0, '2' => 0, '3' => 0];
            $darRaw = $raw['default_author_rules'] ?? null;
            if (is_array($darRaw)) {
                foreach (['1', '2', '3'] as $nk) {
                    if (! array_key_exists($nk, $darRaw) && ! array_key_exists((int) $nk, $darRaw)) {
                        continue;
                    }
                    $v = $darRaw[$nk] ?? $darRaw[(int) $nk] ?? null;
                    if ($v === null || $v === '') {
                        continue;
                    }
                    $iv = (int) $v;
                    $defaultAuthorRules[$nk] = max(0, min(2, $iv));
                }
            }

            $out[$key] = [
                'name' => is_string($raw['name'] ?? null) ? trim($raw['name']) : '',
                'prefix' => is_string($raw['prefix'] ?? null) ? trim($raw['prefix']) : '',
                'terms' => (int) ($raw['terms'] ?? 0),
                'exam_year' => $examYear,
                'finish_class_count' => $finishCount,
                'default_author_rules' => $defaultAuthorRules,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $sections
     */
    private function assertSectionsShape(array $sections): void
    {
        if ($sections === []) {
            throw ValidationException::withMessages([
                'sections' => ['Mindestens eine Abteilung erforderlich.'],
            ]);
        }

        foreach ($sections as $key => $data) {
            if (! is_string($key) || $key === '' || ! preg_match('/^[a-z0-9_]+$/', $key)) {
                throw ValidationException::withMessages([
                    'sections' => ['Ungültiger Abteilungsschlüssel: nur Kleinbuchstaben, Ziffern und Unterstrich.'],
                ]);
            }

            if (! is_array($data)) {
                throw ValidationException::withMessages([
                    'sections' => ["Abteilung „{$key}“: Daten müssen ein Objekt sein."],
                ]);
            }

            foreach (['name', 'prefix'] as $field) {
                if (! isset($data[$field]) || ! is_string($data[$field]) || trim($data[$field]) === '') {
                    throw ValidationException::withMessages([
                        'sections' => ["Abteilung „{$key}“: „{$field}“ ist erforderlich."],
                    ]);
                }
            }

            $terms = (int) ($data['terms'] ?? 0);
            if ($terms < 1 || $terms > 7) {
                throw ValidationException::withMessages([
                    'sections' => ["Abteilung „{$key}“: „terms“ muss zwischen 1 und 7 liegen."],
                ]);
            }

            $examYear = (int) ($data['exam_year'] ?? -1);
            if ($examYear < 0 || $examYear > 99) {
                throw ValidationException::withMessages([
                    'sections' => ["Abteilung „{$key}“: „exam_year“ muss zwischen 0 und 99 liegen (z. B. 24)."],
                ]);
            }

            $finishCount = (int) ($data['finish_class_count'] ?? 0);
            if ($finishCount < 1 || $finishCount > 26) {
                throw ValidationException::withMessages([
                    'sections' => ["Abteilung „{$key}“: „finish_class_count“ muss zwischen 1 und 26 liegen."],
                ]);
            }

            if (! isset($data['default_author_rules']) || ! is_array($data['default_author_rules'])) {
                throw ValidationException::withMessages([
                    'sections' => ["Abteilung „{$key}“: „default_author_rules“ ist erforderlich."],
                ]);
            }
            foreach (['1', '2', '3'] as $nk) {
                if (! array_key_exists($nk, $data['default_author_rules'])) {
                    throw ValidationException::withMessages([
                        'sections' => ["Abteilung „{$key}“: „default_author_rules“ muss Schlüssel 1, 2 und 3 enthalten."],
                    ]);
                }
                $dv = (int) $data['default_author_rules'][$nk];
                if ($dv < 0 || $dv > 2) {
                    throw ValidationException::withMessages([
                        'sections' => ["Abteilung „{$key}“: Standard Autorenregeln (1/2/3) müssen 0, 1 oder 2 sein."],
                    ]);
                }
            }
        }
    }

    private function yearPayload(Schoolyear $y): array
    {
        return [
            'id' => $y->id,
            'label' => $y->label,
            'starts_on' => $y->starts_on?->format('Y-m-d'),
            'ends_on' => $y->ends_on?->format('Y-m-d'),
            'sections' => $y->sections ?? [],
        ];
    }
}
