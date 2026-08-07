<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LessonExercise extends Model
{
    use HasFactory;

    public const ATTEMPT_ONE = 'one';
    public const ATTEMPT_LIMITED = 'limited';
    public const ATTEMPT_UNLIMITED = 'unlimited';

    public const SCORE_HIGHEST = 'highest';
    public const SCORE_LATEST = 'latest';
    public const SCORE_FIRST = 'first';

    protected $fillable = [
        'lesson_note_id',
        'title',
        'instructions',
        'opens_at',
        'due_at',
        'allow_late_submission',
        'shuffle_questions',
        'shuffle_options',
        'show_score_immediately',
        'reveal_correct_answers',
        'attempt_mode',
        'max_attempts',
        'score_selection_method',
    ];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'due_at' => 'datetime',
            'allow_late_submission' => 'boolean',
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
            'show_score_immediately' => 'boolean',
            'reveal_correct_answers' => 'boolean',
        ];
    }

    public function lessonNote(): BelongsTo
    {
        return $this->belongsTo(LessonNote::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ExerciseQuestion::class)->orderBy('display_order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExerciseAttempt::class);
    }

    public function retryGrants(): HasMany
    {
        return $this->hasMany(ExerciseRetryGrant::class);
    }

    public function totalMarks(): float
    {
        return (float) $this->questions()->sum('marks');
    }

    public function isOpen(): bool
    {
        return ! $this->opens_at || now()->gte($this->opens_at);
    }

    public function isClosed(): bool
    {
        return $this->due_at && now()->gt($this->due_at) && ! $this->allow_late_submission;
    }

    public function allowedAttemptsFor(User $student): ?int
    {
        if ($this->attempt_mode === self::ATTEMPT_UNLIMITED) {
            return null;
        }

        $base = $this->attempt_mode === self::ATTEMPT_LIMITED ? (int) $this->max_attempts : 1;
        $extra = (int) $this->retryGrants()->where('student_id', $student->id)->sum('extra_attempts');

        return $base + $extra;
    }

    public function attemptsUsedBy(User $student): int
    {
        return $this->attempts()->where('student_id', $student->id)->where('status', '!=', ExerciseAttempt::STATUS_IN_PROGRESS)->count();
    }

    public function canStartAttempt(User $student): bool
    {
        if (! $this->lessonNote?->isApproved() || ! $this->isOpen() || $this->isClosed()) {
            return false;
        }

        $allowed = $this->allowedAttemptsFor($student);

        return $allowed === null || $this->attemptsUsedBy($student) < $allowed;
    }

    public function recalculateCountedAttempt(User|int $student): void
    {
        $studentId = $student instanceof User ? $student->id : $student;
        $attempts = $this->attempts()
            ->where('student_id', $studentId)
            ->whereIn('status', [ExerciseAttempt::STATUS_SUBMITTED, ExerciseAttempt::STATUS_MARKED])
            ->orderBy('attempt_number')
            ->get();

        $this->attempts()->where('student_id', $studentId)->update(['is_counted' => false]);

        if ($attempts->isEmpty()) {
            return;
        }

        $counted = match ($this->score_selection_method) {
            self::SCORE_FIRST => $attempts->first(),
            self::SCORE_LATEST => $attempts->last(),
            default => $attempts->sortByDesc('total_score')->first(),
        };

        $counted->update(['is_counted' => true]);
    }
}
