<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExerciseAttempt;
use App\Models\ExerciseQuestion;
use App\Models\FeeItem;
use App\Models\LessonExercise;
use App\Models\LessonNote;
use App\Models\Payment;
use App\Models\PrefectRole;
use App\Models\Question;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\StudentRole;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoPortalSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach (['admin', 'hod', 'teacher', 'student', 'prefect', 'cbt_personnel'] as $role) {
                Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
            }

            $password = Hash::make('12345');

            $settings = SchoolSetting::current();
            $settings->update([
                'school_name' => 'TOKE Schools',
                'motto' => 'Learning for excellence',
                'vision' => 'A confident learning community prepared for modern assessment.',
                'school_address' => '15 Knowledge Avenue, Lagos',
                'school_phone' => '+234 800 555 0101',
                'school_email' => 'office@tokeschools.test',
                'exam_duration' => 60,
                'pass_mark' => 50,
                'auto_grade' => true,
            ]);

            $session = AcademicSession::updateOrCreate(
                ['academic_year' => '2026/2027', 'term' => 'First Term'],
                ['starts_at' => '2026-09-14', 'ends_at' => '2026-12-18', 'is_active' => true, 'activated_at' => now()]
            );
            AcademicSession::whereKeyNot($session->id)->update(['is_active' => false]);

            $admin = $this->user('rena', 'Rena', 'Admin', 'admin', $password);
            $hod = $this->user('hod001', 'Amaka', 'Okafor', 'hod', $password);
            $this->user('cbt001', 'Daniel', 'Adebayo', 'cbt_personnel', $password);
            $mathTeacher = $this->user('tch001', 'Grace', 'Olawale', 'teacher', $password);
            $englishTeacher = $this->user('tch002', 'Samuel', 'Eze', 'teacher', $password);
            $scienceTeacher = $this->user('tch003', 'Ifeoma', 'Bello', 'teacher', $password);

            $classes = $this->classes();
            $classes['JSS1 A']->update(['class_teacher_id' => $mathTeacher->id]);
            $classes['JSS1 B']->update(['class_teacher_id' => $englishTeacher->id]);
            $classes['JSS2 A']->update(['class_teacher_id' => $scienceTeacher->id]);

            $subjects = $this->subjects($classes);

            $this->assignTeacher($mathTeacher, $classes['JSS1 A'], $subjects['JSS1 A:Mathematics']);
            $this->assignTeacher($mathTeacher, $classes['JSS1 B'], $subjects['JSS1 B:Mathematics']);
            $this->assignTeacher($englishTeacher, $classes['JSS1 A'], $subjects['JSS1 A:English Language']);
            $this->assignTeacher($englishTeacher, $classes['JSS1 B'], $subjects['JSS1 B:English Language']);
            $this->assignTeacher($scienceTeacher, $classes['JSS1 A'], $subjects['JSS1 A:Basic Science']);
            $this->assignTeacher($scienceTeacher, $classes['JSS2 A'], $subjects['JSS2 A:Basic Science']);
            $this->assignTeacher($scienceTeacher, $classes['SS1 Science'], $subjects['SS1 Science:Physics']);

            $regularRole = StudentRole::updateOrCreate(
                ['name' => 'Regular Student'],
                ['description' => 'General student', 'is_active' => true]
            );
            $classCaptain = PrefectRole::updateOrCreate(
                ['name' => 'Class Captain'],
                ['description' => 'Class leadership role', 'is_active' => true]
            );

            $students = [
                $this->user('stu001', 'Tobi', 'Johnson', 'student', $password, ['school_class_id' => $classes['JSS1 A']->id, 'student_role_id' => $regularRole->id]),
                $this->user('stu002', 'Aisha', 'Musa', 'student', $password, ['school_class_id' => $classes['JSS1 A']->id, 'student_role_id' => $regularRole->id]),
                $this->user('stu003', 'Chinedu', 'Nwosu', 'student', $password, ['school_class_id' => $classes['JSS1 B']->id, 'student_role_id' => $regularRole->id]),
                $this->user('stu004', 'Mary', 'Okon', 'student', $password, ['school_class_id' => $classes['JSS2 A']->id, 'student_role_id' => $regularRole->id]),
                $this->user('stu005', 'David', 'Balogun', 'student', $password, ['school_class_id' => $classes['SS1 Science']->id, 'student_role_id' => $regularRole->id]),
                $this->user('pref001', 'Kemi', 'Adeyemi', 'prefect', $password, [
                    'school_class_id' => $classes['JSS1 A']->id,
                    'student_role_id' => $regularRole->id,
                    'prefect_role_id' => $classCaptain->id,
                    'prefect_title' => 'Class Captain',
                ]),
            ];

            foreach ($students as $index => $student) {
                foreach (Subject::where('school_class_id', $student->school_class_id)->get() as $subject) {
                    DB::table('student_subject')->updateOrInsert(
                        ['student_id' => $student->id, 'subject_id' => $subject->id],
                        ['created_at' => now(), 'updated_at' => now()]
                    );
                }

                Payment::updateOrCreate(
                    ['student_id' => $student->id, 'school_class_id' => $student->school_class_id],
                    [
                        'total_fees' => 75000,
                        'amount_paid' => $index < 4 ? 75000 : 45000,
                        'status' => $index < 4 ? 'paid' : 'partial',
                        'payment_details' => 'Demo first term payment record',
                        'last_payment_date' => now()->subDays($index + 1),
                    ]
                );

                Attendance::updateOrCreate(
                    ['student_id' => $student->id, 'school_class_id' => $student->school_class_id, 'attendance_date' => now()->toDateString()],
                    ['status' => $index === 2 ? 'absent' : 'present', 'marked_by' => $mathTeacher->id, 'remarks' => 'Demo attendance']
                );
            }

            FeeItem::updateOrCreate(
                ['name' => 'First Term Tuition'],
                ['amount' => 75000, 'fee_type' => 'compulsory', 'applies_to_all_classes' => true, 'created_by' => $admin->id, 'is_active' => true]
            );

            $notes = $this->lessonNotes($session, $classes, $subjects, $mathTeacher, $englishTeacher, $scienceTeacher, $hod);
            $exercise = $this->linearEquationExercise($notes['Linear Equations']);
            $this->englishExercise($notes['Parts of Speech']);
            $this->sampleExerciseAttempt($exercise, User::where('portal_id', 'stu001')->firstOrFail());
            $this->formalExam($classes, $subjects, $mathTeacher, User::where('portal_id', 'stu001')->firstOrFail());

            User::query()->update(['password' => $password, 'must_change_password' => false]);
        });
    }

    private function user(string $portalId, string $first, string $last, string $role, string $password, array $extra = []): User
    {
        $user = User::updateOrCreate(
            ['portal_id' => $portalId],
            array_merge([
                'first_name' => $first,
                'last_name' => $last,
                'email' => "{$portalId}@tokeschools.test",
                'password' => $password,
                'role' => $role,
                'must_change_password' => false,
                'is_active' => true,
                'password_changed_at' => now(),
            ], $extra)
        );

        $user->syncRoles([$role]);
        $user->profile()->firstOrCreate();

        return $user;
    }

    private function classes(): array
    {
        $classes = [];

        foreach ([['JSS1 A', 'JSS1', 'A'], ['JSS1 B', 'JSS1', 'B'], ['JSS2 A', 'JSS2', 'A'], ['SS1 Science', 'SS1', 'Science']] as [$name, $level, $stream]) {
            $classes[$name] = SchoolClass::updateOrCreate(
                ['name' => $name],
                ['level' => $level, 'stream' => $stream, 'description' => "{$name} active class arm", 'is_active' => true]
            );
        }

        return $classes;
    }

    private function subjects(array $classes): array
    {
        $subjects = [];

        foreach ([
            ['JSS1 A', 'Mathematics', 'MTH-J1A'],
            ['JSS1 A', 'English Language', 'ENG-J1A'],
            ['JSS1 A', 'Basic Science', 'BSC-J1A'],
            ['JSS1 B', 'Mathematics', 'MTH-J1B'],
            ['JSS1 B', 'English Language', 'ENG-J1B'],
            ['JSS1 B', 'Basic Science', 'BSC-J1B'],
            ['JSS2 A', 'Mathematics', 'MTH-J2A'],
            ['JSS2 A', 'English Language', 'ENG-J2A'],
            ['JSS2 A', 'Basic Science', 'BSC-J2A'],
            ['SS1 Science', 'Mathematics', 'MTH-S1S'],
            ['SS1 Science', 'Physics', 'PHY-S1S'],
            ['SS1 Science', 'Chemistry', 'CHM-S1S'],
        ] as [$className, $name, $code]) {
            $subjects["{$className}:{$name}"] = Subject::updateOrCreate(
                ['code' => $code, 'school_class_id' => $classes[$className]->id],
                ['name' => $name, 'description' => "{$name} for {$className}", 'is_active' => true]
            );
        }

        return $subjects;
    }

    private function assignTeacher(User $teacher, SchoolClass $class, Subject $subject): void
    {
        DB::table('teacher_class_subject')->updateOrInsert(
            ['teacher_id' => $teacher->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id],
            ['created_at' => now(), 'updated_at' => now()]
        );
    }

    private function lessonNotes(AcademicSession $session, array $classes, array $subjects, User $mathTeacher, User $englishTeacher, User $scienceTeacher, User $hod): array
    {
        $rows = [
            [$mathTeacher, 'JSS1 A', 'Mathematics', 1, 'Linear Equations', 'Solving one-step linear equations', LessonNote::STATUS_APPROVED, '<p>Students learn how to isolate variables and check answers.</p><ol><li>Collect like terms.</li><li>Balance both sides.</li><li>Substitute to verify.</li></ol>'],
            [$englishTeacher, 'JSS1 A', 'English Language', 1, 'Parts of Speech', 'Nouns, verbs and adjectives', LessonNote::STATUS_APPROVED, '<p>This note introduces common parts of speech using classroom examples.</p>'],
            [$scienceTeacher, 'JSS1 A', 'Basic Science', 2, 'Living Things', 'Characteristics of living things', LessonNote::STATUS_PENDING, '<p>Living things feed, respire, move, grow, reproduce, excrete and respond.</p>'],
            [$mathTeacher, 'JSS1 B', 'Mathematics', 2, 'Fractions', 'Adding and subtracting fractions', LessonNote::STATUS_RETURNED, '<p>Use equivalent fractions and common denominators.</p>'],
            [$scienceTeacher, 'JSS2 A', 'Basic Science', 3, 'Energy', 'Forms and sources of energy', LessonNote::STATUS_DRAFT, '<p>Energy can be light, heat, sound, electrical or chemical.</p>'],
            [$scienceTeacher, 'SS1 Science', 'Physics', 1, 'Measurement', 'Physical quantities and units', LessonNote::STATUS_APPROVED, '<p>Physics measurements require quantities, units and instruments.</p>'],
        ];

        $notes = [];

        foreach ($rows as [$teacher, $className, $subjectName, $week, $title, $topic, $status, $content]) {
            $note = LessonNote::updateOrCreate(
                [
                    'academic_session_id' => $session->id,
                    'school_class_id' => $classes[$className]->id,
                    'subject_id' => $subjects["{$className}:{$subjectName}"]->id,
                    'teacher_id' => $teacher->id,
                    'week_number' => $week,
                    'status' => $status,
                ],
                [
                    'title' => $title,
                    'topic' => $topic,
                    'subtopic' => $week === 1 ? 'Classroom practice' : null,
                    'lesson_date' => now()->addDays($week)->toDateString(),
                    'previous_knowledge' => 'Students have met related ideas in previous classes.',
                    'learning_objectives' => 'By the end of the lesson, students should explain the topic and answer practice questions.',
                    'teaching_materials' => 'Whiteboard, marker, textbook, workbook and charts.',
                    'introduction' => 'The teacher begins with a short discussion and quick recall questions.',
                    'main_content' => $content,
                    'evaluation' => 'Students answer oral and written questions.',
                    'conclusion' => 'The teacher summarizes the key points and corrects common mistakes.',
                    'assignment' => 'Complete the practice questions in your notebook.',
                    'submitted_at' => in_array($status, [LessonNote::STATUS_PENDING, LessonNote::STATUS_APPROVED, LessonNote::STATUS_RETURNED], true) ? now()->subDays(2) : null,
                    'submitted_by' => in_array($status, [LessonNote::STATUS_PENDING, LessonNote::STATUS_APPROVED, LessonNote::STATUS_RETURNED], true) ? $teacher->id : null,
                    'approved_at' => $status === LessonNote::STATUS_APPROVED ? now()->subDay() : null,
                    'approved_by' => $status === LessonNote::STATUS_APPROVED ? $hod->id : null,
                    'published_at' => $status === LessonNote::STATUS_APPROVED ? now()->subDay() : null,
                ]
            );

            if ($status === LessonNote::STATUS_APPROVED && ! $note->reviews()->where('action', LessonNote::STATUS_APPROVED)->exists()) {
                $note->reviews()->create(['reviewer_id' => $hod->id, 'action' => LessonNote::STATUS_APPROVED, 'comments' => 'Approved for class use.', 'reviewed_at' => now()->subDay()]);
            }

            if ($status === LessonNote::STATUS_RETURNED && ! $note->reviews()->where('action', LessonNote::STATUS_RETURNED)->exists()) {
                $note->reviews()->create(['reviewer_id' => $hod->id, 'action' => LessonNote::STATUS_RETURNED, 'comments' => 'Add more worked examples before resubmission.', 'reviewed_at' => now()->subDay()]);
            }

            $notes[$title] = $note;
        }

        return $notes;
    }

    private function linearEquationExercise(LessonNote $note): LessonExercise
    {
        $exercise = $note->exercise()->updateOrCreate([], [
            'title' => 'Linear Equations Practice',
            'instructions' => 'Answer all questions. Theory will be marked by your teacher.',
            'opens_at' => now()->subDay(),
            'due_at' => now()->addDays(5),
            'allow_late_submission' => false,
            'shuffle_questions' => true,
            'shuffle_options' => false,
            'show_score_immediately' => true,
            'reveal_correct_answers' => false,
            'attempt_mode' => LessonExercise::ATTEMPT_LIMITED,
            'max_attempts' => 2,
            'score_selection_method' => LessonExercise::SCORE_HIGHEST,
        ]);

        $exercise->questions()->updateOrCreate(['display_order' => 1], ['question_type' => ExerciseQuestion::TYPE_OBJECTIVE, 'question_text' => '<p>Solve x + 3 = 7.</p>', 'options' => ['A' => '2', 'B' => '3', 'C' => '4', 'D' => '10'], 'correct_answer' => 'C', 'marks' => 2]);
        $exercise->questions()->updateOrCreate(['display_order' => 2], ['question_type' => ExerciseQuestion::TYPE_TRUE_FALSE, 'question_text' => '<p>If 2x = 10, then x = 5.</p>', 'correct_answer' => 'true', 'marks' => 1]);
        $exercise->questions()->updateOrCreate(['display_order' => 3], ['question_type' => ExerciseQuestion::TYPE_THEORY, 'question_text' => '<p>Explain why whatever is done to one side of an equation must be done to the other side.</p>', 'marking_guide' => 'Mentions balance/equality and gives an example.', 'marks' => 5]);

        return $exercise;
    }

    private function englishExercise(LessonNote $note): void
    {
        $exercise = $note->exercise()->updateOrCreate([], [
            'title' => 'Parts of Speech Quiz',
            'instructions' => 'Choose the best answer.',
            'opens_at' => now()->subDay(),
            'due_at' => now()->addDays(3),
            'attempt_mode' => LessonExercise::ATTEMPT_ONE,
            'score_selection_method' => LessonExercise::SCORE_HIGHEST,
            'show_score_immediately' => true,
        ]);

        $exercise->questions()->updateOrCreate(['display_order' => 1], ['question_type' => ExerciseQuestion::TYPE_OBJECTIVE, 'question_text' => '<p>Which word is a noun?</p>', 'options' => ['A' => 'Run', 'B' => 'Beautiful', 'C' => 'Lagos', 'D' => 'Quickly'], 'correct_answer' => 'C', 'marks' => 2]);
    }

    private function sampleExerciseAttempt(LessonExercise $exercise, User $student): void
    {
        $questions = $exercise->questions()->orderBy('display_order')->get();
        $attempt = $exercise->attempts()->updateOrCreate(
            ['student_id' => $student->id, 'attempt_number' => 1],
            ['status' => ExerciseAttempt::STATUS_AWAITING_MARKING, 'started_at' => now()->subHours(2), 'submitted_at' => now()->subHour(), 'auto_score' => 3, 'manual_score' => 0, 'total_score' => 3, 'is_counted' => true]
        );

        $attempt->answers()->updateOrCreate(['exercise_question_id' => $questions[0]->id], ['answer_text' => 'C', 'is_correct' => true, 'awarded_marks' => 2]);
        $attempt->answers()->updateOrCreate(['exercise_question_id' => $questions[1]->id], ['answer_text' => 'true', 'is_correct' => true, 'awarded_marks' => 1]);
        $attempt->answers()->updateOrCreate(['exercise_question_id' => $questions[2]->id], ['answer_text' => 'It keeps both sides equal like a balanced scale.']);
    }

    private function formalExam(array $classes, array $subjects, User $teacher, User $student): void
    {
        $exam = Exam::updateOrCreate(['title' => 'JSS1 Mathematics Weekly CBT'], [
            'description' => 'A short formal CBT for JSS1 Mathematics.',
            'subject_id' => $subjects['JSS1 A:Mathematics']->id,
            'school_class_id' => $classes['JSS1 A']->id,
            'target_class_ids' => [$classes['JSS1 A']->id],
            'created_by' => $teacher->id,
            'duration_minutes' => 30,
            'start_time' => now()->subHour(),
            'end_time' => now()->addDays(2),
            'shuffle_questions' => true,
            'show_results' => true,
            'is_live' => true,
            'allow_review' => true,
            'pass_mark' => 50,
        ]);

        Question::updateOrCreate(['exam_id' => $exam->id, 'order' => 1], ['question_text' => 'What is 5 + 7?', 'option_a' => '10', 'option_b' => '11', 'option_c' => '12', 'option_d' => '13', 'correct_answer' => 'C', 'points' => 2, 'is_ai_generated' => false]);
        Question::updateOrCreate(['exam_id' => $exam->id, 'order' => 2], ['question_text' => 'What is 3 x 4?', 'option_a' => '7', 'option_b' => '12', 'option_c' => '9', 'option_d' => '14', 'correct_answer' => 'B', 'points' => 2, 'is_ai_generated' => false]);

        ExamAttempt::updateOrCreate(
            ['exam_id' => $exam->id, 'student_id' => $student->id],
            ['started_at' => now()->subMinutes(40), 'submitted_at' => now()->subMinutes(20), 'score' => 4, 'total_points' => 4, 'percentage' => 100, 'grade' => 'A+', 'is_submitted' => true, 'answers' => []]
        );
    }
}
