<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Zuordnungssession (z. B. „IDPA/SA 2025/26“). Tabelle thesis_sessions.
 */
class ThesisSession extends Model
{
    protected $table = 'thesis_sessions';

    protected $fillable = [
        'name',
        'phase_1_at',
        'phase_2_at',
        'phase_3_at',
        'phase_4_at',
        'phase_5_at',
    ];

    protected $casts = [
        'phase_1_at' => 'datetime',
        'phase_2_at' => 'datetime',
        'phase_3_at' => 'datetime',
        'phase_4_at' => 'datetime',
        'phase_5_at' => 'datetime',
    ];
}
