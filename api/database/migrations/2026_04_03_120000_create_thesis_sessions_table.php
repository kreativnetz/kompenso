<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('thesis_sessions')) {
            return;
        }

        Schema::create('thesis_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->dateTime('phase_1_at');
            $table->dateTime('phase_2_at');
            $table->dateTime('phase_3_at');
            $table->dateTime('phase_4_at');
            $table->dateTime('phase_5_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thesis_sessions');
    }
};
