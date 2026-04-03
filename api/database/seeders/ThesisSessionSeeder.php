<?php

namespace Database\Seeders;

use App\Models\ThesisSession;
use Illuminate\Database\Seeder;

class ThesisSessionSeeder extends Seeder
{
    public function run(): void
    {
        ThesisSession::updateOrCreate(
            ['name' => 'IDPA/SA 2025/26'],
            [
                'phase_1_at' => '2025-08-30 00:00:00',
                'phase_2_at' => '2025-09-11 23:59:59',
                'phase_3_at' => '2025-09-12 12:00:00',
                'phase_4_at' => '2025-09-30 00:00:00',
                'phase_5_at' => '2025-10-01 00:00:00',
            ]
        );
    }
}
