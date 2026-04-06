<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Course extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['course_name', 'description', 'price', 'is_free', 'is_public', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $table = 'courses';

    protected $primaryKey = 'course_id';

    // timestamps are present in the migration
    public $timestamps = true;

    protected $fillable = [
        'course_name',
        'description',
        'price',
        'is_free',
        'is_public',
        'is_active',
    ];

    /**
     * Get the department that the course belongs to.
     */

    /**
     * Get the groups for this course.
     */
    public function groups()
    {
        return $this->hasMany(Group::class, 'course_id', 'course_id');
    }

    /**
     * Get the students enrolled in this course.
     */
    public function students()
    {
        return $this->belongsToMany(Student::class, 'student_course', 'course_id', 'student_id');
    }

    public function waitingGroups()
    {
        return $this->hasMany(WaitingGroup::class, 'course_id', 'course_id');
    }

    public function subcourses()
    {
        return $this->hasMany(Subcourse::class, 'course_id', 'course_id');
    }
}
