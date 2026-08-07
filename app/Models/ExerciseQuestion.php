<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExerciseQuestion extends Model
{
    use HasFactory;

    public const TYPE_OBJECTIVE = 'objective';
    public const TYPE_TRUE_FALSE = 'true_false';
    public const TYPE_THEORY = 'theory';

    protected $fillable = [
        'lesson_exercise_id',
        'question_type',
        'question_text',
        'options',
        'correct_answer',
        'marking_guide',
        'marks',
        'image_path',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'marks' => 'decimal:2',
        ];
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(LessonExercise::class, 'lesson_exercise_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExerciseAnswer::class);
    }

    public function isAutoMarked(): bool
    {
        return in_array($this->question_type, [self::TYPE_OBJECTIVE, self::TYPE_TRUE_FALSE], true);
    }

    public function isCorrect(?string $answer): bool
    {
        if ($answer === null) {
            return false;
        }

        return strtoupper(trim($answer)) === strtoupper(trim((string) $this->correct_answer));
    }
}
