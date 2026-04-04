<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alte Semantik: status 0 = Bewilligung ausstehend, 1 = aktiv/sichtbar.
 * Neu: 0 = abgelehnt/inaktiv, 1 = Bewilligung ausstehend, 2 = aktiv.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('thesis')) {
            return;
        }

        DB::statement(
            'UPDATE thesis SET status = CASE WHEN status = 0 THEN 1 WHEN status = 1 THEN 2 ELSE status END'
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('thesis')) {
            return;
        }

        DB::statement(
            'UPDATE thesis SET status = CASE WHEN status = 1 THEN 0 WHEN status = 2 THEN 1 ELSE status END'
        );
    }
};
