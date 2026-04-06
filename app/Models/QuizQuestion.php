<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{
    use HasFactory;

    protected $table = 'questions';

    protected $primaryKey = 'question_id';

    protected $fillable = [
        'quiz_id',
        'question_text',
        'question_type',
        'points',
        'image_path',
    ];

    /**
     * Get the quiz that the question belongs to.
     */
    public function quiz()
    {
        return $this->belongsTo(Quiz::class, 'quiz_id', 'quiz_id');
    }

    /**
     * Get the options for the question.
     */
    public function options()
    {
        return $this->hasMany(Option::class, 'question_id', 'question_id');
    }
}
