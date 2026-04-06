<?php

// app/Models/Schedule.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $table = 'schedules';

    protected $primaryKey = 'schedule_id';

    public $timestamps = true;

    protected $fillable = [
        'group_id',
        'room_id',
        'day_of_week',
        'start_time',
        'end_time',
        'start_date', // أضف هذا
        'end_date',   // أضف هذا
        'is_active',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'start_date' => 'date',    // أضف هذا
        'end_date' => 'date',      // أضف هذا
        'is_active' => 'boolean',
    ];

    /**
     * Get start time in H:i format
     */
    public function getStartTimeAttribute($value)
    {
        return $value ? \Carbon\Carbon::parse($value)->format('H:i') : null;
    }

    /**
     * Get end time in H:i format
     */
    public function getEndTimeAttribute($value)
    {
        return $value ? \Carbon\Carbon::parse($value)->format('H:i') : null;
    }

    /**
     * Set start time - ensure it's stored properly
     */
    public function setStartTimeAttribute($value)
    {
        $this->attributes['start_time'] = $value ? \Carbon\Carbon::parse($value)->format('H:i:s') : null;
    }

    /**
     * Set end time - ensure it's stored properly
     */
    public function setEndTimeAttribute($value)
    {
        $this->attributes['end_time'] = $value ? \Carbon\Carbon::parse($value)->format('H:i:s') : null;
    }

    /**
     * Set start date - ensure it's stored properly
     */
    public function setStartDateAttribute($value)
    {
        $this->attributes['start_date'] = $value ? \Carbon\Carbon::parse($value)->format('Y-m-d') : null;
    }

    /**
     * Set end date - ensure it's stored properly
     */
    public function setEndDateAttribute($value)
    {
        $this->attributes['end_date'] = $value ? \Carbon\Carbon::parse($value)->format('Y-m-d') : null;
    }

    /**
     * Get the group for this schedule
     */
    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', 'group_id');
    }

    /**
     * Get the room for this schedule
     */
    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id', 'room_id');
    }

    /**
     * Check if schedule is active now (مع الأخذ في الاعتبار تاريخ الجروب)
     */
    public function isActiveNow()
    {
        $now = now();
        $today = $now->format('Y-m-d');

        // تحقق من أن الجروب لا يزال نشطاً (ضمن تاريخ البداية والنهاية)
        $isGroupActive = $this->group &&
                        $this->group->start_date <= $today &&
                        $this->group->end_date >= $today;

        return $this->is_active &&
               $isGroupActive &&
               $this->day_of_week === strtolower($now->englishDayOfWeek) &&
               $this->start_time <= $now->format('H:i:s') &&
               $this->end_time >= $now->format('H:i:s');
    }

    /**
     * Check if schedule is active for a specific date (مع الأخذ في الاعتبار تاريخ الجروب)
     */
    public function isActiveOnDate($date = null)
    {
        $date = $date ? \Carbon\Carbon::parse($date) : now();
        $dateStr = $date->format('Y-m-d');
        $dayOfWeek = strtolower($date->englishDayOfWeek);

        // تحقق من أن الجدولة نشطة في هذا التاريخ
        // نستخدم تواريخ الجدول إذا وجدت، وإلا نرجع لتاريخ الجروب
        $effectiveStartDate = $this->start_date ?? ($this->group->start_date ?? null);
        $effectiveEndDate = $this->end_date ?? ($this->group->end_date ?? null);

        $isDateActive = $effectiveStartDate && $effectiveEndDate &&
                        $effectiveStartDate <= $dateStr &&
                        $effectiveEndDate >= $dateStr;

        return $this->is_active &&
               $isDateActive &&
               strtolower($this->day_of_week) === strtolower($dayOfWeek);
    }

    /**
     * Scope for active schedules (يشمل التحقق من تاريخ الجروب)
     */
    public function scopeFullyActive($query, $date = null)
    {
        $date = $date ? \Carbon\Carbon::parse($date)->format('Y-m-d') : now()->format('Y-m-d');

        return $query->where('is_active', 1)
            ->whereHas('group', function ($q) use ($date) {
                $q->where('start_date', '<=', $date)
                    ->where('end_date', '>=', $date);
            });
    }

    /**
     * Scope for schedules active on specific date
     */
    public function scopeActiveOnDate($query, $date)
    {
        $date = \Carbon\Carbon::parse($date)->format('Y-m-d');
        $dayOfWeek = strtolower(\Carbon\Carbon::parse($date)->englishDayOfWeek);

        return $query->where('is_active', 1)
            ->where('day_of_week', $dayOfWeek)
            ->whereHas('group', function ($q) use ($date) {
                $q->where('start_date', '<=', $date)
                    ->where('end_date', '>=', $date);
            });
    }

    /**
     * Get duration in hours
     */
    public function getDurationAttribute()
    {
        $start = \Carbon\Carbon::parse($this->start_time);
        $end = \Carbon\Carbon::parse($this->end_time);

        return $end->diffInHours($start);
    }

    /**
     * Automatically deactivate schedules when group ends
     */
    public static function deactivateExpiredSchedules()
    {
        $today = now()->format('Y-m-d');

        return static::where('is_active', 1)
            ->whereHas('group', function ($q) use ($today) {
                $q->where('end_date', '<', $today);
            })
            ->update(['is_active' => 0]);
    }
}
