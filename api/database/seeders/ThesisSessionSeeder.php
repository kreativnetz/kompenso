<?php

namespace Database\Seeders;

use App\Models\Schoolyear;
use App\Models\ThesisSession;
use Illuminate\Database\Seeder;

class ThesisSessionSeeder extends Seeder
{
    public function run(): void
    {
        $schoolyear = Schoolyear::query()->updateOrCreate(
            ['label' => '2025/26'],
            [
                'starts_on' => '2025-08-01',
                'ends_on' => '2026-07-31',
                'sections' => [
                    'demo' => [
                        'name' => 'Demo-Abteilung',
                        'prefix' => 'D',
                        'terms' => 4,
                        'exam_year' => 25,
                        'finish_class_count' => 3,
                    ],
                ],
            ],
        );

        ThesisSession::query()->updateOrCreate(
            ['name' => 'IDPA/SA 2025/26'],
            [
                'schoolyear_id' => $schoolyear->id,
                'phase_1_at' => now()->subDay(),
                'phase_2_at' => now()->addMonths(2),
                'phase_3_at' => now()->addMonths(3),
                'phase_4_at' => now()->addMonths(4),
                'phase_5_at' => now()->addMonths(5),
                'section_author_rules' => [],
                'compensation' => [],
            ],
        );
    }
}
