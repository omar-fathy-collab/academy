<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentGroup extends Model
{
    use HasFactory;

    protected $table = 'student_group';

    protected $primaryKey = 'id';

    protected $fillable = [
        'student_id',
        'group_id',
    ];

    /**
     * Get the student that belongs to the group.
     */
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    /**
     * Get the group that the student belongs to.
     */
    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', 'group_id');
    }
}
