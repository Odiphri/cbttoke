<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'exercise_attempt_id',
        'exercise_question_id',
        'answer_text',
        'is_correct',
        'awarded_marks',
        'teacher_feedback',
        'marked_by',
        'marked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'awarded_marks' => 'decimal:2',
            'marked_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExerciseAttempt::class, 'exercise_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ExerciseQuestion::class, 'exercise_question_id');
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
