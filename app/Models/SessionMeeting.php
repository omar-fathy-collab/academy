<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionMeeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'title',
        'meeting_link',
        'end_time',
        'is_closed',
    ];

    protected $casts = [
        'is_closed' => 'boolean',
    ];

    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id', 'session_id');
    }

    public function logs()
    {
        return $this->hasMany(MeetingLog::class, 'meeting_id');
    }
}
