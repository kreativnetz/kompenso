<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schoolyear extends Model
{
    protected $fillable = [
        'label',
        'starts_on',
        'ends_on',
        'sections',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'sections' => 'array',
    ];

    public function thesisSessions(): HasMany
    {
        return $this->hasMany(ThesisSession::class);
    }
}
