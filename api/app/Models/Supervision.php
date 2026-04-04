<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Supervision extends Model
{
    protected $table = 'supervisions';

    public $timestamps = false;

    protected $fillable = [
        'thesis',
        'teacher',
        'type',
        'datum',
        'status',
    ];

    protected $casts = [
        'thesis' => 'integer',
        'teacher' => 'integer',
        'type' => 'integer',
        'status' => 'integer',
        'datum' => 'datetime',
    ];

    public function thesisModel(): BelongsTo
    {
        return $this->belongsTo(Thesis::class, 'thesis');
    }

    public function teacherModel(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher');
    }
}
