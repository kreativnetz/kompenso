<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('schoolyears')) {
            return;
        }

        Schema::create('schoolyears', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->json('sections');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schoolyears');
    }
};
