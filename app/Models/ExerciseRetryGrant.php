<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseRetryGrant extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_exercise_id',
        'student_id',
        'granted_by',
        'extra_attempts',
        'reason',
    ];

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(LessonExercise::class, 'lesson_exercise_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
