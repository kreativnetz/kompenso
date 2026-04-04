<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('thesis')) {
            Schema::create('thesis', function (Blueprint $table) {
                $table->id();
                $table->string('title', 100);
                $table->text('description');
                $table->integer('type');
                $table->string('password', 10);
                $table->unsignedBigInteger('session');
                $table->integer('status')->default(1);
                $table->string('section', 32);
            });
        }

        if (! Schema::hasTable('authors')) {
            Schema::create('authors', function (Blueprint $table) {
                $table->id();
                $table->string('last_name', 50);
                $table->string('first_name', 50);
                $table->string('class', 16);
                $table->unsignedBigInteger('thesis');
                $table->string('email', 50);
                $table->string('handy', 20);
                $table->integer('status')->default(1);
            });
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            if (Schema::hasTable('thesis') && Schema::hasColumn('thesis', 'section')) {
                try {
                    DB::statement('ALTER TABLE thesis MODIFY section VARCHAR(32) NOT NULL');
                } catch (\Throwable) {
                    // ignore if already widened or incompatible engine
                }
            }
            if (Schema::hasTable('authors') && Schema::hasColumn('authors', 'class')) {
                try {
                    DB::statement('ALTER TABLE authors MODIFY class VARCHAR(16) NOT NULL');
                } catch (\Throwable) {
                }
            }
        }
    }

    public function down(): void
    {
        // leave legacy tables in place
    }
};
