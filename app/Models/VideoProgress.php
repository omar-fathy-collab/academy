<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Student;
use App\Models\SessionMaterial;

class VideoProgress extends Model
{
    use HasFactory;

    protected $table = 'video_progress';

    protected $fillable = [
        'user_id',
        'student_id',
        'video_id',
        'watched_seconds',
        'watched_percentage',
        'watched_segments',
        'last_position',
        'is_completed',
        'last_heartbeat_at',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'watched_percentage' => 'decimal:2',
        'last_heartbeat_at' => 'datetime',
        'watched_segments' => 'array',
    ];

    public function video()
    {
        return $this->belongsTo(Video::class, 'video_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
