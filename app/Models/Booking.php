<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{

    use \Illuminate\Database\Eloquent\Factories\HasFactory, \App\Traits\HasUuid;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'age',
        'placement_exam_grade', // تأكد من وجوده
        'date',
        'time',
        'message',
        'student_id',
    ];

    protected $appends = ['waiting_groups'];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    public function getWaitingGroupsAttribute()
    {
        if ($this->student) {
            return $this->student->waitingStudents->map(function ($ws) {
                return $ws->waitingGroup;
            })->filter()->values();
        }
        return [];
    }

    // إضافة scope للفلترة
    public function scopeNotInWaitingGroups($query)
    {
        return $query->whereNull('student_id');
    }

    public function scopeInWaitingGroups($query)
    {
        return $query->whereNotNull('student_id');
    }
}
