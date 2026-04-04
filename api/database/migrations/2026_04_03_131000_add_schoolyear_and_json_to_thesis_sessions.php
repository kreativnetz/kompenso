<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('thesis_sessions')) {
            return;
        }

        if (! Schema::hasColumn('thesis_sessions', 'schoolyear_id')) {
            Schema::table('thesis_sessions', function (Blueprint $table) {
                $table->foreignId('schoolyear_id')->nullable()->constrained('schoolyears')->restrictOnDelete();
                $table->json('section_author_rules')->nullable();
                $table->json('compensation')->nullable();
            });
        }

        $emptyObject = json_encode(new \stdClass);

        $sessionCount = DB::table('thesis_sessions')->count();
        if ($sessionCount > 0) {
            $legacyId = DB::table('schoolyears')->where('label', 'Legacy')->value('id');
            if (! $legacyId) {
                $now = now()->toDateTimeString();
                $legacyId = DB::table('schoolyears')->insertGetId([
                    'label' => 'Legacy',
                    'starts_on' => '2000-08-01',
                    'ends_on' => '2001-07-31',
                    'sections' => $emptyObject,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('thesis_sessions')->whereNull('schoolyear_id')->update([
                'schoolyear_id' => $legacyId,
            ]);
        }

        DB::table('thesis_sessions')->whereNull('section_author_rules')->update([
            'section_author_rules' => $emptyObject,
        ]);
        DB::table('thesis_sessions')->whereNull('compensation')->update([
            'compensation' => $emptyObject,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('thesis_sessions')) {
            return;
        }

        if (Schema::hasColumn('thesis_sessions', 'schoolyear_id')) {
            Schema::table('thesis_sessions', function (Blueprint $table) {
                $table->dropForeign(['schoolyear_id']);
                $table->dropColumn(['schoolyear_id', 'section_author_rules', 'compensation']);
            });
        }
    }
};
