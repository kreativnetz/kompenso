<?php

namespace App\Services;

use App\Models\ThesisSession;
use Carbon\Carbon;

/**
 * Phasen aus thesis_sessions.phase_1_at … phase_5_at (aufsteigende Stichtage).
 *
 * Index 1: [phase_1_at, phase_2_at) — nur Lernende
 * Index 2: [phase_2_at, phase_3_at) — LP Lesephase
 * Index 3: [phase_3_at, phase_4_at) — LP Buchen / Austragen
 * Index 4: [phase_4_at, phase_5_at) — u.a. Admin-Zuweisung
 * Index 5: ab phase_5_at — weiterhin Admin-Zuweisung möglich
 */
final class ThesisSessionPhase
{
    public static function currentPhaseIndex(ThesisSession $session, Carbon $now): int
    {
        if ($now->lt($session->phase_1_at)) {
            return 0;
        }
        if ($now->lt($session->phase_2_at)) {
            return 1;
        }
        if ($now->lt($session->phase_3_at)) {
            return 2;
        }
        if ($now->lt($session->phase_4_at)) {
            return 3;
        }
        if ($now->lt($session->phase_5_at)) {
            return 4;
        }

        return 5;
    }

    public static function allowsTeacherBoard(ThesisSession $session, Carbon $now): bool
    {
        return $now->greaterThanOrEqualTo($session->phase_2_at);
    }

    public static function allowsBooking(ThesisSession $session, Carbon $now): bool
    {
        return $now->greaterThanOrEqualTo($session->phase_3_at) && $now->lt($session->phase_4_at);
    }

    public static function allowsAdminAssignment(ThesisSession $session, Carbon $now): bool
    {
        return $now->greaterThanOrEqualTo($session->phase_4_at);
    }

    public static function isSessionPast(ThesisSession $session, Carbon $now): bool
    {
        return $now->greaterThanOrEqualTo($session->phase_5_at);
    }

    /** Volle Themensliste (alle Arbeiten): ab LP-Zugang bis vor Ende Phase 5. */
    public static function isActiveForFullList(ThesisSession $session, Carbon $now): bool
    {
        return self::allowsTeacherBoard($session, $now) && $now->lt($session->phase_5_at);
    }
}
