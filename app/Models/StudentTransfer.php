<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentTransfer extends Model
{
    use HasFactory;

    protected $table = 'student_transfers';

    protected $primaryKey = 'transfer_id';

    protected $fillable = [
        'student_id',
        'from_group_id',
        'to_group_id',
        'transfer_date',
        'notes',
        'transferred_by',
    ];

    protected $casts = [
        'transfer_date' => 'date',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    public function fromGroup()
    {
        return $this->belongsTo(Group::class, 'from_group_id', 'group_id');
    }

    public function toGroup()
    {
        return $this->belongsTo(Group::class, 'to_group_id', 'group_id');
    }

    public function transferredBy()
    {
        return $this->belongsTo(User::class, 'transferred_by', 'id');
    }
}
