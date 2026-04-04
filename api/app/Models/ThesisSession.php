<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Zuordnungssession (z. B. „IDPA/SA 2025/26“). Tabelle thesis_sessions.
 */
class ThesisSession extends Model
{
    protected $table = 'thesis_sessions';

    protected $fillable = [
        'schoolyear_id',
        'name',
        'phase_1_at',
        'phase_2_at',
        'phase_3_at',
        'phase_4_at',
        'phase_5_at',
        'section_author_rules',
        'compensation',
        'submission_section_keys',
    ];

    protected $casts = [
        'phase_1_at' => 'datetime',
        'phase_2_at' => 'datetime',
        'phase_3_at' => 'datetime',
        'phase_4_at' => 'datetime',
        'phase_5_at' => 'datetime',
        'section_author_rules' => 'array',
        'compensation' => 'array',
        'submission_section_keys' => 'array',
    ];

    public function schoolyear(): BelongsTo
    {
        return $this->belongsTo(Schoolyear::class);
    }

    public function theses(): HasMany
    {
        return $this->hasMany(Thesis::class, 'session');
    }
}
