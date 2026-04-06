<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentCourse extends Model
{
    use HasFactory;

    protected $table = 'student_course';

    protected $primaryKey = 'id';

    protected $fillable = [
        'student_id',
        'course_id',
    ];

    /**
     * Get the student that is enrolled in the course.
     */
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    /**
     * Get the course that the student is enrolled in.
     */
    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }
}
