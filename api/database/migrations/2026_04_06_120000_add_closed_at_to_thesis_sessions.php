<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('thesis_sessions', function (Blueprint $table) {
            $table->dateTime('closed_at')->nullable()->after('phase_5_at');
        });
    }

    public function down(): void
    {
        Schema::table('thesis_sessions', function (Blueprint $table) {
            $table->dropColumn('closed_at');
        });
    }
};
