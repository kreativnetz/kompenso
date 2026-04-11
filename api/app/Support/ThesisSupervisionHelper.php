<?php

namespace App\Support;

use App\Models\Supervision;
use App\Models\Thesis;

class ThesisSupervisionHelper
{
    /**
     * Eine besetzte Rolle pro Typ; Legacy-Zeilen mit status 1 werden weiterhin angezeigt, bis Admin bereinigt.
     *
     * @return array{id: int, teacher_id: int, teacher_token: string}|null
     */
    public static function activeSupervisionSlotPayload(Thesis $thesis, int $type): ?array
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

    /**
     * @param  array<string, mixed>  $compensation
     */
    public static function compensationAmountForRole(array $compensation, int $authorCount, int $type): ?float
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

    /**
     * Erste drei Zeichen des Abteilungsnamens (Legacy excel.php), sonst Gedankenstrich.
     *
     * @param  array<string, mixed>  $sectionsMeta
     */
    public static function sectionExcelPrefix(string $sectionKeyLower, array $sectionsMeta): string
    {
        $name = '';
        foreach ($sectionsMeta as $rawK => $def) {
            if (strtolower((string) $rawK) === $sectionKeyLower && is_array($def)) {
                $n = trim((string) ($def['name'] ?? ''));
                if ($n !== '') {
                    $name = $n;
                }
                break;
            }
        }

        if ($name === '') {
            return '—';
        }

        $prefix = mb_substr($name, 0, 3, 'UTF-8');

        return $prefix !== '' ? $prefix : '—';
    }

    public static function formatCompensationCell(?float $amount): string
    {
        if ($amount === null) {
            return '';
        }

        $s = number_format($amount, 4, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');

        return $s === '' ? '0' : $s;
    }

    /**
     * @param  list<string|int|float>  $cells
     */
    public static function tsvLine(array $cells): string
    {
        $safe = array_map(function ($c) {
            return str_replace(["\t", "\n", "\r"], ' ', (string) $c);
        }, $cells);

        return implode("\t", $safe);
    }
}
