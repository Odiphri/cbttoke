<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExerciseAttempt;
use App\Models\LessonExercise;
use App\Models\Payment;
use App\Models\Question;
use App\Models\SchoolSetting;
use App\Models\Attendance;
use App\Models\ChangeRequest;
use App\Models\Override;
use App\Models\LessonNote;
use App\Services\AIService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    public function __construct(private AIService $aiService)
    {
    }
    
    public function index()
    {
        $stats = [
            'total_users' => User::count(),
            'total_students' => User::where('role', 'student')->count(),
            'total_teachers' => User::where('role', 'teacher')->count(),
            'total_classes' => SchoolClass::count(),
            'total_subjects' => Subject::count(),
            'total_exams' => Exam::count(),
            'active_exams' => Exam::where('is_live', true)->count(),
            'total_attempts' => ExamAttempt::count(),
            'total_payments' => Payment::sum('total_fees'),
            'paid_amount' => Payment::where('status', 'paid')->sum('amount_paid'),
            'unpaid_students' => Payment::where('status', 'unpaid')->count(),
            'pending_requests' => ChangeRequest::where('status', 'pending')->count(),
            'active_overrides' => Override::where('is_active', true)->count(),
            'pending_lesson_notes' => LessonNote::where('status', LessonNote::STATUS_PENDING)->count(),
            'approved_lesson_notes' => LessonNote::where('status', LessonNote::STATUS_APPROVED)->count(),
        ];

        $recentUsers = User::where('role', '!=', 'admin')->latest()->take(5)->get();
        $recentExams = Exam::with(['subject', 'schoolClass'])->latest()->take(5)->get();
        $pendingRequests = ChangeRequest::with('student')->where('status', 'pending')->latest()->take(5)->get();
        $needsAttention = $this->needsAttention();
        $recentActivity = $this->recentActivity();
        $teacherHighlights = $this->teacherHighlights();
        $academicHealth = $this->academicHealth();

        $paymentStats = $this->getPaymentStats();
        $attendanceStats = $this->getAttendanceStats();
        $examStats = $this->getExamStats();

        return view('admin.dashboard', compact(
            'stats',
            'recentUsers',
            'recentExams',
            'pendingRequests',
            'paymentStats',
            'attendanceStats',
            'examStats',
            'needsAttention',
            'recentActivity',
            'teacherHighlights',
            'academicHealth'
        ));
    }

    public function reports(Request $request)
    {
        $activeSession = AcademicSession::active()->first();
        $sessionId = $request->integer('academic_session_id') ?: $activeSession?->id;
        $classId = $request->integer('school_class_id') ?: null;
        $subjectId = $request->integer('subject_id') ?: null;

        $submittedExamAttempts = ExamAttempt::submitted()
            ->whereHas('exam', fn ($query) => $query
                ->when($classId, fn ($query) => $query->where('school_class_id', $classId))
                ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId)));

        $exerciseAttempts = ExerciseAttempt::whereIn('status', [ExerciseAttempt::STATUS_SUBMITTED, ExerciseAttempt::STATUS_AWAITING_MARKING, ExerciseAttempt::STATUS_MARKED])
            ->whereHas('exercise.lessonNote', fn ($query) => $query
                ->when($sessionId, fn ($query) => $query->where('academic_session_id', $sessionId))
                ->when($classId, fn ($query) => $query->where('school_class_id', $classId))
                ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId)));

        $lessonNotes = LessonNote::query()
            ->when($sessionId, fn ($query) => $query->where('academic_session_id', $sessionId))
            ->when($classId, fn ($query) => $query->where('school_class_id', $classId))
            ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId));

        $paymentRows = Payment::with(['student.assignedClass'])
            ->when($classId, fn ($query) => $query->where('school_class_id', $classId))
            ->latest()
            ->take(10)
            ->get();

        $examRows = Exam::with(['subject', 'schoolClass'])
            ->withCount(['attempts as submitted_attempts_count' => fn ($query) => $query->where('is_submitted', true)])
            ->when($classId, fn ($query) => $query->where('school_class_id', $classId))
            ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId))
            ->latest()
            ->take(10)
            ->get();

        $exerciseRows = LessonExercise::with(['lessonNote.schoolClass', 'lessonNote.subject'])
            ->withCount(['attempts', 'attempts as awaiting_marking_count' => fn ($query) => $query->where('status', ExerciseAttempt::STATUS_AWAITING_MARKING)])
            ->whereHas('lessonNote', fn ($query) => $query
                ->when($sessionId, fn ($query) => $query->where('academic_session_id', $sessionId))
                ->when($classId, fn ($query) => $query->where('school_class_id', $classId))
                ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId)))
            ->latest()
            ->take(10)
            ->get();

        return view('admin.reports.index', [
            'sessions' => AcademicSession::latest()->get(),
            'classes' => SchoolClass::active()->orderBy('level')->orderBy('stream')->get(),
            'subjects' => Subject::active()->with('schoolClass')->orderBy('name')->get(),
            'selectedSessionId' => $sessionId,
            'selectedClassId' => $classId,
            'selectedSubjectId' => $subjectId,
            'summary' => [
                'submitted_exam_attempts' => (clone $submittedExamAttempts)->count(),
                'average_exam_score' => round((float) (clone $submittedExamAttempts)->avg('percentage'), 1),
                'exercise_attempts' => (clone $exerciseAttempts)->count(),
                'awaiting_marking' => (clone $exerciseAttempts)->where('status', ExerciseAttempt::STATUS_AWAITING_MARKING)->count(),
                'lesson_notes' => (clone $lessonNotes)->count(),
                'approved_lesson_notes' => (clone $lessonNotes)->where('status', LessonNote::STATUS_APPROVED)->count(),
                'payments_recorded' => Payment::when($classId, fn ($query) => $query->where('school_class_id', $classId))->count(),
                'payment_balance' => Payment::when($classId, fn ($query) => $query->where('school_class_id', $classId))->sum('balance'),
            ],
            'lessonStatusCounts' => (clone $lessonNotes)
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status'),
            'examRows' => $examRows,
            'exerciseRows' => $exerciseRows,
            'paymentRows' => $paymentRows,
        ]);
    }

    public function exercises(Request $request)
    {
        $status = $request->query('status');
        $exercises = LessonExercise::with(['lessonNote.teacher', 'lessonNote.schoolClass', 'lessonNote.subject'])
            ->withCount([
                'questions',
                'attempts',
                'attempts as awaiting_marking_count' => fn ($query) => $query->where('status', ExerciseAttempt::STATUS_AWAITING_MARKING),
                'attempts as marked_count' => fn ($query) => $query->where('status', ExerciseAttempt::STATUS_MARKED),
            ])
            ->when($request->filled('school_class_id'), fn ($query) => $query->whereHas('lessonNote', fn ($query) => $query->where('school_class_id', $request->integer('school_class_id'))))
            ->when($request->filled('subject_id'), fn ($query) => $query->whereHas('lessonNote', fn ($query) => $query->where('subject_id', $request->integer('subject_id'))))
            ->when($status === 'awaiting_marking', fn ($query) => $query->whereHas('attempts', fn ($query) => $query->where('status', ExerciseAttempt::STATUS_AWAITING_MARKING)))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $awaitingAttempts = ExerciseAttempt::with(['student.assignedClass', 'exercise.lessonNote.teacher', 'exercise.lessonNote.subject'])
            ->where('status', ExerciseAttempt::STATUS_AWAITING_MARKING)
            ->latest('submitted_at')
            ->take(10)
            ->get();

        return view('admin.exercises.index', [
            'exercises' => $exercises,
            'awaitingAttempts' => $awaitingAttempts,
            'classes' => SchoolClass::active()->orderBy('level')->orderBy('stream')->get(),
            'subjects' => Subject::active()->with('schoolClass')->orderBy('name')->get(),
            'selectedClassId' => $request->query('school_class_id'),
            'selectedSubjectId' => $request->query('subject_id'),
            'selectedStatus' => $status,
        ]);
    }

    public function teacherWorkload(Request $request)
    {
        $activeSession = AcademicSession::active()->first();
        $teachers = User::with(['teachingSubjects.schoolClass', 'assignedClasses'])
            ->whereIn('role', ['teacher', 'hod', 'cbt_personnel'])
            ->withCount([
                'lessonNotes',
                'lessonNotes as pending_lesson_notes_count' => fn ($query) => $query->where('status', LessonNote::STATUS_PENDING),
                'lessonNotes as approved_lesson_notes_count' => fn ($query) => $query->where('status', LessonNote::STATUS_APPROVED),
                'lessonNotes as returned_lesson_notes_count' => fn ($query) => $query->whereIn('status', [LessonNote::STATUS_RETURNED, LessonNote::STATUS_REJECTED]),
                'createdExams',
            ])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->query('search'));
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('portal_id', 'like', "%{$search}%");
                });
            })
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString();

        $teacherIds = $teachers->pluck('id');
        $unmarkedCounts = ExerciseAttempt::where('status', ExerciseAttempt::STATUS_AWAITING_MARKING)
            ->whereHas('exercise.lessonNote', fn ($query) => $query->whereIn('teacher_id', $teacherIds))
            ->with('exercise.lessonNote')
            ->get()
            ->groupBy(fn (ExerciseAttempt $attempt) => $attempt->exercise?->lessonNote?->teacher_id)
            ->map->count();

        return view('admin.teacher-workload.index', [
            'teachers' => $teachers,
            'unmarkedCounts' => $unmarkedCounts,
            'activeSession' => $activeSession,
            'search' => $request->query('search'),
        ]);
    }

    public function lessonNoteCoverage(Request $request)
    {
        $activeSession = AcademicSession::active()->first();
        $sessionId = $request->integer('academic_session_id') ?: $activeSession?->id;
        $weeks = range(1, 13);

        $assignments = DB::table('teacher_class_subject')
            ->join('users', 'users.id', '=', 'teacher_class_subject.teacher_id')
            ->join('school_classes', 'school_classes.id', '=', 'teacher_class_subject.school_class_id')
            ->join('subjects', 'subjects.id', '=', 'teacher_class_subject.subject_id')
            ->select([
                'users.id as teacher_id',
                'users.first_name',
                'users.last_name',
                'school_classes.id as class_id',
                'school_classes.level',
                'school_classes.stream',
                'subjects.id as subject_id',
                'subjects.name as subject_name',
            ])
            ->when($request->filled('teacher_id'), fn ($query) => $query->where('users.id', $request->integer('teacher_id')))
            ->when($request->filled('school_class_id'), fn ($query) => $query->where('school_classes.id', $request->integer('school_class_id')))
            ->when($request->filled('subject_id'), fn ($query) => $query->where('subjects.id', $request->integer('subject_id')))
            ->orderBy('users.first_name')
            ->orderBy('school_classes.level')
            ->orderBy('subjects.name')
            ->get();

        $notes = LessonNote::where('academic_session_id', $sessionId)
            ->whereIn('teacher_id', $assignments->pluck('teacher_id')->unique())
            ->get()
            ->keyBy(fn (LessonNote $note) => $note->teacher_id . ':' . $note->school_class_id . ':' . $note->subject_id . ':' . $note->week_number);

        return view('admin.lesson-note-coverage.index', [
            'sessions' => AcademicSession::latest()->get(),
            'teachers' => User::whereIn('role', ['teacher', 'hod', 'cbt_personnel'])->orderBy('first_name')->get(),
            'classes' => SchoolClass::active()->orderBy('level')->orderBy('stream')->get(),
            'subjects' => Subject::active()->with('schoolClass')->orderBy('name')->get(),
            'selectedSessionId' => $sessionId,
            'selectedTeacherId' => $request->query('teacher_id'),
            'selectedClassId' => $request->query('school_class_id'),
            'selectedSubjectId' => $request->query('subject_id'),
            'assignments' => $assignments,
            'notes' => $notes,
            'weeks' => $weeks,
        ]);
    }

    private function needsAttention(): array
    {
        return [
            [
                'label' => 'Pending lesson notes',
                'value' => LessonNote::where('status', LessonNote::STATUS_PENDING)->count(),
                'icon' => 'fa-book-open',
                'url' => route('admin.lesson-notes.index', ['status' => LessonNote::STATUS_PENDING]),
            ],
            [
                'label' => 'Returned notes',
                'value' => LessonNote::where('status', LessonNote::STATUS_RETURNED)->count(),
                'icon' => 'fa-undo',
                'url' => route('admin.lesson-notes.index', ['status' => LessonNote::STATUS_RETURNED]),
            ],
            [
                'label' => 'Awaiting marking',
                'value' => ExerciseAttempt::where('status', ExerciseAttempt::STATUS_AWAITING_MARKING)->count(),
                'icon' => 'fa-pen',
                'url' => route('admin.exercises', ['status' => 'awaiting_marking']),
            ],
            [
                'label' => 'Live exams',
                'value' => Exam::where('is_live', true)->count(),
                'icon' => 'fa-broadcast-tower',
                'url' => route('admin.monitor'),
            ],
            [
                'label' => 'Unpaid / partial',
                'value' => Payment::whereIn('status', ['unpaid', 'partial'])->count(),
                'icon' => 'fa-money-bill-wave',
                'url' => route('admin.payments'),
            ],
            [
                'label' => 'Pending requests',
                'value' => ChangeRequest::where('status', 'pending')->count(),
                'icon' => 'fa-clock',
                'url' => route('admin.dashboard'),
            ],
        ];
    }

    private function recentActivity()
    {
        $lessonActivities = LessonNote::with(['teacher', 'schoolClass', 'subject'])
            ->latest('updated_at')
            ->take(8)
            ->get()
            ->map(fn (LessonNote $note) => [
                'time' => $note->updated_at,
                'title' => $note->title,
                'meta' => "{$note->teacher?->full_name} / {$note->schoolClass?->full_name} / {$note->subject?->name}",
                'badge' => $note->statusLabel(),
                'url' => route('admin.lesson-notes.show', $note),
            ]);

        $exerciseActivities = ExerciseAttempt::with(['student', 'exercise.lessonNote.subject'])
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->take(8)
            ->get()
            ->map(fn (ExerciseAttempt $attempt) => [
                'time' => $attempt->submitted_at,
                'title' => $attempt->exercise?->title ?? 'Exercise submission',
                'meta' => ($attempt->student?->full_name ?? 'Student') . ' / ' . ($attempt->exercise?->lessonNote?->subject?->name ?? 'Subject'),
                'badge' => str_replace('_', ' ', $attempt->status),
                'url' => route('admin.exercises'),
            ]);

        $examActivities = ExamAttempt::with(['student', 'exam.subject'])
            ->where('is_submitted', true)
            ->latest('submitted_at')
            ->take(8)
            ->get()
            ->map(fn (ExamAttempt $attempt) => [
                'time' => $attempt->submitted_at,
                'title' => $attempt->exam?->title ?? 'Exam submitted',
                'meta' => ($attempt->student?->full_name ?? 'Student') . ' / ' . ($attempt->percentage ?? 0) . '%',
                'badge' => 'exam',
                'url' => $attempt->exam ? route('admin.results.show', $attempt->exam) : route('admin.results'),
            ]);

        return $lessonActivities
            ->merge($exerciseActivities)
            ->merge($examActivities)
            ->filter(fn ($item) => $item['time'])
            ->sortByDesc('time')
            ->take(10)
            ->values();
    }

    private function teacherHighlights()
    {
        return User::whereIn('role', ['teacher', 'hod', 'cbt_personnel'])
            ->withCount([
                'lessonNotes',
                'lessonNotes as pending_lesson_notes_count' => fn ($query) => $query->where('status', LessonNote::STATUS_PENDING),
                'lessonNotes as returned_lesson_notes_count' => fn ($query) => $query->whereIn('status', [LessonNote::STATUS_RETURNED, LessonNote::STATUS_REJECTED]),
            ])
            ->orderByDesc('pending_lesson_notes_count')
            ->orderByDesc('returned_lesson_notes_count')
            ->take(6)
            ->get();
    }

    private function academicHealth(): array
    {
        $submittedExamAttempts = ExamAttempt::where('is_submitted', true);
        $markedExerciseAttempts = ExerciseAttempt::where('status', ExerciseAttempt::STATUS_MARKED);
        $activeSession = AcademicSession::active()->first();

        $weakClasses = SchoolClass::withCount(['lessonNotes as approved_notes_count' => fn ($query) => $query->where('status', LessonNote::STATUS_APPROVED)])
            ->take(6)
            ->get()
            ->map(function (SchoolClass $class) {
                $examAverage = ExamAttempt::where('is_submitted', true)
                    ->whereHas('exam', fn ($query) => $query->where('school_class_id', $class->id))
                    ->avg('percentage');

                return [
                    'class' => $class,
                    'exam_average' => $examAverage === null ? null : round((float) $examAverage, 1),
                    'approved_notes_count' => $class->approved_notes_count,
                ];
            })
            ->sortBy(fn ($row) => $row['exam_average'] ?? 101)
            ->take(5)
            ->values();

        $lowExercisePerformance = ExerciseAttempt::with(['exercise.questions', 'exercise.lessonNote.schoolClass', 'exercise.lessonNote.subject'])
            ->where('status', ExerciseAttempt::STATUS_MARKED)
            ->where('is_counted', true)
            ->latest('marked_at')
            ->take(500)
            ->get()
            ->map(function (ExerciseAttempt $attempt) {
                $exercise = $attempt->exercise;
                $note = $exercise?->lessonNote;
                $totalMarks = (float) ($exercise?->questions?->sum('marks') ?? 0);

                if (!$note || $totalMarks <= 0) {
                    return null;
                }

                return [
                    'key' => $note->school_class_id . ':' . $note->subject_id,
                    'class' => $note->schoolClass,
                    'subject' => $note->subject,
                    'percentage' => ((float) $attempt->total_score / $totalMarks) * 100,
                ];
            })
            ->filter()
            ->groupBy('key')
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'class' => $first['class'],
                    'subject' => $first['subject'],
                    'average' => round((float) $rows->avg('percentage'), 1),
                    'attempts' => $rows->count(),
                ];
            })
            ->sortBy('average')
            ->take(5)
            ->values();

        $studentsAwaitingMarking = ExerciseAttempt::with([
                'student.assignedClass',
                'exercise.lessonNote.subject',
                'exercise.lessonNote.teacher',
            ])
            ->where('status', ExerciseAttempt::STATUS_AWAITING_MARKING)
            ->latest('submitted_at')
            ->take(6)
            ->get();

        $latestExpectedWeek = LessonNote::query()
            ->when($activeSession, fn ($query) => $query->where('academic_session_id', $activeSession->id))
            ->max('week_number') ?: 1;
        $latestExpectedWeek = min(max((int) $latestExpectedWeek, 1), 13);
        $expectedWeeks = range(1, $latestExpectedWeek);

        $subjectsMissingLessonNotes = DB::table('teacher_class_subject')
            ->join('users', 'users.id', '=', 'teacher_class_subject.teacher_id')
            ->join('school_classes', 'school_classes.id', '=', 'teacher_class_subject.school_class_id')
            ->join('subjects', 'subjects.id', '=', 'teacher_class_subject.subject_id')
            ->select([
                'teacher_class_subject.teacher_id',
                'teacher_class_subject.school_class_id',
                'teacher_class_subject.subject_id',
                'users.first_name',
                'users.last_name',
                'school_classes.level',
                'school_classes.stream',
                'subjects.name as subject_name',
            ])
            ->orderBy('subjects.name')
            ->get()
            ->map(function ($assignment) use ($activeSession, $expectedWeeks) {
                $submittedWeeks = LessonNote::query()
                    ->when($activeSession, fn ($query) => $query->where('academic_session_id', $activeSession->id))
                    ->where('teacher_id', $assignment->teacher_id)
                    ->where('school_class_id', $assignment->school_class_id)
                    ->where('subject_id', $assignment->subject_id)
                    ->whereIn('status', [LessonNote::STATUS_PENDING, LessonNote::STATUS_APPROVED, LessonNote::STATUS_RETURNED])
                    ->pluck('week_number')
                    ->map(fn ($week) => (int) $week)
                    ->unique()
                    ->all();
                $missingWeeks = collect($expectedWeeks)->diff($submittedWeeks)->values();

                if ($missingWeeks->isEmpty()) {
                    return null;
                }

                return [
                    'teacher' => trim($assignment->first_name . ' ' . $assignment->last_name),
                    'class' => trim($assignment->level . ' ' . $assignment->stream),
                    'subject' => $assignment->subject_name,
                    'missing_weeks' => $missingWeeks->all(),
                ];
            })
            ->filter()
            ->sortByDesc(fn ($row) => count($row['missing_weeks']))
            ->take(6)
            ->values();

        return [
            'exam_average' => round((float) (clone $submittedExamAttempts)->avg('percentage'), 1),
            'submitted_exam_attempts' => (clone $submittedExamAttempts)->count(),
            'exercise_average' => round((float) (clone $markedExerciseAttempts)->avg('total_score'), 1),
            'marked_exercise_attempts' => (clone $markedExerciseAttempts)->count(),
            'weak_classes' => $weakClasses,
            'low_exercise_performance' => $lowExercisePerformance,
            'students_awaiting_marking' => $studentsAwaitingMarking,
            'subjects_missing_lesson_notes' => $subjectsMissingLessonNotes,
        ];
    }

    private function getPaymentStats()
    {
        $totalFees = Payment::sum('total_fees');
        $paidAmount = Payment::where('status', 'paid')->sum('amount_paid');
        $unpaidAmount = Payment::where('status', 'unpaid')->sum('total_fees');
        $partialAmount = Payment::where('status', 'partial')->sum('balance');

        return [
            'total_fees' => $totalFees,
            'paid_amount' => $paidAmount,
            'unpaid_amount' => $unpaidAmount,
            'partial_amount' => $partialAmount,
            'collection_rate' => $totalFees > 0 ? ($paidAmount / $totalFees) * 100 : 0,
        ];
    }

    private function getAttendanceStats()
    {
        $today = now()->toDateString();
        
        $present = Attendance::where('attendance_date', $today)
            ->where('status', 'present')
            ->count();
        $absent = Attendance::where('attendance_date', $today)
            ->where('status', 'absent')
            ->count();
        $total = $present + $absent;

        return [
            'present_today' => $present,
            'absent_today' => $absent,
            'total_today' => $total,
            'attendance_rate' => $total > 0 ? ($present / $total) * 100 : 0,
        ];
    }

    private function getExamStats()
    {
        $totalExams = Exam::count();
        $activeExams = Exam::where('is_live', true)->count();
        $totalAttempts = ExamAttempt::count();
        $submittedAttempts = ExamAttempt::where('is_submitted', true)->count();
        $submittedScore = ExamAttempt::where('is_submitted', true)->sum('score');
        $submittedPoints = ExamAttempt::where('is_submitted', true)->sum('total_points');

        $averageScore = $submittedPoints > 0
            ? ($submittedScore / $submittedPoints) * 100
            : 0;

        return [
            'total_exams' => $totalExams,
            'active_exams' => $activeExams,
            'total_attempts' => $totalAttempts,
            'submitted_attempts' => $submittedAttempts,
            'average_score' => round($averageScore, 2),
        ];
    }

    public function users(Request $request)
    {
        $users = User::with('profile')
            ->where('role', '!=', 'admin')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = strtolower(trim((string) $request->query('search')));
                $fullNameExpression = "LOWER(first_name || ' ' || last_name)";

                if (config('database.default') !== 'sqlite') {
                    $fullNameExpression = "LOWER(CONCAT(first_name, ' ', last_name))";
                }

                $query->where(function ($query) use ($search, $fullNameExpression) {
                    $query->whereRaw('LOWER(first_name) like ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(last_name) like ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(portal_id) like ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(email) like ?', ["%{$search}%"])
                        ->orWhereRaw($fullNameExpression . ' like ?', ["%{$search}%"]);
                });
            })
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->query('role')))
            ->latest()
            ->paginate(20)
            ->withQueryString();
        $roles = ['hod', 'cbt_personnel', 'teacher', 'prefect', 'student'];
        $search = $request->query('search');
        $selectedRole = $request->query('role');
        
        return view('admin.users.index', compact('users', 'roles', 'search', 'selectedRole'));
    }

    public function updateUserRole(Request $request, User $user)
    {
        abort_if($user->role === 'admin', 403, 'Admin users cannot be edited from user management.');

        $validated = $request->validate([
            'role' => 'required|in:hod,cbt_personnel,teacher,prefect,student',
            'is_active' => 'nullable|boolean',
        ]);

        $user->update([
            'role' => $validated['role'],
            'is_active' => $request->boolean('is_active'),
        ]);

        $user->syncRoles([$validated['role']]);

        return back()->with('success', 'User role updated successfully.');
    }

    public function storeAdminUser(Request $request)
    {
        $validated = $request->validate([
            'portal_id' => 'required|string|max:255|unique:users,portal_id',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email',
            'password' => 'required|string|min:4',
        ]);

        [$firstName, $lastName] = $this->splitName($validated['name']);

        $admin = User::create([
            'portal_id' => $validated['portal_id'],
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $validated['email'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => 'admin',
            'must_change_password' => false,
            'is_active' => true,
            'password_changed_at' => now(),
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole('admin');
        $admin->profile()->firstOrCreate();

        return back()->with('success', 'Admin user created successfully.');
    }

    public function classes()
    {
        $classes = SchoolClass::with('classTeacher', 'subjects')->latest()->paginate(20);
        $levels = ['JSS1', 'JSS2', 'JSS3', 'SS1', 'SS2', 'SS3'];
        $streams = ['Science', 'Art', 'Commercial'];
        
        return view('admin.classes.index', compact('classes', 'levels', 'streams'));
    }

    public function payments()
    {
        $payments = Payment::with('student', 'schoolClass')
            ->latest()
            ->paginate(20);
            
        $totalFees = Payment::sum('total_fees');
        $totalPaid = Payment::sum('amount_paid');
        $totalBalance = Payment::sum('balance');
        
        return view('admin.payments.index', compact(
            'payments', 
            'totalFees', 
            'totalPaid', 
            'totalBalance'
        ));
    }

    public function create()
    {
        $levels = ['JSS1', 'JSS2', 'JSS3', 'SS1', 'SS2', 'SS3'];
        $streams = ['Science', 'Art', 'Commercial'];
        $teachers = User::where('role', 'teacher')->get();
        
        return view('admin.classes.create', compact('levels', 'streams', 'teachers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'level' => 'required|string',
            'stream' => 'nullable|string',
            'description' => 'nullable|string',
            'class_teacher_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
        ]);

        SchoolClass::create($validated);

        return redirect()->route('admin.classes')->with('success', 'Class created successfully!');
    }

    public function settings()
    {
        return view('admin.settings.index', [
            'settings' => SchoolSetting::current(),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'school_name' => 'required|string|max:255',
            'motto' => 'nullable|string|max:255',
            'vision' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'school_address' => 'nullable|string',
            'school_phone' => 'nullable|string',
            'school_email' => 'nullable|email',
            'exam_duration' => 'required|integer|min:1',
            'pass_mark' => 'required|integer|min:0|max:100',
            'auto_grade' => 'boolean',
        ]);

        $settings = SchoolSetting::current();

        if ($request->hasFile('logo')) {
            if ($settings->logo_path) {
                Storage::disk('public')->delete($settings->logo_path);
            }

            $validated['logo_path'] = $request->file('logo')->store('school', 'public');
        }

        unset($validated['logo']);
        $validated['auto_grade'] = $request->boolean('auto_grade');
        $settings->update($validated);

        return redirect()->route('admin.settings')->with('success', 'Settings updated successfully!');
    }

    public function exams(Request $request)
    {
        $exams = Exam::with(['subject', 'schoolClass', 'creator'])
            ->when($request->filled('search'), fn ($query) => $this->applyExamSearch($query, (string) $request->query('search')))
            ->latest()
            ->paginate(20)
            ->withQueryString();
        $search = $request->query('search');

        return view('admin.exams.index', compact('exams', 'search'));
    }

    public function examCreate()
    {
        $classes = SchoolClass::all();
        $subjects = Subject::all();
        $teachers = User::where('role', 'teacher')->get();
        
        return view('admin.exams.create', compact('classes', 'subjects', 'teachers'));
    }

    public function examStore(Request $request)
    {
        $this->normalizeExamBooleanFields($request);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subject_id' => 'required|exists:subjects,id',
            'school_class_id' => 'required|exists:school_classes,id',
            'target_class_ids' => 'nullable|array',
            'target_class_ids.*' => 'exists:school_classes,id',
            'created_by' => 'required|exists:users,id',
            'duration_minutes' => 'required|integer|min:1|max:300',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after:start_time',
            'shuffle_questions' => 'boolean',
            'show_results' => 'boolean',
            'is_live' => 'boolean',
            'allow_review' => 'boolean',
        ]);

        $this->ensureSubjectBelongsToClass((int) $validated['subject_id'], (int) $validated['school_class_id']);

        $payload = [
            'title' => $validated['title'],
            'subject_id' => $validated['subject_id'],
            'school_class_id' => $validated['school_class_id'],
            'target_class_ids' => $this->targetClassIds((int) $validated['school_class_id'], $validated['target_class_ids'] ?? []),
            'created_by' => $validated['created_by'],
            'duration_minutes' => $validated['duration_minutes'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'shuffle_questions' => $request->boolean('shuffle_questions'),
            'show_results' => $request->boolean('show_results'),
            'is_live' => $request->boolean('is_live'),
            'allow_review' => $request->boolean('allow_review'),
            'pass_mark' => SchoolSetting::current()->pass_mark,
        ];

        if ($payload['is_live'] && (empty($payload['end_time']) || now()->gte($payload['end_time']))) {
            $payload['start_time'] = now();
            $payload['end_time'] = now()->addMinutes((int) $payload['duration_minutes']);
        }

        $exam = Exam::create($payload);

        return redirect()->route('admin.exams.show', $exam)
            ->with('success', 'Exam created successfully! Now add questions.');
    }

    public function examEdit(Exam $exam)
    {
        $classes = SchoolClass::all();
        $subjects = Subject::all();
        $teachers = User::where('role', 'teacher')->get();
        
        return view('admin.exams.edit', compact('exam', 'classes', 'subjects', 'teachers'));
    }

    public function examShow(Exam $exam)
    {
        $exam->load(['subject', 'schoolClass', 'creator', 'questions']);
        $routePrefix = 'admin';

        return view('teacher.exams.edit', compact('exam', 'routePrefix'));
    }

    public function examUpdate(Request $request, Exam $exam)
    {
        $this->normalizeExamBooleanFields($request);

        if (! $request->has(['subject_id', 'school_class_id', 'created_by'])) {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'duration_minutes' => 'required|integer|min:1|max:300',
                'start_time' => 'nullable|date',
                'end_time' => 'nullable|date|after:start_time',
                'shuffle_questions' => 'boolean',
                'show_results' => 'boolean',
                'is_live' => 'boolean',
            ]);

            $validated['shuffle_questions'] = $request->boolean('shuffle_questions');
            $validated['show_results'] = $request->boolean('show_results');
            $validated['is_live'] = $request->boolean('is_live');

            if ($validated['is_live'] && (empty($validated['end_time']) || now()->gte($validated['end_time']))) {
                $validated['start_time'] = now();
                $validated['end_time'] = now()->addMinutes((int) $validated['duration_minutes']);
            }

            $exam->update($validated);

            return redirect()->route('admin.exams.show', $exam)
                ->with('success', 'Exam settings updated successfully!');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subject_id' => 'required|exists:subjects,id',
            'school_class_id' => 'required|exists:school_classes,id',
            'target_class_ids' => 'nullable|array',
            'target_class_ids.*' => 'exists:school_classes,id',
            'created_by' => 'required|exists:users,id',
            'duration_minutes' => 'required|integer|min:1|max:300',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after:start_time',
            'shuffle_questions' => 'boolean',
            'show_results' => 'boolean',
            'is_live' => 'boolean',
            'allow_review' => 'boolean',
        ]);

        $this->ensureSubjectBelongsToClass((int) $validated['subject_id'], (int) $validated['school_class_id']);

        $validated['target_class_ids'] = $this->targetClassIds((int) $validated['school_class_id'], $validated['target_class_ids'] ?? []);
        $validated['shuffle_questions'] = $request->boolean('shuffle_questions');
        $validated['show_results'] = $request->boolean('show_results');
        $validated['is_live'] = $request->boolean('is_live');
        $validated['allow_review'] = $request->boolean('allow_review');

        if ($validated['is_live'] && (empty($validated['end_time']) || now()->gte($validated['end_time']))) {
            $validated['start_time'] = now();
            $validated['end_time'] = now()->addMinutes((int) $validated['duration_minutes']);
        }

        $exam->update($validated);

        return redirect()->route('admin.exams')->with('success', 'Exam updated successfully!');
    }

    public function examDestroy(Exam $exam)
    {
        $exam->delete();
        return redirect()->route('admin.exams')->with('success', 'Exam deleted successfully!');
    }

    public function questionDestroy(Question $question)
    {
        $exam = $question->exam;
        if ($question->image_path) {
            Storage::disk('public')->delete($question->image_path);
        }
        $question->delete();
        return redirect()->route('admin.exams.edit', $exam->id)->with('success', 'Question deleted successfully!');
    }

    public function toggleExamLive(Exam $exam)
    {
        $payload = ['is_live' => ! $exam->is_live];

        if (! $exam->is_live && (! $exam->end_time || $exam->end_time->lt(now()))) {
            $payload['start_time'] = now();
            $payload['end_time'] = now()->addMinutes($exam->duration_minutes);
        }

        $exam->update($payload);

        return back()->with('success', $exam->is_live ? 'Exam published.' : 'Exam moved offline.');
    }

    public function generateQuestions(Request $request, Exam $exam)
    {
        $validated = $request->validate([
            'topic' => 'required|string|max:255',
            'number_of_questions' => 'required|integer|min:1|max:20',
            'points_per_question' => 'required|integer|min:1|max:100',
            'overall_points' => 'required|integer|min:1|max:2000',
            'difficulty' => 'required|in:easy,medium,hard',
        ]);

        $expectedGeneratedPoints = (int) $validated['number_of_questions'] * (int) $validated['points_per_question'];

        if ((int) $validated['overall_points'] !== $expectedGeneratedPoints) {
            return response()->json([
                'success' => false,
                'message' => "Overall points must equal number of questions x points per question ({$expectedGeneratedPoints}).",
            ], 422);
        }

        try {
            $questions = $this->aiService->generateQuestions(
                $validated['topic'],
                (int) $validated['number_of_questions'],
                $validated['difficulty'],
                (int) $validated['points_per_question'],
                (int) $validated['overall_points']
            );

            if (empty($questions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate questions. Please try again.',
                ], 500);
            }

            $createdQuestions = [];
            $currentOrder = (int) $exam->questions()->max('order');

            foreach ($questions as $questionData) {
                $createdQuestions[] = Question::create([
                    'exam_id' => $exam->id,
                    'question_text' => $questionData['question_text'],
                    'option_a' => $questionData['option_a'],
                    'option_b' => $questionData['option_b'],
                    'option_c' => $questionData['option_c'],
                    'option_d' => $questionData['option_d'],
                    'correct_answer' => $questionData['correct_answer'],
                    'points' => $questionData['points'],
                    'is_ai_generated' => true,
                    'order' => ++$currentOrder,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Successfully generated ' . count($createdQuestions) . ' questions!',
                'questions' => $createdQuestions,
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating questions: ' . $exception->getMessage(),
            ], 500);
        }
    }

    public function addManualQuestion(Request $request, Exam $exam)
    {
        $validated = $request->validate([
            'question_text' => 'required|string',
            'option_a' => 'required|string',
            'option_b' => 'required|string',
            'option_c' => 'required|string',
            'option_d' => 'required|string',
            'correct_answer' => 'required|in:A,B,C,D',
            'points' => 'required|integer|min:1',
            'question_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $imagePath = $request->hasFile('question_image')
            ? $request->file('question_image')->store('question-images', 'public')
            : null;

        $question = Question::create([
            'exam_id' => $exam->id,
            'question_text' => $this->sanitizeQuestionHtml($validated['question_text']),
            'option_a' => $validated['option_a'],
            'option_b' => $validated['option_b'],
            'option_c' => $validated['option_c'],
            'option_d' => $validated['option_d'],
            'correct_answer' => $validated['correct_answer'],
            'image_path' => $imagePath,
            'points' => $validated['points'],
            'is_ai_generated' => false,
            'order' => ((int) $exam->questions()->max('order')) + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Question added successfully!',
            'question' => $question,
        ]);
    }

    public function deleteQuestion(Question $question)
    {
        if ($question->image_path) {
            Storage::disk('public')->delete($question->image_path);
        }

        $question->delete();

        return response()->json([
            'success' => true,
            'message' => 'Question deleted successfully!',
        ]);
    }

    private function ensureSubjectBelongsToClass(int $subjectId, int $classId): void
    {
        abort_unless(
            Subject::where('id', $subjectId)->where('school_class_id', $classId)->exists(),
            422,
            'The selected subject does not belong to the selected class.'
        );
    }

    private function targetClassIds(int $baseClassId, array $targetClassIds): array
    {
        $baseClass = SchoolClass::findOrFail($baseClassId);
        $targetClassIds = collect($targetClassIds ?: [$baseClassId])
            ->push($baseClassId)
            ->filter()
            ->map(fn ($classId) => (int) $classId)
            ->unique()
            ->values();

        $validCount = SchoolClass::whereIn('id', $targetClassIds)
            ->where('level', $baseClass->level)
            ->count();

        abort_unless($validCount === $targetClassIds->count(), 422, 'Exam target classes must be in the selected class level.');

        return $targetClassIds->all();
    }

    private function normalizeExamBooleanFields(Request $request): void
    {
        foreach (['shuffle_questions', 'show_results', 'is_live', 'allow_review'] as $field) {
            $request->merge([$field => $request->boolean($field)]);
        }
    }

    private function applyExamSearch($query, string $search): void
    {
        $search = strtolower(trim($search));

        $query->where(function ($query) use ($search) {
            $query->whereRaw('LOWER(title) like ?', ["%{$search}%"])
                ->orWhereHas('subject', fn ($query) => $query->whereRaw('LOWER(name) like ?', ["%{$search}%"]))
                ->orWhereHas('schoolClass', function ($query) use ($search) {
                    $query->whereRaw('LOWER(name) like ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(level) like ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(stream) like ?', ["%{$search}%"]);
                })
                ->orWhereHas('creator', function ($query) use ($search) {
                    $fullNameExpression = config('database.default') === 'sqlite'
                        ? "LOWER(first_name || ' ' || last_name)"
                        : "LOWER(CONCAT(first_name, ' ', last_name))";

                    $query->whereRaw('LOWER(first_name) like ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(last_name) like ?', ["%{$search}%"])
                        ->orWhereRaw($fullNameExpression . ' like ?', ["%{$search}%"]);
                });
        });
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    private function sanitizeQuestionHtml(string $html): string
    {
        $allowedTags = '<p><br><strong><b><em><i><u><s><ol><ul><li><blockquote><code><pre><sub><sup><span>';
        $cleanHtml = strip_tags($html, $allowedTags);

        if (! class_exists(\DOMDocument::class)) {
            return $cleanHtml;
        }

        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<div>' . $cleanHtml . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        foreach ($document->getElementsByTagName('*') as $node) {
            while ($node->attributes && $node->attributes->length > 0) {
                $node->removeAttributeNode($node->attributes->item(0));
            }
        }

        $wrapper = $document->getElementsByTagName('div')->item(0);
        $output = '';

        if ($wrapper) {
            foreach ($wrapper->childNodes as $child) {
                $output .= $document->saveHTML($child);
            }
        }

        return trim($output);
    }
}
