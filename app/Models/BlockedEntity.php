<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlockedEntity extends Model
{
    use HasFactory;

    protected $fillable = [
        'identifier',
        'type',
        'reason',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function isExpired()
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
