<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{

    use \Illuminate\Database\Eloquent\Factories\HasFactory, \App\Traits\HasUuid;

    protected $table = 'teachers';

    protected $primaryKey = 'teacher_id';

    protected $fillable = [
        'user_id',
        'teacher_name',
        'department_id',
        'hire_date',
        'salary_percentage',
        'base_salary',
        'bank_account',
        'payment_method',
    ];

    /**
     * Get the user that owns the teacher.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Get the department that owns the teacher.
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    /**
     * Get the groups that the teacher teaches.
     */
    public function groups()
    {
        return $this->hasMany(Group::class, 'teacher_id', 'teacher_id');
    }
}

