<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Group extends Model
{

    use HasFactory, HasUuid, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['group_name', 'course_id', 'teacher_id', 'start_date', 'end_date', 'price'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public $timestamps = false;

    protected $table = 'groups';

    protected $primaryKey = 'group_id';

    protected $fillable = [
        'group_name',
        'is_online',
        'course_id',
        'subcourse_id',
        'teacher_id',
        'schedule',
        'start_date',
        'end_date',
        'price',
        'is_free',
        'is_public',
        'teacher_percentage',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Get the course that the group belongs to.
     */
    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id', 'course_id');
    }

    /**
     * Get the teacher that teaches the group.
     */
    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id', 'teacher_id');
    }

    /**
     * Get the subcourse that the group belongs to.
     */
    public function subcourse()
    {
        return $this->belongsTo(Subcourse::class, 'subcourse_id', 'subcourse_id');
    }

    /**
     * Get the students in this group.
     */
    public function students()
    {
        return $this->belongsToMany(Student::class, 'student_group', 'group_id', 'student_id');
    }

    public function salaries()
    {
        return $this->hasMany(Salary::class, 'group_id', 'group_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'group_id', 'group_id');
    }

    /**
     * Get the sessions for this group.
     */
    public function sessions()
    {
        return $this->hasMany(Session::class, 'group_id', 'group_id');
    }

    /**
     * Get the messages for this group.
     */
    public function messages()
    {
        return $this->hasMany(Message::class, 'group_id', 'group_id');
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('end_date')
                ->orWhere('end_date', '>=', now());
        });
    }

    /**
     * Get the schedules for this group.
     */
    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'group_id', 'group_id');
    }

    public function enrollmentRequests()
    {
        return $this->hasMany(GroupEnrollmentRequest::class, 'group_id', 'group_id');
    }

    /**
     * Check if group is free
     */
    public function isFree()
    {
        return $this->is_free || $this->price <= 0;
    }

    /**
     * Check if group is active
     */
    public function getIsActiveAttribute()
    {
        return ! $this->end_date || $this->end_date >= now();
    }
}
