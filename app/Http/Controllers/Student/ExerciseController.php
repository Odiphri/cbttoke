<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ExerciseAnswer;
use App\Models\ExerciseAttempt;
use App\Models\ExerciseQuestion;
use App\Models\LessonExercise;
use App\Models\LessonNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExerciseController extends Controller
{
    public function index(Request $request)
    {
        $student = Auth::user();
        $exercises = LessonExercise::with(['lessonNote.subject', 'attempts' => fn ($query) => $query->where('student_id', $student->id)])
            ->whereHas('lessonNote', fn ($query) => $query->approved()->where('school_class_id', $student->school_class_id))
            ->orderBy('due_at')
            ->get();

        return view('student.exercises.index', compact('exercises'));
    }

    public function show(LessonExercise $lessonExercise)
    {
        $student = Auth::user();
        $this->ensureCanView($lessonExercise, $student);

        $attempt = $lessonExercise->attempts()
            ->where('student_id', $student->id)
            ->where('status', ExerciseAttempt::STATUS_IN_PROGRESS)
            ->latest()
            ->first();

        if (! $attempt) {
            abort_unless($lessonExercise->canStartAttempt($student), 403, 'This exercise is not available.');
            $attempt = DB::transaction(function () use ($lessonExercise, $student) {
                $exercise = LessonExercise::whereKey($lessonExercise->id)->lockForUpdate()->firstOrFail();
                abort_unless($exercise->canStartAttempt($student), 403);
                $nextNumber = ((int) $exercise->attempts()->where('student_id', $student->id)->max('attempt_number')) + 1;

                return $exercise->attempts()->create([
                    'student_id' => $student->id,
                    'attempt_number' => $nextNumber,
                    'status' => ExerciseAttempt::STATUS_IN_PROGRESS,
                    'started_at' => now(),
                ]);
            });
        }

        $questions = $lessonExercise->questions()->get();
        if ($lessonExercise->shuffle_questions) {
            $questions = $questions->shuffle();
        }
        $attempt->load('answers');

        return view('student.exercises.take', compact('lessonExercise', 'attempt', 'questions'));
    }

    public function save(Request $request, LessonExercise $lessonExercise, ExerciseAttempt $attempt)
    {
        $this->ensureAttemptOwner($lessonExercise, $attempt);
        abort_unless($attempt->status === ExerciseAttempt::STATUS_IN_PROGRESS, 403);
        $this->storeAnswers($request, $lessonExercise, $attempt);

        return back()->with('success', 'Answers saved.');
    }

    public function submit(Request $request, LessonExercise $lessonExercise, ExerciseAttempt $attempt)
    {
        $this->ensureAttemptOwner($lessonExercise, $attempt);
        $lessonExercise->loadMissing('questions');

        DB::transaction(function () use ($request, $lessonExercise, $attempt) {
            $attempt = ExerciseAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($attempt->status !== ExerciseAttempt::STATUS_IN_PROGRESS) {
                return;
            }

            abort_unless($lessonExercise->isOpen() && ! $lessonExercise->isClosed(), 403);
            $lessonExercise->unsetRelation('questions');
            $lessonExercise->load('questions');
            $this->storeAnswers($request, $lessonExercise, $attempt);

            $autoScore = 0;
            $hasTheory = false;
            foreach ($lessonExercise->questions as $question) {
                $answer = $attempt->answers()->firstOrCreate(['exercise_question_id' => $question->id]);
                if ($question->question_type === ExerciseQuestion::TYPE_THEORY) {
                    $hasTheory = true;
                    continue;
                }

                $isCorrect = $question->isCorrect($answer->answer_text);
                $marks = $isCorrect ? (float) $question->marks : 0;
                $autoScore += $marks;
                $answer->update(['is_correct' => $isCorrect, 'awarded_marks' => $marks]);
            }

            $attempt->update([
                'status' => $hasTheory ? ExerciseAttempt::STATUS_AWAITING_MARKING : ExerciseAttempt::STATUS_SUBMITTED,
                'submitted_at' => now(),
                'auto_score' => $autoScore,
                'manual_score' => 0,
                'total_score' => $autoScore,
            ]);

            $lessonExercise->recalculateCountedAttempt($attempt->student_id);
        });

        return redirect()->route('student.exercises.result', [$lessonExercise, $attempt])->with('success', 'Exercise submitted.');
    }

    public function result(LessonExercise $lessonExercise, ExerciseAttempt $attempt)
    {
        $this->ensureAttemptOwner($lessonExercise, $attempt);
        abort_if($attempt->status === ExerciseAttempt::STATUS_IN_PROGRESS, 403);
        $attempt->load(['answers.question', 'student']);

        return view('student.exercises.result', compact('lessonExercise', 'attempt'));
    }

    private function storeAnswers(Request $request, LessonExercise $exercise, ExerciseAttempt $attempt): void
    {
        $answers = $request->validate(['answers' => 'nullable|array'])['answers'] ?? [];

        foreach ($exercise->questions as $question) {
            if (! array_key_exists($question->id, $answers)) {
                continue;
            }

            $attempt->answers()->updateOrCreate(
                ['exercise_question_id' => $question->id],
                ['answer_text' => is_array($answers[$question->id]) ? null : (string) $answers[$question->id]]
            );
        }
    }

    private function ensureCanView(LessonExercise $exercise, $student): void
    {
        abort_unless($exercise->lessonNote?->isApproved() && (int) $exercise->lessonNote->school_class_id === (int) $student->school_class_id, 403);
    }

    private function ensureAttemptOwner(LessonExercise $exercise, ExerciseAttempt $attempt): void
    {
        abort_unless((int) $attempt->lesson_exercise_id === (int) $exercise->id && (int) $attempt->student_id === (int) Auth::id(), 403);
        $this->ensureCanView($exercise, Auth::user());
    }
}
