<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Author extends Model
{
    protected $table = 'authors';

    public $timestamps = false;

    protected $fillable = [
        'last_name',
        'first_name',
        'class',
        'thesis',
        'email',
        'handy',
        'status',
    ];

    protected $casts = [
        'thesis' => 'integer',
        'status' => 'integer',
    ];

    public function thesisModel(): BelongsTo
    {
        return $this->belongsTo(Thesis::class, 'thesis');
    }
}
