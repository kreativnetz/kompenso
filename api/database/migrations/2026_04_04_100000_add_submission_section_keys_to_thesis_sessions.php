<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('thesis_sessions')) {
            return;
        }

        if (! Schema::hasColumn('thesis_sessions', 'submission_section_keys')) {
            Schema::table('thesis_sessions', function (Blueprint $table) {
                $table->json('submission_section_keys')->nullable()->after('compensation');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('thesis_sessions')) {
            return;
        }

        if (Schema::hasColumn('thesis_sessions', 'submission_section_keys')) {
            Schema::table('thesis_sessions', function (Blueprint $table) {
                $table->dropColumn('submission_section_keys');
            });
        }
    }
};
