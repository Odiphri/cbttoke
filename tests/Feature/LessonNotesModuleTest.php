<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\ExerciseAttempt;
use App\Models\ExerciseQuestion;
use App\Models\LessonExercise;
use App\Models\LessonNote;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LessonNotesModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_teacher_can_create_submit_and_hod_can_approve_note(): void
    {
        [$teacher, $class, $subject] = $this->assignedTeacherContext();
        $hod = $this->user('hod-lesson', 'hod');
        $student = $this->user('student-lesson', 'student', ['school_class_id' => $class->id]);

        $this->actingAs($teacher)
            ->post(route('teacher.lesson-notes.store'), $this->notePayload($class, $subject, ['action' => 'submit']))
            ->assertRedirect();

        $note = LessonNote::firstOrFail();
        $this->assertSame(LessonNote::STATUS_PENDING, $note->status);
        $this->assertStringNotContainsString('<script>', $note->main_content);

        $this->actingAs($student)->get(route('student.lesson-notes.index'))->assertOk()->assertDontSee('Linear Equations');

        $this->actingAs($hod)
            ->post(route('hod.lesson-notes.approve', $note))
            ->assertRedirect();

        $note->refresh();
        $this->assertSame(LessonNote::STATUS_APPROVED, $note->status);
        $this->assertNotNull($note->published_at);
        $this->assertDatabaseHas('lesson_note_reviews', [
            'lesson_note_id' => $note->id,
            'reviewer_id' => $hod->id,
            'action' => LessonNote::STATUS_APPROVED,
        ]);

        $this->actingAs($student)->get(route('student.lesson-notes.index'))->assertOk()->assertSee('Linear Equations');
    }

    public function test_teacher_cannot_create_note_for_unassigned_class_subject_or_edit_locked_notes(): void
    {
        [$teacher, $class, $subject] = $this->assignedTeacherContext();
        $outsideClass = SchoolClass::create(['name' => 'JSS2A', 'level' => 'JSS2', 'stream' => 'A', 'is_active' => true]);
        $outsideSubject = Subject::create(['name' => 'English', 'code' => 'ENG-LN', 'school_class_id' => $outsideClass->id, 'is_active' => true]);

        $this->actingAs($teacher)
            ->post(route('teacher.lesson-notes.store'), $this->notePayload($outsideClass, $outsideSubject))
            ->assertRedirect();

        $this->assertDatabaseMissing('lesson_notes', [
            'school_class_id' => $outsideClass->id,
            'subject_id' => $outsideSubject->id,
        ]);

        $note = LessonNote::create($this->noteRecord($teacher, $class, $subject, ['status' => LessonNote::STATUS_PENDING]));

        $this->actingAs($teacher)
            ->get(route('teacher.lesson-notes.edit', $note))
            ->assertRedirect();
    }

    public function test_return_and_reject_require_reasons_and_preserve_review_history(): void
    {
        [$teacher, $class, $subject] = $this->assignedTeacherContext();
        $hod = $this->user('hod-review', 'hod');
        $admin = $this->user('admin-review', 'admin');
        $note = LessonNote::create($this->noteRecord($teacher, $class, $subject, ['status' => LessonNote::STATUS_PENDING]));

        $this->actingAs($hod)
            ->post(route('hod.lesson-notes.return', $note), ['comments' => ''])
            ->assertSessionHasErrors('comments');

        $this->actingAs($hod)
            ->post(route('hod.lesson-notes.return', $note), ['comments' => 'Add examples.'])
            ->assertRedirect();

        $note->fresh()->update(['status' => LessonNote::STATUS_PENDING]);

        $this->actingAs($admin)
            ->post(route('admin.lesson-notes.reject', $note), ['comments' => 'Incomplete work.'])
            ->assertRedirect();

        $this->assertSame(2, $note->fresh()->reviews()->count());
    }

    public function test_admin_can_accept_returned_lesson_note(): void
    {
        [$teacher, $class, $subject] = $this->assignedTeacherContext();
        $admin = $this->user('admin-accept-returned', 'admin');
        $note = LessonNote::create($this->noteRecord($teacher, $class, $subject, [
            'status' => LessonNote::STATUS_RETURNED,
        ]));

        $this->actingAs($admin)
            ->get(route('admin.lesson-notes.show', $note))
            ->assertOk()
            ->assertSee('Accept / Approve');

        $this->actingAs($admin)
            ->post(route('admin.lesson-notes.approve', $note))
            ->assertRedirect()
            ->assertSessionHas('success');

        $note->refresh();

        $this->assertSame(LessonNote::STATUS_APPROVED, $note->status);
        $this->assertNotNull($note->published_at);
    }

    public function test_exercise_auto_marks_objective_and_true_false_then_teacher_marks_theory(): void
    {
        [$teacher, $class, $subject] = $this->assignedTeacherContext();
        $student = $this->user('student-exercise', 'student', ['school_class_id' => $class->id]);
        [$exercise, $objective, $trueFalse, $theory] = $this->approvedExercise($teacher, $class, $subject);

        $this->actingAs($student)->get(route('student.exercises.show', $exercise))->assertOk();
        $attempt = ExerciseAttempt::where('lesson_exercise_id', $exercise->id)->where('student_id', $student->id)->firstOrFail();

        $this->actingAs($student)
            ->post(route('student.exercises.submit', [$exercise, $attempt]), [
                'answers' => [
                    $objective->id => 'B',
                    $trueFalse->id => 'true',
                    $theory->id => 'Because balance is maintained.',
                ],
            ])
            ->assertRedirect();

        $attempt->refresh();
        $this->assertSame(ExerciseAttempt::STATUS_AWAITING_MARKING, $attempt->status);
        $this->assertEquals(3, (float) $attempt->auto_score);
        $this->assertEquals(3, (float) $attempt->total_score);

        $theoryAnswer = $attempt->answers()->where('exercise_question_id', $theory->id)->firstOrFail();

        $this->actingAs($teacher)
            ->put(route('teacher.exercises.submissions.update', [$exercise, $attempt]), [
                'marks' => [$theoryAnswer->id => 4],
                'feedback' => [$theoryAnswer->id => 'Good.'],
                'final_total_score' => 7,
                'overall_feedback' => 'Well done.',
            ])
            ->assertRedirect();

        $attempt->refresh();
        $this->assertSame(ExerciseAttempt::STATUS_MARKED, $attempt->status);
        $this->assertEquals(7, (float) $attempt->total_score);
        $this->assertTrue($attempt->is_counted);
    }

    public function test_teacher_can_fail_student_answer_with_zero_mark(): void
    {
        [$teacher, $class, $subject] = $this->assignedTeacherContext();
        $student = $this->user('student-failed-answer', 'student', ['school_class_id' => $class->id]);
        [$exercise, $objective, $trueFalse, $theory] = $this->approvedExercise($teacher, $class, $subject);
        $attempt = $exercise->attempts()->create([
            'student_id' => $student->id,
            'attempt_number' => 1,
            'status' => ExerciseAttempt::STATUS_AWAITING_MARKING,
            'started_at' => now(),
            'submitted_at' => now(),
            'total_score' => 3,
        ]);
        $objectiveAnswer = $attempt->answers()->create(['exercise_question_id' => $objective->id, 'answer_text' => 'B', 'awarded_marks' => 2]);
        $trueFalseAnswer = $attempt->answers()->create(['exercise_question_id' => $trueFalse->id, 'answer_text' => 'true', 'awarded_marks' => 1]);
        $theoryAnswer = $attempt->answers()->create(['exercise_question_id' => $theory->id, 'answer_text' => 'Weak answer.']);

        $this->actingAs($teacher)
            ->put(route('teacher.exercises.submissions.update', [$exercise, $attempt]), [
                'marks' => [
                    $objectiveAnswer->id => 2,
                    $trueFalseAnswer->id => 1,
                    $theoryAnswer->id => 0,
                ],
                'feedback' => [
                    $theoryAnswer->id => 'Correct answer: mention equality.',
                ],
                'final_total_score' => 3,
                'overall_feedback' => 'Theory failed.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $attempt->refresh();
        $theoryAnswer->refresh();

        $this->assertSame(ExerciseAttempt::STATUS_MARKED, $attempt->status);
        $this->assertFalse($theoryAnswer->is_correct);
        $this->assertEquals(0, (float) $theoryAnswer->awarded_marks);
        $this->assertSame('Correct answer: mention equality.', $theoryAnswer->teacher_feedback);
        $this->assertNotNull($theoryAnswer->marked_at);
    }

    public function test_attempt_limits_unlimited_and_retry_grants_are_enforced(): void
    {
        [$teacher, $class, $subject] = $this->assignedTeacherContext();
        $student = $this->user('student-attempts', 'student', ['school_class_id' => $class->id]);
        [$exercise, $question] = $this->approvedObjectiveExercise($teacher, $class, $subject, ['attempt_mode' => LessonExercise::ATTEMPT_ONE]);

        $this->submitObjectiveAttempt($student, $exercise, $question, 'A');
        $this->actingAs($student)->get(route('student.exercises.show', $exercise))->assertRedirect();
        $this->assertSame(1, $exercise->attempts()->where('student_id', $student->id)->count());

        $this->actingAs($teacher)->post(route('teacher.exercises.retry', [$exercise, $student]))->assertRedirect();
        $this->submitObjectiveAttempt($student, $exercise, $question, 'B');
        $this->assertSame(2, $exercise->attempts()->where('student_id', $student->id)->count());

        [$unlimited] = $this->approvedObjectiveExercise($teacher, $class, $subject, ['attempt_mode' => LessonExercise::ATTEMPT_UNLIMITED], 'Unlimited Drill');
        $this->assertTrue($unlimited->canStartAttempt($student));
    }

    public function test_student_and_teacher_isolation_and_prefect_access(): void
    {
        [$teacher, $class, $subject] = $this->assignedTeacherContext();
        $otherTeacher = $this->user('other-teacher', 'teacher');
        $otherClass = SchoolClass::create(['name' => 'SS1A', 'level' => 'SS1', 'stream' => 'A', 'is_active' => true]);
        $student = $this->user('student-own-class', 'student', ['school_class_id' => $class->id]);
        $otherStudent = $this->user('student-other-class', 'student', ['school_class_id' => $otherClass->id]);
        $prefect = $this->user('prefect-own-class', 'prefect', ['school_class_id' => $class->id]);
        [$exercise] = $this->approvedObjectiveExercise($teacher, $class, $subject);

        $this->actingAs($otherStudent)->get(route('student.lesson-notes.index'))->assertOk()->assertDontSee('Objective Drill');
        $this->actingAs($prefect)->get(route('prefect.lesson-notes.index'))->assertOk()->assertSee('Objective Drill');
        $this->actingAs($otherTeacher)->get(route('teacher.exercises.submissions.index', $exercise))->assertRedirect();
        $this->actingAs($student)->get(route('student.exercises.show', $exercise))->assertOk();
    }

    public function test_teacher_can_generate_lesson_note_draft_with_ai(): void
    {
        [$teacher, $class, $subject] = $this->assignedTeacherContext();

        $this->mock(AIService::class, function ($mock) {
            $mock->shouldReceive('generateLessonNoteDraft')->once()->andReturn([
                'title' => 'AI Algebra Note',
                'topic' => 'Algebra',
                'previous_knowledge' => 'Counting numbers',
                'learning_objectives' => 'Solve simple equations',
                'teaching_materials' => 'Board and marker',
                'introduction' => 'Recall arithmetic',
                'main_content' => '<p>AI note body</p><script>alert(1)</script>',
                'evaluation' => 'Class exercise',
                'conclusion' => 'Summary',
                'assignment' => 'Practice',
            ]);
        });

        $this->actingAs($teacher)
            ->postJson(route('teacher.lesson-notes.ai-draft'), [
                'school_class_id' => $class->id,
                'subject_id' => $subject->id,
                'week_number' => 1,
                'topic' => 'Algebra',
            ])
            ->assertOk()
            ->assertJsonPath('draft.title', 'AI Algebra Note')
            ->assertJsonMissing(['<script>']);
    }

    public function test_teacher_can_generate_exercise_questions_with_ai(): void
    {
        [$teacher, $class, $subject] = $this->assignedTeacherContext();
        $note = LessonNote::create($this->noteRecord($teacher, $class, $subject));
        $exercise = $note->exercise()->create([
            'title' => 'AI Drill',
            'attempt_mode' => LessonExercise::ATTEMPT_ONE,
            'score_selection_method' => LessonExercise::SCORE_HIGHEST,
        ]);

        $this->mock(AIService::class, function ($mock) {
            $mock->shouldReceive('generateLessonExerciseQuestions')->once()->andReturn([
                [
                    'question_type' => ExerciseQuestion::TYPE_OBJECTIVE,
                    'question_text' => '<p>What is 2 + 2?</p>',
                    'options' => ['A' => '3', 'B' => '4', 'C' => '5', 'D' => '6'],
                    'correct_answer' => 'B',
                    'marking_guide' => null,
                    'marks' => 2,
                ],
                [
                    'question_type' => ExerciseQuestion::TYPE_THEORY,
                    'question_text' => '<p>Explain balance.</p>',
                    'options' => null,
                    'correct_answer' => null,
                    'marking_guide' => 'Mention equality.',
                    'marks' => 4,
                ],
            ]);
        });

        $this->actingAs($teacher)
            ->postJson(route('teacher.lesson-notes.questions.ai-generate', $note), [
                'topic' => 'Equations',
                'number_of_questions' => 2,
                'difficulty' => 'medium',
                'marks_per_question' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(2, $exercise->questions()->count());
    }

    public function test_teacher_can_continue_lesson_note_with_ai(): void
    {
        [$teacher, $class, $subject] = $this->assignedTeacherContext();

        $this->mock(AIService::class, function ($mock) {
            $mock->shouldReceive('continueLessonNote')->once()->andReturn([
                'main_content' => '<h3>More Examples</h3><p>Extra lesson content.</p><script>alert(1)</script>',
            ]);
        });

        $this->actingAs($teacher)
            ->postJson(route('teacher.lesson-notes.continue-draft'), [
                'school_class_id' => $class->id,
                'subject_id' => $subject->id,
                'topic' => 'Algebra',
                'existing_content' => '<p>Existing note.</p>',
                'target_words' => 4000,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissing(['<script>']);
    }

    public function test_teacher_can_remove_student_submission_from_exercise(): void
    {
        [$teacher, $class, $subject] = $this->assignedTeacherContext();
        $student = $this->user('student-remove-submission', 'student', ['school_class_id' => $class->id]);
        [$exercise, $question] = $this->approvedObjectiveExercise($teacher, $class, $subject);

        $attempt = $exercise->attempts()->create([
            'student_id' => $student->id,
            'attempt_number' => 1,
            'status' => ExerciseAttempt::STATUS_SUBMITTED,
            'started_at' => now(),
            'submitted_at' => now(),
            'total_score' => 5,
            'is_counted' => true,
        ]);
        $attempt->answers()->create([
            'exercise_question_id' => $question->id,
            'answer_text' => 'A',
            'is_correct' => true,
            'awarded_marks' => 5,
        ]);

        $this->actingAs($teacher)
            ->delete(route('teacher.exercises.submissions.destroy', [$exercise, $attempt]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('exercise_attempts', ['id' => $attempt->id]);
        $this->assertDatabaseMissing('exercise_answers', ['exercise_attempt_id' => $attempt->id]);
    }

    public function test_teacher_can_edit_already_marked_submission(): void
    {
        [$teacher, $class, $subject] = $this->assignedTeacherContext();
        $student = $this->user('student-edit-marked', 'student', ['school_class_id' => $class->id]);
        [$exercise, $objective, $trueFalse, $theory] = $this->approvedExercise($teacher, $class, $subject);
        $attempt = $exercise->attempts()->create([
            'student_id' => $student->id,
            'attempt_number' => 1,
            'status' => ExerciseAttempt::STATUS_MARKED,
            'started_at' => now(),
            'submitted_at' => now(),
            'marked_at' => now(),
            'total_score' => 3,
        ]);
        $objectiveAnswer = $attempt->answers()->create(['exercise_question_id' => $objective->id, 'answer_text' => 'B', 'awarded_marks' => 2]);
        $trueFalseAnswer = $attempt->answers()->create(['exercise_question_id' => $trueFalse->id, 'answer_text' => 'true', 'awarded_marks' => 1]);
        $theoryAnswer = $attempt->answers()->create(['exercise_question_id' => $theory->id, 'answer_text' => 'Balance.', 'awarded_marks' => 0]);

        $this->actingAs($teacher)
            ->put(route('teacher.exercises.submissions.update', [$exercise, $attempt]), [
                'marks' => [
                    $objectiveAnswer->id => 2,
                    $trueFalseAnswer->id => 1,
                    $theoryAnswer->id => 4,
                ],
                'feedback' => [
                    $theoryAnswer->id => 'Improved.',
                ],
                'final_total_score' => 7,
                'overall_feedback' => 'Updated.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $attempt->refresh();
        $theoryAnswer->refresh();

        $this->assertSame(ExerciseAttempt::STATUS_MARKED, $attempt->status);
        $this->assertEquals(7, (float) $attempt->total_score);
        $this->assertSame('Improved.', $theoryAnswer->teacher_feedback);
    }

    public function test_teacher_can_create_note_with_exercise_questions_before_saving_note(): void
    {
        [$teacher, $class, $subject] = $this->assignedTeacherContext();

        $this->actingAs($teacher)
            ->post(route('teacher.lesson-notes.store'), array_merge($this->notePayload($class, $subject, [
                'has_exercise' => 1,
                'exercise_builder_touched' => 1,
                'exercise_title' => 'Inline Algebra Exercise',
                'attempt_mode' => LessonExercise::ATTEMPT_LIMITED,
                'max_attempts' => 2,
                'score_selection_method' => LessonExercise::SCORE_HIGHEST,
                'show_score_immediately' => 1,
                'exercise_instructions' => '<p>Answer all questions.</p>',
            ]), [
                'exercise_questions' => [
                    [
                        'question_type' => ExerciseQuestion::TYPE_OBJECTIVE,
                        'question_text' => '<p>What is 2 + 2?</p>',
                        'option_a' => '<p>3</p>',
                        'option_b' => '<p>4</p>',
                        'option_c' => '<p>5</p>',
                        'option_d' => '<p>6</p>',
                        'correct_answer' => 'B',
                        'marks' => 2,
                    ],
                    [
                        'question_type' => ExerciseQuestion::TYPE_THEORY,
                        'question_text' => '<p>Explain equality.</p>',
                        'marking_guide' => '<p>Mentions both sides being balanced.</p>',
                        'marks' => 3,
                    ],
                ],
            ]))
            ->assertRedirect();

        $note = LessonNote::with('exercise.questions')->firstOrFail();

        $this->assertSame('Inline Algebra Exercise', $note->exercise->title);
        $this->assertSame(2, $note->exercise->questions->count());
        $this->assertSame('<p>4</p>', $note->exercise->questions->first()->options['B']);
    }

    public function test_teacher_can_edit_note_settings_without_replacing_questions_after_submissions(): void
    {
        [$teacher, $class, $subject] = $this->assignedTeacherContext();
        $student = $this->user('student-submitted-edit', 'student', ['school_class_id' => $class->id]);
        $note = LessonNote::create($this->noteRecord($teacher, $class, $subject));
        $exercise = $note->exercise()->create([
            'title' => 'Submitted Exercise',
            'attempt_mode' => LessonExercise::ATTEMPT_ONE,
            'score_selection_method' => LessonExercise::SCORE_HIGHEST,
        ]);
        $exercise->questions()->create([
            'question_type' => ExerciseQuestion::TYPE_OBJECTIVE,
            'question_text' => '<p>Original question?</p>',
            'options' => ['A' => 'One', 'B' => 'Two', 'C' => 'Three', 'D' => 'Four'],
            'correct_answer' => 'A',
            'marks' => 1,
            'display_order' => 1,
        ]);
        $exercise->attempts()->create([
            'student_id' => $student->id,
            'attempt_number' => 1,
            'status' => ExerciseAttempt::STATUS_SUBMITTED,
            'started_at' => now(),
            'submitted_at' => now(),
        ]);

        $this->actingAs($teacher)
            ->put(route('teacher.lesson-notes.update', $note), array_merge($this->notePayload($class, $subject, [
                'title' => 'Edited After Submission',
                'has_exercise' => 1,
                'exercise_builder_touched' => 1,
                'exercise_title' => 'Updated Settings Only',
                'attempt_mode' => LessonExercise::ATTEMPT_LIMITED,
                'max_attempts' => 2,
                'score_selection_method' => LessonExercise::SCORE_HIGHEST,
            ]), [
                'exercise_questions' => [
                    [
                        'question_type' => ExerciseQuestion::TYPE_OBJECTIVE,
                        'question_text' => '<p>Replacement question?</p>',
                        'option_a' => 'A',
                        'option_b' => 'B',
                        'option_c' => 'C',
                        'option_d' => 'D',
                        'correct_answer' => 'B',
                        'marks' => 5,
                    ],
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHas('info');

        $note->refresh();
        $exercise->refresh();

        $this->assertSame('Edited After Submission', $note->title);
        $this->assertSame('Updated Settings Only', $exercise->title);
        $this->assertSame(1, $exercise->questions()->count());
        $this->assertSame('<p>Original question?</p>', $exercise->questions()->first()->question_text);
    }

    private function assignedTeacherContext(): array
    {
        AcademicSession::create(['academic_year' => '2026/2027', 'term' => 'First Term', 'is_active' => true]);
        $teacher = $this->user('teacher-lesson-' . uniqid(), 'teacher');
        $class = SchoolClass::create(['name' => 'JSS1A', 'level' => 'JSS1', 'stream' => 'A', 'is_active' => true]);
        $subject = Subject::create(['name' => 'Mathematics', 'code' => 'MTH-LN-' . uniqid(), 'school_class_id' => $class->id, 'is_active' => true]);
        $teacher->teachingSubjects()->attach($subject->id, ['school_class_id' => $class->id]);

        return [$teacher, $class, $subject];
    }

    private function approvedExercise(User $teacher, SchoolClass $class, Subject $subject): array
    {
        $note = LessonNote::create($this->noteRecord($teacher, $class, $subject, [
            'title' => 'Approved Algebra',
            'topic' => 'Approved Algebra',
            'status' => LessonNote::STATUS_APPROVED,
            'approved_at' => now(),
            'published_at' => now(),
        ]));
        $exercise = $note->exercise()->create([
            'title' => 'Algebra Exercise',
            'attempt_mode' => LessonExercise::ATTEMPT_ONE,
            'score_selection_method' => LessonExercise::SCORE_HIGHEST,
        ]);
        $objective = $exercise->questions()->create([
            'question_type' => ExerciseQuestion::TYPE_OBJECTIVE,
            'question_text' => '2 + 2?',
            'options' => ['A' => '3', 'B' => '4', 'C' => '5', 'D' => '6'],
            'correct_answer' => 'B',
            'marks' => 2,
            'display_order' => 1,
        ]);
        $trueFalse = $exercise->questions()->create([
            'question_type' => ExerciseQuestion::TYPE_TRUE_FALSE,
            'question_text' => 'Zero is an integer.',
            'correct_answer' => 'true',
            'marks' => 1,
            'display_order' => 2,
        ]);
        $theory = $exercise->questions()->create([
            'question_type' => ExerciseQuestion::TYPE_THEORY,
            'question_text' => 'Explain balance.',
            'marking_guide' => 'Look for equality.',
            'marks' => 4,
            'display_order' => 3,
        ]);

        return [$exercise, $objective, $trueFalse, $theory];
    }

    private function approvedObjectiveExercise(User $teacher, SchoolClass $class, Subject $subject, array $overrides = [], string $title = 'Objective Drill'): array
    {
        static $week = 2;

        $note = LessonNote::create($this->noteRecord($teacher, $class, $subject, [
            'title' => $title,
            'topic' => $title,
            'week_number' => $week++,
            'status' => LessonNote::STATUS_APPROVED,
            'approved_at' => now(),
            'published_at' => now(),
        ]));
        $exercise = $note->exercise()->create(array_merge([
            'title' => $title,
            'attempt_mode' => LessonExercise::ATTEMPT_ONE,
            'score_selection_method' => LessonExercise::SCORE_HIGHEST,
        ], $overrides));
        $question = $exercise->questions()->create([
            'question_type' => ExerciseQuestion::TYPE_OBJECTIVE,
            'question_text' => 'Pick A.',
            'options' => ['A' => 'Right', 'B' => 'Wrong', 'C' => 'Wrong', 'D' => 'Wrong'],
            'correct_answer' => 'A',
            'marks' => 5,
            'display_order' => 1,
        ]);

        return [$exercise, $question];
    }

    private function submitObjectiveAttempt(User $student, LessonExercise $exercise, ExerciseQuestion $question, string $answer): void
    {
        $this->actingAs($student)->get(route('student.exercises.show', $exercise))->assertOk();
        $attempt = ExerciseAttempt::where('lesson_exercise_id', $exercise->id)->where('student_id', $student->id)->latest('attempt_number')->firstOrFail();
        $this->actingAs($student)->post(route('student.exercises.submit', [$exercise, $attempt]), ['answers' => [$question->id => $answer]])->assertRedirect();
    }

    private function notePayload(SchoolClass $class, Subject $subject, array $overrides = []): array
    {
        return array_merge([
            'week_number' => 1,
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'title' => 'Linear Equations',
            'topic' => 'Linear Equations',
            'main_content' => '<p>Solve for x.</p><script>alert(1)</script>',
        ], $overrides);
    }

    private function noteRecord(User $teacher, SchoolClass $class, Subject $subject, array $overrides = []): array
    {
        $session = AcademicSession::active()->first() ?: AcademicSession::create(['academic_year' => '2026/2027', 'term' => 'First Term', 'is_active' => true]);

        return array_merge([
            'academic_session_id' => $session->id,
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'week_number' => 1,
            'title' => 'Linear Equations',
            'topic' => 'Linear Equations',
            'main_content' => '<p>Solve for x.</p>',
            'status' => LessonNote::STATUS_DRAFT,
        ], $overrides);
    }

    private function user(string $portalId, string $role, array $overrides = []): User
    {
        return User::create(array_merge([
            'portal_id' => $portalId,
            'first_name' => ucfirst(str_replace('-', ' ', $portalId)),
            'last_name' => 'User',
            'email' => "{$portalId}@example.com",
            'password' => Hash::make('password'),
            'role' => $role,
            'must_change_password' => false,
            'is_active' => true,
        ], $overrides));
    }
}
