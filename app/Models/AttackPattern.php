<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttackPattern extends Model
{
    protected $fillable = [
        'name',
        'type',
        'signature',
        'confidence_score',
        'hit_count',
        'last_seen_at',
        'is_active',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function logs()
    {
        return $this->hasMany(SecurityLog::class, 'pattern_id');
    }
}
