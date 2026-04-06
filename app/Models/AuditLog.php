<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'event',
        'description',
        'ip_address',
        'user_agent',
    ];

    /**
     * Get the user associated with the audit log.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
