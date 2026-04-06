<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecurityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_type',
        'risk_score',
        'ip_address',
        'request_path',
        'request_method',
        'payload',
        'user_agent',
        'description',
        'country',
        'city',
        'device_id',
        'session_id',
        'anomaly_hint',
        'reputation_score',
        'is_false_positive',
        'pattern_id',
        'adaptive_weight',
    ];

    protected $casts = [
        'payload' => 'array',
        'risk_score' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pattern()
    {
        return $this->belongsTo(AttackPattern::class, 'pattern_id');
    }
}
