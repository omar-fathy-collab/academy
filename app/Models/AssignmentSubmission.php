<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\HasUuid;

class AssignmentSubmission extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'assignment_submissions';

    protected $primaryKey = 'submission_id';

    public $timestamps = false;

    protected $fillable = [
        'assignment_id',
        'student_id',
        'file_path',
        'submission_date',
        'score',
        'feedback',
        'status',
        'graded_at',
    ];

    /**
     * Get the assignment that was submitted.
     */
    public function assignment()
    {
        return $this->belongsTo(Assignment::class, 'assignment_id', 'assignment_id');
    }

    /**
     * Get the student who submitted the assignment.
     */
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }
}
