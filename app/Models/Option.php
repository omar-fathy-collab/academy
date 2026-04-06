<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Option extends Model
{
    use HasFactory;

    protected $table = 'options';

    protected $primaryKey = 'option_id';

    protected $fillable = [
        'question_id',
        'option_text',
        'is_correct',
    ];

    /**
     * Get the question that the option belongs to.
     */
    public function question()
    {
        return $this->belongsTo(QuizQuestion::class, 'question_id', 'question_id');
    }
}
