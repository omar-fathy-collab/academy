<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\HasUuid;

class QuizAnswer extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'quiz_answers';

    protected $primaryKey = 'answer_id';

    protected $fillable = [
        'attempt_id',
        'question_id',
        'option_id',
        'answer_text',
        'is_correct',
        'points_earned',
    ];

    public $timestamps = true;

    /**
     * Get the attempt that the answer belongs to.
     */
    public function attempt()
    {
        return $this->belongsTo(QuizAttempt::class, 'attempt_id', 'attempt_id');
    }

    /**
     * Get the question that the answer belongs to.
     */
    public function question()
    {
        return $this->belongsTo(QuizQuestion::class, 'question_id', 'question_id');
    }

    /**
     * Get the option that was selected.
     */
    public function option()
    {
        return $this->belongsTo(Option::class, 'option_id', 'option_id');
    }
}
