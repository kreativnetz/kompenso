<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supervision;
use App\Models\Teacher;
use App\Models\Thesis;
use App\Models\ThesisSession;
use App\Services\ThesisSessionPhase;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherThesisBoardController extends Controller
{
    public function supervisedSessions(Request $request)
    {
        $teacher = $request->user();
        $now = Carbon::now();

        $sessionIds = Supervision::query()
            ->where('supervisions.teacher', $teacher->id)
            ->where('supervisions.status', 2)
            ->join('thesis', 'thesis.id', '=', 'supervisions.thesis')
            ->distinct()
            ->pluck('thesis.session');

        $supervised = ThesisSession::query()
            ->with('schoolyear')
            ->whereIn('id', $sessionIds)
            ->get()
            ->sortBy([
                fn (ThesisSession $s) => -($s->schoolyear?->starts_on?->timestamp ?? 0),
                fn (ThesisSession $s) => $s->name,
            ])
            ->values()
            ->map(fn (ThesisSession $s) => $this->sessionSummaryPayload($s, $now));

        $current = ThesisSession::query()
            ->with('schoolyear')
            ->whereNotNull('schoolyear_id')
            ->join('schoolyears', 'schoolyears.id', '=', 'thesis_sessions.schoolyear_id')
            ->orderByDesc('schoolyears.starts_on')
            ->orderByDesc('thesis_sessions.id')
            ->select('thesis_sessions.*')
            ->get()
            ->first(fn (ThesisSession $s) => ThesisSessionPhase::isActiveForFullList($s, $now));

        return response()->json([
            'supervised_sessions' => $supervised,
            'current_accessible_session' => $current
                ? $this->sessionSummaryPayload($current, $now)
                : null,
        ]);
    }

    public function teacherBoard(Request $request, ThesisSession $thesisSession)
    {
        $teacher = $request->user();
        $now = Carbon::now();

        if (! $thesisSession->schoolyear_id) {
            abort(404);
        }

        $this->assertTeacherBoardSessionAccess($request, $thesisSession, $now);

        $phaseIndex = ThesisSessionPhase::currentPhaseIndex($thesisSession, $now);
        $allowsBoard = ThesisSessionPhase::allowsTeacherBoard($thesisSession, $now);
        $fullList = ThesisSessionPhase::isActiveForFullList($thesisSession, $now);

        $listMode = $fullList ? 'all' : 'mine';

        $isAdmin = (int) $teacher->status >= 3;

        $thesisQuery = Thesis::query()
            ->where('session', $thesisSession->id)
            ->when(
                $isAdmin,
                fn ($q) => $q->whereIn('status', [1, 2]),
                fn ($q) => $q->where('status', 2),
            )
            ->with(['authors', 'supervisions.teacherModel']);

        if ($listMode === 'mine') {
            $thesisIds = Supervision::query()
                ->where('teacher', $teacher->id)
                ->where('status', 2)
                ->whereIn('thesis', function ($q) use ($thesisSession) {
                    $q->select('id')->from('thesis')->where('session', $thesisSession->id);
                })
                ->pluck('thesis');
            $thesisQuery->whereIn('id', $thesisIds);
        }

        $theses = $thesisQuery->get();

        $thesisSession->loadMissing('schoolyear');
        $sectionsMeta = $thesisSession->schoolyear?->sections;
        if (! is_array($sectionsMeta)) {
            $sectionsMeta = [];
        }

        $grouped = $this->groupThesesBySectionAndClass($theses, $sectionsMeta);

        $canBook = ThesisSessionPhase::allowsBooking($thesisSession, $now);
        $canAdminAssign = (int) $teacher->status >= 3
            && ThesisSessionPhase::allowsTeacherBoard($thesisSession, $now);

        $teachersForAssign = null;
        if ($canAdminAssign) {
            $teachersForAssign = Teacher::query()
                ->where('status', '>', 0)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->map(fn (Teacher $t) => [
                    'id' => $t->id,
                    'full_name' => $t->fullName(),
                    'token' => $t->token,
                ]);
        }

        return response()->json([
            'thesis_session' => $this->sessionSummaryPayload($thesisSession, $now),
            'phase' => [
                'index' => $phaseIndex,
                'allows_teacher_board' => $allowsBoard,
                'can_book' => $canBook,
                'can_admin_assign' => $canAdminAssign,
            ],
            'list_mode' => $listMode,
            'sections' => $grouped,
            'teachers' => $teachersForAssign,
        ]);
    }

    public function myBookings(Request $request, ThesisSession $thesisSession)
    {
        $teacher = $request->user();
        $now = Carbon::now();

        if (! $thesisSession->schoolyear_id) {
            abort(404);
        }

        $this->assertTeacherBoardSessionAccess($request, $thesisSession, $now);

        $isAdmin = (int) $teacher->status >= 3;
        $thesisStatuses = $isAdmin ? [1, 2] : [2];

        $supervisions = Supervision::query()
            ->where('teacher', $teacher->id)
            ->where('status', 2)
            ->whereHas('thesisModel', function ($q) use ($thesisSession, $thesisStatuses) {
                $q->where('session', $thesisSession->id)
                    ->whereIn('status', $thesisStatuses);
            })
            ->with(['thesisModel.authors', 'thesisModel.supervisions.teacherModel'])
            ->orderBy('thesis')
            ->orderBy('type')
            ->get();

        $thesisSession->loadMissing('schoolyear');
        $sectionsMeta = $thesisSession->schoolyear?->sections;
        if (! is_array($sectionsMeta)) {
            $sectionsMeta = [];
        }

        $compensation = is_array($thesisSession->compensation) ? $thesisSession->compensation : [];

        $byThesis = $supervisions->groupBy('thesis');

        $cards = [];
        $mainCount = 0;
        $secondaryCount = 0;
        $compSum = 0.0;

        foreach ($byThesis as $group) {
            $t = $group->first()?->thesisModel;
            if ($t === null) {
                continue;
            }

            $authorCount = $t->authors->count();
            $sk = strtolower((string) $t->section);
            $sectionName = $this->sectionLabelFromMeta($sk, $sectionsMeta);

            $roles = [];
            foreach ($group->sortBy('type') as $sup) {
                $type = (int) $sup->type;
                if ($type === 1) {
                    $mainCount++;
                } else {
                    $secondaryCount++;
                }
                $amount = $this->compensationAmountForRole($compensation, $authorCount, $type);
                if ($amount !== null) {
                    $compSum += $amount;
                }
                $roles[] = [
                    'type' => $type,
                    'role_label' => $type === 1 ? 'Hauptbetreuung' : 'Gegenbetreuung',
                    'compensation_amount' => $amount,
                    'other_supervisor' => $this->otherActiveSupervisorForThesis($t, $type, (int) $teacher->id),
                ];
            }

            $cards[] = [
                'thesis_id' => $t->id,
                'title' => $t->title,
                'section_key' => $sk,
                'section_name' => $sectionName,
                'main_class' => $this->mainClassLabel($t),
                'authors' => $t->authors->map(fn ($a) => [
                    'first_name' => $a->first_name,
                    'last_name' => $a->last_name,
                    'class' => $a->class,
                ])->values()->all(),
                'roles' => $roles,
            ];
        }

        usort($cards, function ($a, $b) {
            return [$a['section_key'], $a['main_class'], $a['title']]
                <=> [$b['section_key'], $b['main_class'], $b['title']];
        });

        return response()->json([
            'thesis_session' => $this->sessionSummaryPayload($thesisSession, $now),
            'cards' => $cards,
            'totals' => [
                'theses_count' => count($cards),
                'main_supervisions' => $mainCount,
                'secondary_supervisions' => $secondaryCount,
                'compensation_total' => round($compSum, 2),
            ],
        ]);
    }

    public function storeSupervision(Request $request, ThesisSession $thesisSession)
    {
        $teacher = $request->user();
        $now = Carbon::now();

        if (! ThesisSessionPhase::allowsBooking($thesisSession, $now)) {
            throw ValidationException::withMessages([
                'phase' => ['In dieser Phase ist kein Eintragen möglich.'],
            ]);
        }

        $data = $request->validate([
            'thesis_id' => ['required', 'integer'],
            'type' => ['required', 'integer', 'in:1,2'],
        ]);

        $thesis = Thesis::query()
            ->where('id', $data['thesis_id'])
            ->where('session', $thesisSession->id)
            ->where('status', 2)
            ->firstOrFail();

        $type = (int) $data['type'];
        if ((int) $teacher->status < 3) {
            $otherType = $type === 1 ? 2 : 1;
            $alreadyOther = Supervision::query()
                ->where('thesis', $thesis->id)
                ->where('type', $otherType)
                ->where('teacher', $teacher->id)
                ->whereIn('status', [1, 2])
                ->exists();
            if ($alreadyOther) {
                throw ValidationException::withMessages([
                    'type' => ['Du bist für diese Arbeit bereits in der anderen Betreuungsrolle eingetragen.'],
                ]);
            }
        }

        DB::transaction(function () use ($thesis, $teacher, $data, $now) {
            $locked = Supervision::query()
                ->where('thesis', $thesis->id)
                ->where('type', (int) $data['type'])
                ->lockForUpdate()
                ->get();

            $occupied = $locked->contains(
                fn (Supervision $s) => in_array((int) $s->status, [1, 2], true)
            );
            if ($occupied) {
                throw ValidationException::withMessages([
                    'type' => ['Diese Rolle ist bereits besetzt.'],
                ]);
            }

            Supervision::create([
                'thesis' => $thesis->id,
                'teacher' => $teacher->id,
                'type' => (int) $data['type'],
                'datum' => $now,
                'status' => 2,
            ]);
        });

        return response()->json(['status' => 'ok'], 201);
    }

    public function withdrawSupervision(Request $request, ThesisSession $thesisSession, Supervision $supervision)
    {
        $teacher = $request->user();
        $now = Carbon::now();

        if (! ThesisSessionPhase::allowsBooking($thesisSession, $now)) {
            throw ValidationException::withMessages([
                'phase' => ['In dieser Phase ist kein Austragen möglich.'],
            ]);
        }

        $thesis = Thesis::query()
            ->where('id', $supervision->thesis)
            ->where('session', $thesisSession->id)
            ->firstOrFail();

        if ((int) $supervision->teacher !== (int) $teacher->id) {
            abort(403);
        }

        DB::transaction(function () use ($supervision, $teacher) {
            $supervision->refresh();
            if ((int) $supervision->teacher !== (int) $teacher->id) {
                abort(403);
            }
            $supervision->update(['status' => 0]);
        });

        return response()->json(['status' => 'ok']);
    }

    public function assignSupervision(Request $request, ThesisSession $thesisSession)
    {
        $teacher = $request->user();
        $now = Carbon::now();

        if ((int) $teacher->status < 3) {
            abort(403);
        }

        if (! ThesisSessionPhase::allowsTeacherBoard($thesisSession, $now)) {
            throw ValidationException::withMessages([
                'phase' => ['Zuweisung ist derzeit nicht möglich.'],
            ]);
        }

        $data = $request->validate([
            'thesis_id' => ['required', 'integer'],
            'type' => ['required', 'integer', 'in:1,2'],
            'teacher_id' => ['present', 'nullable', 'integer', 'exists:teachers,id'],
        ]);

        $thesis = Thesis::query()
            ->where('id', $data['thesis_id'])
            ->where('session', $thesisSession->id)
            ->where('status', 2)
            ->firstOrFail();

        DB::transaction(function () use ($thesis, $data, $now) {
            Supervision::query()
                ->where('thesis', $thesis->id)
                ->where('type', (int) $data['type'])
                ->lockForUpdate()
                ->get();

            Supervision::query()
                ->where('thesis', $thesis->id)
                ->where('type', (int) $data['type'])
                ->update(['status' => 0]);

            if ($data['teacher_id'] === null) {
                return;
            }

            $winner = Teacher::query()->where('id', $data['teacher_id'])->where('status', '>', 0)->firstOrFail();

            $row = Supervision::query()
                ->where('thesis', $thesis->id)
                ->where('type', (int) $data['type'])
                ->where('teacher', $winner->id)
                ->first();

            if ($row) {
                $row->update([
                    'status' => 2,
                    'datum' => $now,
                ]);
            } else {
                Supervision::create([
                    'thesis' => $thesis->id,
                    'teacher' => $winner->id,
                    'type' => (int) $data['type'],
                    'datum' => $now,
                    'status' => 2,
                ]);
            }
        });

        return response()->json(['status' => 'ok']);
    }

    public function setThesisWorkflowStatus(Request $request, ThesisSession $thesisSession, Thesis $thesis)
    {
        $user = $request->user();
        if ((int) $user->status < 3) {
            abort(403);
        }

        if ((int) $thesis->session !== (int) $thesisSession->id) {
            abort(404);
        }

        $data = $request->validate([
            'status' => ['required', 'integer', 'in:0,2'],
        ]);

        if ((int) $thesis->status !== 1) {
            throw ValidationException::withMessages([
                'status' => ['Nur Arbeiten mit ausstehender Bewilligung können hier bearbeitet werden.'],
            ]);
        }

        $thesis->update(['status' => (int) $data['status']]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * @param  array<string, mixed>  $sectionsMeta
     * @return list<array{key: string, name: string, classes: list<array{class_label: string, theses: list<array<string, mixed>>}>}>
     */
    private function groupThesesBySectionAndClass($theses, array $sectionsMeta): array
    {
        $bySection = [];
        foreach ($theses as $thesis) {
            $sk = strtolower((string) $thesis->section);
            $mainClass = $this->mainClassLabel($thesis);
            if (! isset($bySection[$sk])) {
                $bySection[$sk] = [];
            }
            if (! isset($bySection[$sk][$mainClass])) {
                $bySection[$sk][$mainClass] = [];
            }
            $bySection[$sk][$mainClass][] = $this->thesisPayload($thesis);
        }

        ksort($bySection, SORT_STRING);

        $out = [];
        foreach ($bySection as $sk => $classes) {
            ksort($classes, SORT_STRING);
            $name = $sk;
            foreach ($sectionsMeta as $rawK => $def) {
                if (strtolower((string) $rawK) === $sk && is_array($def)) {
                    $n = trim((string) ($def['name'] ?? ''));
                    if ($n !== '') {
                        $name = $n;
                    }
                    break;
                }
            }
            $classList = [];
            foreach ($classes as $classLabel => $list) {
                $classList[] = [
                    'class_label' => $classLabel,
                    'theses' => $list,
                ];
            }
            $out[] = [
                'key' => $sk,
                'name' => $name,
                'classes' => $classList,
            ];
        }

        return $out;
    }

    private function mainClassLabel(Thesis $thesis): string
    {
        $classes = $thesis->authors->pluck('class')->filter()->map(fn ($c) => (string) $c)->sort()->values();
        if ($classes->isEmpty()) {
            return '—';
        }

        return $classes->first();
    }

    private function thesisPayload(Thesis $thesis): array
    {
        return [
            'id' => $thesis->id,
            'title' => $thesis->title,
            'description' => $thesis->description,
            'workflow_status' => (int) $thesis->status,
            'section_key' => strtolower((string) $thesis->section),
            'main_class' => $this->mainClassLabel($thesis),
            'authors' => $thesis->authors->map(fn ($a) => [
                'first_name' => $a->first_name,
                'last_name' => $a->last_name,
                'class' => $a->class,
            ])->values()->all(),
            'main_supervision' => $this->activeSupervisionSlotPayload($thesis, 1),
            'secondary_supervision' => $this->activeSupervisionSlotPayload($thesis, 2),
        ];
    }

    /**
     * Eine besetzte Rolle pro Typ; Legacy-Zeilen mit status 1 werden weiterhin angezeigt, bis Admin bereinigt.
     *
     * @return array{id: int, teacher_id: int, teacher_token: string}|null
     */
    private function activeSupervisionSlotPayload(Thesis $thesis, int $type): ?array
    {
        $candidates = $thesis->supervisions
            ->where('type', $type)
            ->filter(fn (Supervision $s) => in_array((int) $s->status, [1, 2], true))
            ->sortBy(fn (Supervision $s) => (int) $s->status === 2 ? 0 : 1)
            ->values();

        $s = $candidates->first();
        if ($s === null) {
            return null;
        }

        return [
            'id' => $s->id,
            'teacher_id' => (int) $s->teacher,
            'teacher_token' => (string) ($s->teacherModel?->token ?? ''),
        ];
    }

    private function sessionSummaryPayload(ThesisSession $session, Carbon $now): array
    {
        $session->loadMissing('schoolyear');

        return [
            'id' => $session->id,
            'name' => $session->name,
            'schoolyear_label' => $session->schoolyear?->label,
            'phase_index' => ThesisSessionPhase::currentPhaseIndex($session, $now),
            'is_past' => ThesisSessionPhase::isSessionPast($session, $now),
            'is_active_for_board' => ThesisSessionPhase::isActiveForFullList($session, $now),
        ];
    }

    private function assertTeacherBoardSessionAccess(Request $request, ThesisSession $thesisSession, Carbon $now): void
    {
        $teacher = $request->user();
        $allowsBoard = ThesisSessionPhase::allowsTeacherBoard($thesisSession, $now);
        $isPast = ThesisSessionPhase::isSessionPast($thesisSession, $now);

        $hasConfirmedHere = Supervision::query()
            ->where('teacher', $teacher->id)
            ->where('status', 2)
            ->whereIn('thesis', function ($q) use ($thesisSession) {
                $q->select('id')
                    ->from('thesis')
                    ->where('session', $thesisSession->id);
            })
            ->exists();

        if (! $allowsBoard) {
            abort(403, 'Die Themensliste ist in dieser Phase noch nicht freigegeben.');
        }

        if ($isPast && ! $hasConfirmedHere) {
            abort(403, 'Kein Zugriff auf diese abgeschlossene Session.');
        }
    }

    /**
     * @param  array<string, mixed>  $sectionsMeta
     */
    private function sectionLabelFromMeta(string $sectionKey, array $sectionsMeta): string
    {
        $name = $sectionKey;
        foreach ($sectionsMeta as $rawK => $def) {
            if (strtolower((string) $rawK) === $sectionKey && is_array($def)) {
                $n = trim((string) ($def['name'] ?? ''));
                if ($n !== '') {
                    $name = $n;
                }
                break;
            }
        }

        return $name;
    }

    /**
     * @param  array<string, mixed>  $compensation
     */
    private function compensationAmountForRole(array $compensation, int $authorCount, int $type): ?float
    {
        $key = $type === 1 ? 'haupt' : 'gegen';
        $row = $compensation[$key] ?? null;
        if (! is_array($row)) {
            return null;
        }
        $slot = (string) max(1, min(3, $authorCount));
        $raw = $row[$slot] ?? $row[(int) $slot] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }

        return round((float) $raw, 4);
    }

    private function otherActiveSupervisorForThesis(Thesis $thesis, int $myType, int $viewerTeacherId): ?array
    {
        $otherType = $myType === 1 ? 2 : 1;
        $candidates = $thesis->supervisions
            ->where('type', $otherType)
            ->filter(fn (Supervision $s) => in_array((int) $s->status, [1, 2], true))
            ->sortBy(fn (Supervision $s) => (int) $s->status === 2 ? 0 : 1)
            ->values();
        $s = $candidates->first();
        if ($s === null) {
            return null;
        }
        if ((int) $s->teacher === $viewerTeacherId) {
            return null;
        }

        return [
            'teacher_id' => (int) $s->teacher,
            'token' => (string) ($s->teacherModel?->token ?? ''),
            'full_name' => $s->teacherModel?->fullName() ?? '',
        ];
    }
}
