<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ExerciseAttempt;
use App\Models\LessonExercise;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExerciseSubmissionController extends Controller
{
    public function index(LessonExercise $lessonExercise)
    {
        $this->ensureTeacherOwns($lessonExercise);
        $attempts = $lessonExercise->attempts()->with(['student', 'answers.question'])->latest('submitted_at')->paginate(20);

        return view('teacher.lesson-notes.submissions', compact('lessonExercise', 'attempts'));
    }

    public function edit(LessonExercise $lessonExercise, ExerciseAttempt $attempt)
    {
        $this->ensureTeacherOwns($lessonExercise);
        abort_unless((int) $attempt->lesson_exercise_id === (int) $lessonExercise->id, 403);
        $lessonExercise->load('questions');
        $attempt->load(['student', 'answers.question']);

        foreach ($lessonExercise->questions as $question) {
            $attempt->answers()->firstOrCreate(['exercise_question_id' => $question->id]);
        }

        $attempt->load(['student', 'answers.question']);

        return view('teacher.lesson-notes.mark', compact('lessonExercise', 'attempt'));
    }

    public function update(Request $request, LessonExercise $lessonExercise, ExerciseAttempt $attempt)
    {
        $this->ensureTeacherOwns($lessonExercise);
        abort_unless((int) $attempt->lesson_exercise_id === (int) $lessonExercise->id, 403);

        $validated = $request->validate([
            'marks' => 'required|array',
            'marks.*' => 'numeric|min:0',
            'feedback' => 'nullable|array',
            'final_total_score' => 'required|numeric|min:0',
            'overall_feedback' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $lessonExercise, $attempt, $validated) {
            $attempt = ExerciseAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $lessonExercise->load('questions');
            $awardedTotal = 0;

            foreach ($lessonExercise->questions as $question) {
                $answer = $attempt->answers()->firstOrCreate(['exercise_question_id' => $question->id]);
                $marks = (float) ($validated['marks'][$answer->id] ?? 0);

                abort_if($marks > (float) $question->marks, 422, 'Marks cannot exceed the question maximum.');
                $awardedTotal += $marks;

                $answer->update([
                    'is_correct' => $marks >= (float) $question->marks,
                    'awarded_marks' => $marks,
                    'teacher_feedback' => $validated['feedback'][$answer->id] ?? null,
                    'marked_by' => Auth::id(),
                    'marked_at' => now(),
                ]);
            }

            abort_if((float) $validated['final_total_score'] > $lessonExercise->totalMarks(), 422, 'Total score cannot exceed the exercise total.');

            $attempt->update([
                'auto_score' => 0,
                'manual_score' => $awardedTotal,
                'total_score' => (float) $validated['final_total_score'],
                'overall_feedback' => $request->input('overall_feedback'),
                'status' => ExerciseAttempt::STATUS_MARKED,
                'marked_at' => now(),
            ]);

            $lessonExercise->recalculateCountedAttempt($attempt->student_id);
        });

        return redirect()->route('teacher.exercises.submissions.index', $lessonExercise)->with('success', 'Theory answers marked.');
    }

    public function destroy(LessonExercise $lessonExercise, ExerciseAttempt $attempt)
    {
        $this->ensureTeacherOwns($lessonExercise);
        abort_unless((int) $attempt->lesson_exercise_id === (int) $lessonExercise->id, 403);

        $studentId = $attempt->student_id;
        $attempt->delete();
        $lessonExercise->recalculateCountedAttempt($studentId);

        return back()->with('success', 'Student submission removed from this exercise.');
    }

    public function grantRetry(Request $request, LessonExercise $lessonExercise, User $student)
    {
        $this->ensureTeacherOwns($lessonExercise);
        $request->validate(['reason' => 'nullable|string|max:1000']);

        $lessonExercise->retryGrants()->create([
            'student_id' => $student->id,
            'granted_by' => Auth::id(),
            'extra_attempts' => 1,
            'reason' => $request->input('reason'),
        ]);

        return back()->with('success', 'Additional retry granted.');
    }

    private function ensureTeacherOwns(LessonExercise $exercise): void
    {
        abort_unless((int) $exercise->lessonNote->teacher_id === (int) Auth::id(), 403);
    }
}
