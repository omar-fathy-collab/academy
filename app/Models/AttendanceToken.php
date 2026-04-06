<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceToken extends Model
{
    protected $fillable = [
        'session_id',
        'teacher_subnet',
        'is_open',
        'is_wifi_open',
        'is_qr_open',
        'lat',
        'lng',
        'radius_meters',
        'opened_at',
        'closed_at',
        'qr_token',
        'qr_expires_at',
        'refresh_interval',
    ];

    protected $casts = [
        'is_open' => 'boolean',
        'is_wifi_open' => 'boolean',
        'is_qr_open' => 'boolean',
        'lat' => 'decimal:8',
        'lng' => 'decimal:8',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'qr_expires_at' => 'datetime',
    ];

    /**
     * Get the session associated with the token.
     */
    public function session()
    {
        return $this->belongsTo(Session::class);
    }
}
