<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeetingLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'meeting_id',
        'event_type',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id', 'session_id');
    }

    public function meeting()
    {
        return $this->belongsTo(SessionMeeting::class, 'meeting_id');
    }
}
