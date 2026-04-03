<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ThesisSession;
use Illuminate\Http\Request;

class ThesisSessionAdminController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureManager($request);

        $sessions = ThesisSession::query()
            ->orderBy('name')
            ->get();

        return response()->json([
            'thesis_sessions' => $sessions->map(fn (ThesisSession $s) => $this->sessionPayload($s)),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureManager($request);

        $data = $this->validatedSession($request);

        $session = ThesisSession::create($data);

        return response()->json([
            'thesis_session' => $this->sessionPayload($session),
        ], 201);
    }

    public function update(Request $request, ThesisSession $thesisSession)
    {
        $this->ensureManager($request);

        $data = $this->validatedSession($request);
        $thesisSession->update($data);
        $thesisSession->refresh();

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

    private function validatedSession(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phase_1_at' => ['required', 'date'],
            'phase_2_at' => ['required', 'date'],
            'phase_3_at' => ['required', 'date'],
            'phase_4_at' => ['required', 'date'],
            'phase_5_at' => ['required', 'date'],
        ]);
    }

    private function sessionPayload(ThesisSession $session): array
    {
        return [
            'id' => $session->id,
            'name' => $session->name,
            'phase_1_at' => $this->formatPhaseInput($session->phase_1_at),
            'phase_2_at' => $this->formatPhaseInput($session->phase_2_at),
            'phase_3_at' => $this->formatPhaseInput($session->phase_3_at),
            'phase_4_at' => $this->formatPhaseInput($session->phase_4_at),
            'phase_5_at' => $this->formatPhaseInput($session->phase_5_at),
        ];
    }

    private function formatPhaseInput(?\Illuminate\Support\Carbon $dt): ?string
    {
        return $dt?->format('Y-m-d\TH:i');
    }
}
