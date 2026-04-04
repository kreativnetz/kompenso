<?php

namespace App\Support;

final class SectionClassCodes
{
    /**
     * @param  array<string, mixed>  $section
     * @return list<string>
     */
    public static function forSection(array $section): array
    {
        $prefix = trim((string) ($section['prefix'] ?? ''));
        $examYear = (int) ($section['exam_year'] ?? 0);
        $examYear = max(0, min(99, $examYear));
        $count = (int) ($section['finish_class_count'] ?? 0);
        if ($count < 1 && isset($section['last_letter'])) {
            $letter = strtolower(substr((string) $section['last_letter'], 0, 1));
            if (strlen($letter) === 1 && $letter >= 'a' && $letter <= 'z') {
                $count = ord($letter) - ord('a') + 1;
            }
        }
        if ($count < 1) {
            $count = 1;
        }
        $count = min(26, $count);
        if ($prefix === '') {
            return [];
        }
        $y = str_pad((string) $examYear, 2, '0', STR_PAD_LEFT);
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = $prefix.$y.chr(ord('a') + $i);
        }

        return $codes;
    }
}
