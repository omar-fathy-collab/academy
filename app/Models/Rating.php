<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\HasUuid;

class Rating extends Model
{
    use HasFactory, HasUuid;

    public $timestamps = false;

    protected $table = 'ratings';

    protected $primaryKey = 'rating_id';

    protected $fillable = [
        'student_id',
        'group_id',
        'session_id',
        'rating_value',
        'comments',
        'rating_type',
        'rated_by',
        'rated_at',
        'month',
        'year',
    ];

    protected $casts = [
        'rated_at' => 'datetime',
    ];

    // Relationships
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function subcourse()
    {
        return $this->belongsTo(Subcourse::class, 'subcourse_id');
    }

    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    public function ratedBy()
    {
        return $this->belongsTo(User::class, 'rated_by');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }
}
