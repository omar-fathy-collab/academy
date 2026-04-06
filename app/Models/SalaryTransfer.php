<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryTransfer extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'salary_transfers';

    protected $primaryKey = 'transfer_id';

    protected $fillable = [
        'source_salary_id',
        'source_teacher_id',
        'target_teacher_id',
        'transfer_amount',
        'payment_status',
        'paid_amount',
        'notes',
        'transferred_by',
    ];

    protected $casts = [
        'transfer_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function sourceSalary()
    {
        return $this->belongsTo(Salary::class, 'source_salary_id', 'salary_id');
    }

    public function sourceTeacher()
    {
        return $this->belongsTo(Teacher::class, 'source_teacher_id', 'teacher_id');
    }

    public function targetTeacher()
    {
        return $this->belongsTo(Teacher::class, 'target_teacher_id', 'teacher_id');
    }

    public function transferredByUser()
    {
        return $this->belongsTo(User::class, 'transferred_by', 'id');
    }

    /**
     * تحديث حالة الدفع للتحويل
     */
    public function updatePaymentStatus($amount)
    {
        $this->paid_amount += $amount;

        if ($this->paid_amount >= $this->transfer_amount) {
            $this->payment_status = 'paid';
        } elseif ($this->paid_amount > 0) {
            $this->payment_status = 'partial';
        }

        $this->save();

        return $this;
    }
}
