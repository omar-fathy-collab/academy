<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{

    use \Illuminate\Database\Eloquent\Factories\HasFactory, \App\Traits\HasUuid;

    protected $table = 'attendance';

    protected $primaryKey = 'attendance_id';

    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'session_id',
        'status',
        'ip_address',
        'recorded_by',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    /**
     * Get the student whose attendance was marked.
     */
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    /**
     * Get the session for which attendance was marked.
     */
    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id', 'session_id');
    }
}
