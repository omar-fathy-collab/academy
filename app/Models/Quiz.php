<?php

namespace App\Models;


use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{

    use HasFactory, HasUuid;

    protected $table = 'quizzes';

    protected $primaryKey = 'quiz_id';

    protected $fillable = [
        'session_id',
        'title',
        'description',
        'time_limit',
        'max_attempts',
        'is_active',
        'is_public',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_public' => 'boolean',
    ];

    /**
     * Get the session that the quiz belongs to.
     */
    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id', 'session_id');
    }

    /**
     * Get the questions for this quiz.
     */
    public function questions()
    {
        return $this->hasMany(QuizQuestion::class, 'quiz_id', 'quiz_id');
    }

    /**
     * Get the attempts for this quiz.
     */
    public function attempts()
    {
        return $this->hasMany(QuizAttempt::class, 'quiz_id', 'quiz_id');
    }
    /**
     * Get the user who created the quiz.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

