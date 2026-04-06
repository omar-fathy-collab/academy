<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $table = 'department';

    protected $primaryKey = 'department_id';

    protected $fillable = [
        'department_name',
        'description',
        'head_teacher_id',
    ];

    /**
     * Get the teachers for the department.
     */
    public function teachers()
    {
        return $this->hasMany(Teacher::class, 'department_id', 'department_id');
    }

    /**
     * Get the head teacher for the department.
     */
    public function headTeacher()
    {
        return $this->belongsTo(Teacher::class, 'head_teacher_id', 'teacher_id');
    }

    /**
     * Get the courses for the department.
     * Since Course model does not exist, this relationship is commented out.
     */
    // public function courses()
    // {
    //     return $this->hasMany(Course::class, 'department_id', 'department_id');
    // }
}
