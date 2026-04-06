<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaitingStudent extends Model
{
    use HasFactory;

    // app/Models/WaitingStudent.php
    protected $fillable = [
        'waiting_group_id',
        'student_id',
        'user_id',
        'placement_exam_grade',
        'assigned_level',
        'notes',
        'status',
        'joined_at',
        'converted_at', // ← إضافة هذا
        'converted_to_group_id', // ← وإذا أضفت هذا
        'added_by',
    ];

    protected $casts = [
        'placement_exam_grade' => 'decimal:2',
        'joined_at' => 'datetime',
        'converted_at' => 'datetime', // ← إضافة هذا
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * علاقة الطالب مع المجموعة
     */
    public function waitingGroup()
    {
        return $this->belongsTo(WaitingGroup::class, 'waiting_group_id');
    }

    /**
     * علاقة الطالب مع بيانات الطالب
     */
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    /**
     * علاقة الطالب مع بيانات اليوزر
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * علاقة الطالب مع من أضافه
     */
    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * عرض حالة الطالب مع لون
     */
    public function getStatusBadgeAttribute()
    {
        $badges = [
            'waiting' => '<span class="badge bg-warning text-dark">في الانتظار</span>',
            'contacted' => '<span class="badge bg-info">تم الاتصال</span>',
            'approved' => '<span class="badge bg-success">معتمد</span>',
            'rejected' => '<span class="badge bg-danger">مرفوض</span>',
        ];

        return $badges[$this->status] ?? '<span class="badge bg-secondary">غير معروف</span>';
    }

    /**
     * مستوى الطالب مع لون
     */
    public function getLevelBadgeAttribute()
    {
        $badges = [
            'مبتدئ' => '<span class="badge bg-info">مبتدئ</span>',
            'متوسط' => '<span class="badge bg-primary">متوسط</span>',
            'متقدم' => '<span class="badge bg-success">متقدم</span>',
        ];

        return $badges[$this->assigned_level] ?? '<span class="badge bg-secondary">غير محدد</span>';
    }

    /**
     * تحديث حالة الطالب
     */
    public function updateStatus($status, $notes = null)
    {
        $this->status = $status;

        if ($notes) {
            $this->notes = $this->notes ? $this->notes."\n".$notes : $notes;
        }

        return $this->save();
    }

    /**
     * نقل الطالب إلى مجموعة أخرى
     */
    public function transferToGroup($newGroupId, $notes = null)
    {
        // إنشاء سجل جديد في المجموعة الجديدة
        $newRecord = $this->replicate();
        $newRecord->waiting_group_id = $newGroupId;
        $newRecord->notes = $notes ?: 'تم النقل من مجموعة: '.$this->waitingGroup->group_name;
        $newRecord->save();

        // تحديث حالة السجل القديم
        $this->status = 'transferred';
        $this->notes = $this->notes ? $this->notes."\n".'تم نقله إلى مجموعة أخرى' : 'تم نقله إلى مجموعة أخرى';
        $this->save();

        return $newRecord;
    }

    public function getConvertedAtAttribute($value)
    {
        if (Schema::hasColumn($this->getTable(), 'converted_at')) {
            return $value;
        }

        return null;
    }

    // Safe mutator for converted_at
    public function setConvertedAtAttribute($value)
    {
        if (Schema::hasColumn($this->getTable(), 'converted_at')) {
            $this->attributes['converted_at'] = $value;
        }
    }

    // Similar for converted_to_group_id
    public function getConvertedToGroupIdAttribute($value)
    {
        if (Schema::hasColumn($this->getTable(), 'converted_to_group_id')) {
            return $value;
        }

        return null;
    }

    public function setConvertedToGroupIdAttribute($value)
    {
        if (Schema::hasColumn($this->getTable(), 'converted_to_group_id')) {
            $this->attributes['converted_to_group_id'] = $value;
        }
    }
}
