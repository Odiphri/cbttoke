<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Payment;
use App\Models\Question;
use App\Models\SchoolSetting;
use App\Models\Attendance;
use App\Models\ChangeRequest;
use App\Models\Override;
use App\Models\LessonNote;
use App\Services\AIService;
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
        $recentExams = Exam::latest()->take(5)->get();
        $pendingRequests = ChangeRequest::where('status', 'pending')->latest()->take(5)->get();

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
            'examStats'
        ));
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

    public function reports()
    {
        return view('admin.reports.index');
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
