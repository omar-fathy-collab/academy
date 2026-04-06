<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class SubCourse extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['subcourse_name', 'subcourse_number', 'course_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $table = 'subcourses';

    protected $primaryKey = 'subcourse_id';

    protected $fillable = [
        'course_id',
        'subcourse_name',
        'subcourse_number',
        'description',
        'duration_hours',
    ];

    // العلاقة مع الكورس الرئيسي
    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    // العلاقة مع مجموعات الانتظار
    public function waitingGroups()
    {
        return $this->hasMany(WaitingGroup::class, 'subcourse_id', 'subcourse_id');
    }

    // دالة للحصول على الاسم الكامل
    public function getFullNameAttribute()
    {
        return $this->subcourse_name.' (مستوى '.$this->subcourse_number.')';
    }
}
