<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeacherPayment extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'teacher_id',
        'salary_id',
        'amount',
        'payment_method',
        'payment_date',
        'notes',
        'confirmed_by',
        'uuid',
    ];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id', 'teacher_id');
    }

    public function salary()
    {
        return $this->belongsTo(Salary::class, 'salary_id', 'salary_id');
    }
}
