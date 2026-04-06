<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\HasUuid;

class TeacherAdjustment extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'teacher_adjustments';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $fillable = [
        'teacher_id',
        'description',
        'amount',
        'type',
        'adjustment_date',
        'payment_status', // إضافة هذا
        'payment_date',   // إضافة هذا
        'payment_method', // إضافة هذا
        'paid_by',        // إضافة هذا
        'created_by',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'adjustment_date' => 'date',
        'payment_date' => 'date',
    ];

    // العلاقات
    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id', 'teacher_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    // سكوب للمدفوعات
    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopeBonuses($query)
    {
        return $query->where('type', 'bonus');
    }

    public function scopeDeductions($query)
    {
        return $query->where('type', 'deduction');
    }

    // دالة للتحقق من حالة الدفع
    public function isPaid()
    {
        return $this->payment_status === 'paid';
    }

    public function isPending()
    {
        return $this->payment_status === 'pending';
    }

    public function salary()
    {
        return $this->belongsTo(Salary::class, 'salary_id', 'salary_id');
    }
}
