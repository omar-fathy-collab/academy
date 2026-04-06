<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Salary extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'salaries';

    protected $primaryKey = 'salary_id';

    protected $fillable = [
        'teacher_id',
        'month',
        'group_id',
        'group_revenue',
        'teacher_share',
        'deductions',
        'bonuses',
        'net_salary',
        'status',
        'payment_date',
        'public_token',
        'updated_by',
    ];

    protected $casts = [
        'group_revenue' => 'decimal:2',
        'teacher_share' => 'decimal:2',
        'deductions' => 'decimal:2',
        'bonuses' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id', 'teacher_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', 'group_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }

    // تعديل: منع تكرار group_id وتوليد public_token
    protected static function booted()
    {
        static::creating(function ($salary) {
            // Generate public token
            if (!$salary->public_token) {
                $salary->public_token = \Illuminate\Support\Str::random(40);
            }

            // تحقق إذا كان الراتب موجود بالفعل لنفس المدرس والمجموعة والشهر
            $existing = self::where('group_id', $salary->group_id)
                ->where('teacher_id', $salary->teacher_id)
                ->where('month', $salary->month)
                ->first();
                
            if ($existing) {
                throw new \Exception("Salary record for Teacher ID {$salary->teacher_id}, Group ID {$salary->group_id}, Month {$salary->month} already exists.");
            }
        });
    }
}
