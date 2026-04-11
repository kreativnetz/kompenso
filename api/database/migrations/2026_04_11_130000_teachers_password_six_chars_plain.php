<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** teachers.password: Klartext, 6 Zeichen. Bei MySQL: vorher lange Werte (z. B. bcrypt) kürzen/neu setzen, sonst schlägt ALTER fehl. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teachers')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE teachers MODIFY password VARCHAR(6) NOT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('teachers')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE teachers MODIFY password VARCHAR(255) NOT NULL');
        }
    }
};
