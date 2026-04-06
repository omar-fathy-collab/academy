<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizAttempt extends Model
{

    use \Illuminate\Database\Eloquent\Factories\HasFactory, \App\Traits\HasUuid;

    protected $table = 'quiz_attempts';

    protected $primaryKey = 'attempt_id';

    public $timestamps = false;

    protected $fillable = [
        'quiz_id',
        'student_id',
        'start_time',
        'end_time',
        'score',
        'status',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    /**
     * Get the quiz that the attempt belongs to.
     */
    public function quiz()
    {
        return $this->belongsTo(Quiz::class, 'quiz_id', 'quiz_id');
    }

    /**
     * Get the student that made the attempt.
     */
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    /**
     * Get the answers for this attempt.
     */
    public function answers()
    {
        return $this->hasMany(QuizAnswer::class, 'attempt_id', 'attempt_id');
    }

    /**
     * Get the user through student relationship.
     */
    public function user()
    {
        return $this->student->user ?? null;
    }
}
