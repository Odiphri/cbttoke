<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExerciseAttempt extends Model
{
    use HasFactory;

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_AWAITING_MARKING = 'awaiting_marking';
    public const STATUS_MARKED = 'marked';

    protected $fillable = [
        'lesson_exercise_id',
        'student_id',
        'attempt_number',
        'status',
        'started_at',
        'submitted_at',
        'marked_at',
        'auto_score',
        'manual_score',
        'total_score',
        'overall_feedback',
        'is_counted',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'marked_at' => 'datetime',
            'auto_score' => 'decimal:2',
            'manual_score' => 'decimal:2',
            'total_score' => 'decimal:2',
            'is_counted' => 'boolean',
        ];
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(LessonExercise::class, 'lesson_exercise_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExerciseAnswer::class);
    }
}
