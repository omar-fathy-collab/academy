<?php

namespace App\Models;


use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'sessions';

    protected $primaryKey = 'session_id';

    protected $fillable = [
        'group_id',
        'session_date',
        'start_time',
        'end_time',
        'topic',
        'meeting_link',
        'notes',
        'requires_proximity',
        'created_by',
    ];

    protected $casts = [
        'session_date' => 'date',
        'requires_proximity' => 'boolean',
    ];

    /**
     * Get the group that the session belongs to.
     */
    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', 'group_id');
    }

    /**
     * Get the attendance records for this session.
     */
    // In Session model
    public function quizzes()
    {
        return $this->hasMany(Quiz::class, 'session_id', 'session_id');
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class, 'session_id', 'session_id');
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class, 'session_id', 'session_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'session_id', 'session_id');
    }

    public function materials()
    {
        return $this->hasMany(SessionMaterial::class, 'session_id', 'session_id');
    }

    public function meetings()
    {
        return $this->hasMany(SessionMeeting::class, 'session_id', 'session_id');
    }
}
