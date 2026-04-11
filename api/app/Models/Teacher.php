<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Teacher extends Authenticatable
{
    use HasApiTokens;

    public $timestamps = false;

    protected $table = 'teachers';

    protected $fillable = [
        'last_name',
        'first_name',
        'token',
        'email',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function isActive(): bool
    {
        return (int) $this->status !== 0;
    }

    public function supervisions(): HasMany
    {
        return $this->hasMany(Supervision::class, 'teacher');
    }

    public static function roleLabel(int $status): string
    {
        return match ($status) {
            0 => 'deaktiviert',
            1 => 'Lehrperson',
            2 => 'Lehrperson +',
            3 => 'Schulleitung',
            4 => 'Administrator',
            default => 'unbekannt',
        };
    }
}
