<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiskProfile extends Model
{
    protected $fillable = [
        'identifier',
        'trust_score',
        'attack_count',
        'false_positive_count',
        'behavior_metadata',
        'last_activity_at',
    ];

    protected $casts = [
        'behavior_metadata' => 'array',
        'last_activity_at' => 'datetime',
    ];

    public static function getForIdentifier(string $identifier): self
    {
        return self::firstOrCreate(
            ['identifier' => $identifier],
            ['trust_score' => 100]
        );
    }
}
