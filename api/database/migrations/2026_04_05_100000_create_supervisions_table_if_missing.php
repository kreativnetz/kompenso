<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supervisions')) {
            return;
        }

        Schema::create('supervisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('thesis');
            $table->unsignedBigInteger('teacher');
            $table->integer('type');
            $table->dateTime('datum');
            $table->integer('status')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisions');
    }
};
