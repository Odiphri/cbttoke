<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Payment;
use App\Models\Attendance;
use App\Models\ExerciseAttempt;
use App\Models\LessonExercise;
use App\Models\LessonNote;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function index()
    {
        $student = Auth::user();
        $eligibleClassIds = $this->eligibleClassIds($student);
        
        if ($eligibleClassIds->isEmpty() && ! $student->isPrefect()) {
            return view('student.dashboard', [
                'student' => $student,
                'recentExams' => collect(),
                'examStats' => [
                    'total' => 0,
                    'completed' => 0,
                    'pending' => 0
                ],
                'paymentStatus' => null,
                'attendanceStats' => [
                    'present' => 0,
                    'absent' => 0,
                    'total' => 0
                ],
                'lessonStats' => ['new_notes' => 0, 'exercises_due' => 0, 'awaiting_marking' => 0],
            ]);
        }
        
        // Only live and currently available exams are visible to students.
        $availableExamQuery = Exam::query()
            ->when(! $student->isPrefect(), fn ($query) => $query->whereIn('school_class_id', $eligibleClassIds))
            ->where('is_live', true);

        $recentExams = (clone $availableExamQuery)
            ->with(['subject', 'attempts' => function($query) use ($student) {
                $query->where('student_id', $student->id);
            }])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
            
        // Calculate exam statistics
        $totalExams = (clone $availableExamQuery)->count();
        $completedExams = ExamAttempt::where('student_id', $student->id)
            ->where('is_submitted', true)
            ->count();
        $pendingExams = $totalExams - $completedExams;
        
        $examStats = [
            'total' => $totalExams,
            'completed' => $completedExams,
            'pending' => $pendingExams
        ];
        
        // Get payment status
        $paymentStatus = $student->school_class_id
            ? Payment::where('student_id', $student->id)
                ->where('school_class_id', $student->school_class_id)
                ->first()
            : null;
            
        // Get attendance statistics
        $attendanceStats = [
            'present' => Attendance::where('student_id', $student->id)
                ->where('status', 'present')
                ->count(),
            'absent' => Attendance::where('student_id', $student->id)
                ->where('status', 'absent')
                ->count(),
            'total' => Attendance::where('student_id', $student->id)->count()
        ];

        $lessonStats = [
            'new_notes' => LessonNote::approved()->whereIn('school_class_id', $eligibleClassIds)->where('published_at', '>=', now()->subDays(7))->count(),
            'exercises_due' => LessonExercise::whereHas('lessonNote', fn ($query) => $query->approved()->whereIn('school_class_id', $eligibleClassIds))
                ->where(fn ($query) => $query->whereNull('due_at')->orWhere('due_at', '>=', now()))
                ->count(),
            'awaiting_marking' => ExerciseAttempt::where('student_id', $student->id)->where('status', ExerciseAttempt::STATUS_AWAITING_MARKING)->count(),
        ];
        
        return view('student.dashboard', compact(
            'student',
            'recentExams',
            'examStats',
            'paymentStatus',
            'attendanceStats',
            'lessonStats'
        ));
    }

    public function payments()
    {
        $student = Auth::user();
        
        $payments = Payment::where('student_id', $student->id)
            ->with('schoolClass')
            ->latest()
            ->get();
            
        $totalFees = $payments->sum('total_fees');
        $paidAmount = $payments->sum('amount_paid');
        $balance = $totalFees - $paidAmount;
        
        return view('student.payments.index', compact('payments', 'totalFees', 'paidAmount', 'balance'));
    }

    public function attendance()
    {
        $student = Auth::user();
        
        $attendance = Attendance::where('student_id', $student->id)
            ->with('schoolClass')
            ->latest()
            ->paginate(20);
            
        $stats = [
            'present' => Attendance::where('student_id', $student->id)
                ->where('status', 'present')
                ->count(),
            'absent' => Attendance::where('student_id', $student->id)
                ->where('status', 'absent')
                ->count(),
            'total' => Attendance::where('student_id', $student->id)->count()
        ];
        
        return view('student.attendance.index', compact('attendance', 'stats'));
    }

    private function eligibleClassIds($student): Collection
    {
        return collect([$student->school_class_id])
            ->merge($student->subjects()->pluck('subjects.school_class_id'))
            ->filter()
            ->map(fn ($classId) => (int) $classId)
            ->unique()
            ->values();
    }
}
