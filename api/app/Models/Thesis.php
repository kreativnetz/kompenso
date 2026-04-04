<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $status 0 = abgelehnt/inaktiv, 1 = bewilligungspflichtig, 2 = aktiv/bewilligt
 */
class Thesis extends Model
{
    protected $table = 'thesis';

    public $timestamps = false;

    protected $fillable = [
        'title',
        'description',
        'type',
        'password',
        'session',
        'status',
        'section',
    ];

    protected $casts = [
        'type' => 'integer',
        'session' => 'integer',
        'status' => 'integer',
    ];

    public function thesisSession(): BelongsTo
    {
        return $this->belongsTo(ThesisSession::class, 'session');
    }

    public function authors(): HasMany
    {
        return $this->hasMany(Author::class, 'thesis');
    }

    public function supervisions(): HasMany
    {
        return $this->hasMany(Supervision::class, 'thesis');
    }
}
