<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\HasUuid;

class Assignment extends Model
{
    use HasFactory, HasUuid;

    public $timestamps = false;

    protected $table = 'assignments';

    protected $primaryKey = 'assignment_id';

    protected $fillable = [
        'title',
        'description',
        'group_id',
        'session_id',
        'due_date',
        'teacher_file',
        'created_by',
    ];

    /**
     * Get the group that the assignment belongs to.
     */
    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', 'group_id');
    }

    /**
     * Get the session that the assignment belongs to.
     */
    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id', 'session_id');
    }

    /**
     * Get the submissions for this assignment.
     */
    public function submissions()
    {
        return $this->hasMany(AssignmentSubmission::class, 'assignment_id', 'assignment_id');
    }
}
