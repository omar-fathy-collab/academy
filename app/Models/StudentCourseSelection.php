<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentCourseSelection extends Model
{
    use HasFactory;

    protected $table = 'student_course_selections';

    protected $primaryKey = 'id';

    protected $fillable = [
        'student_id',
        'course_id',
        'selection_type',
        'notes',
        'selected_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * علاقة مع الطالب
     */
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    /**
     * علاقة مع الكورس
     */
    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    /**
     * علاقة مع من قام بالاختيار
     */
    public function selector()
    {
        return $this->belongsTo(User::class, 'selected_by', 'id');
    }

    /**
     * الحصول على آخر اختيار للطالب
     */
    public static function getLatestSelection($studentId)
    {
        return self::where('student_id', $studentId)
            ->with('course')
            ->latest()
            ->first();
    }

    /**
     * الحصول على تاريخ التسجيل (من تاريخ إنشاء المستخدم)
     */
    public function getRegistrationDateAttribute()
    {
        if ($this->student && $this->student->user) {
            return $this->student->user->created_at;
        }

        return $this->created_at;
    }
}
