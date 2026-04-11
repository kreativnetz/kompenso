<?php

namespace App\Services;

use App\Models\ThesisSession;
use Carbon\Carbon;

/**
 * Phasen aus thesis_sessions.phase_1_at … phase_5_at (aufsteigende Stichtage).
 * Zusätzlich: closed_at — fachliches Ende der Session (Schreibschutz für Lernende/LP/Manager an Arbeiten).
 *
 * Index 0: vor phase_1_at
 * Index 1: [phase_1_at, phase_2_at) — Lernende: Themen einreichen und per Code bearbeiten
 * Index 2: [phase_2_at, phase_3_at) — LP Lesephase (Board)
 * Index 3: [phase_3_at, phase_4_at) — LP: Buchungen; Austragen nur hier
 * Index 4: [phase_4_at, phase_5_at) — LP: weiter eintragen; Austragen geschlossen
 * Index 5: ab phase_5_at — LP-Selbsteintrag geschlossen; Board/Lesen weiter möglich
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

    /** Lernende: neue Arbeit einreichen (Phasenindex 1–3, bis vor phase_4_at). */
    public static function allowsLearnerNewSubmission(ThesisSession $session, Carbon $now): bool
    {
        return $now->greaterThanOrEqualTo($session->phase_1_at) && $now->lt($session->phase_4_at);
    }

    /** Lernende: bestehende Arbeit per Bearbeitungscode ändern (nur Index 1). */
    public static function allowsLearnerTopicEditByCode(ThesisSession $session, Carbon $now): bool
    {
        return $now->greaterThanOrEqualTo($session->phase_1_at) && $now->lt($session->phase_2_at);
    }

    /** LP: sich für eine Arbeit eintragen (Kalender-Phase 3 und 4). */
    public static function allowsTeacherSelfBooking(ThesisSession $session, Carbon $now): bool
    {
        return $now->greaterThanOrEqualTo($session->phase_3_at) && $now->lt($session->phase_5_at);
    }

    /** LP: sich austragen (nur erstes Buchungsfenster). */
    public static function allowsTeacherSelfWithdrawal(ThesisSession $session, Carbon $now): bool
    {
        return $now->greaterThanOrEqualTo($session->phase_3_at) && $now->lt($session->phase_4_at);
    }

    /**
     * Manager: Betreuung zuweisen (zeitlich: ab LP-Board; Schreibschutz nur closed_at am Controller).
     */
    public static function allowsManagerSupervisionAssignment(ThesisSession $session, Carbon $now): bool
    {
        return self::allowsTeacherBoard($session, $now);
    }

    /** Session fachlich beendet (Archiv für den LP-Alltag). */
    public static function isSessionClosed(ThesisSession $session, Carbon $now): bool
    {
        $c = $session->closed_at;
        if ($c === null) {
            return false;
        }

        return $now->greaterThanOrEqualTo($c);
    }

    /** Für Home-UI: Session noch „lebendig“ für die Schule (nicht archiviert). */
    public static function isActiveForSchoolHighlight(ThesisSession $session, Carbon $now): bool
    {
        return ! self::isSessionClosed($session, $now);
    }
}
