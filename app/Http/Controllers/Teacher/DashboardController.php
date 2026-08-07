<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Exam;
use App\Models\ExerciseAttempt;
use App\Models\LessonNote;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $teacher = Auth::user();

        return view('teacher.dashboard', [
            'myExamsCount' => Exam::where('created_by', $teacher->id)->count(),
            'liveExamsCount' => Exam::where('created_by', $teacher->id)->where('is_live', true)->count(),
            'attendanceMarkedCount' => Attendance::where('marked_by', $teacher->id)->count(),
            'lessonNotesCount' => LessonNote::where('teacher_id', $teacher->id)->count(),
            'pendingLessonNotesCount' => LessonNote::where('teacher_id', $teacher->id)->where('status', LessonNote::STATUS_PENDING)->count(),
            'returnedLessonNotesCount' => LessonNote::where('teacher_id', $teacher->id)->whereIn('status', [LessonNote::STATUS_RETURNED, LessonNote::STATUS_REJECTED])->count(),
            'exerciseMarkingCount' => ExerciseAttempt::whereHas('exercise.lessonNote', fn ($query) => $query->where('teacher_id', $teacher->id))
                ->where('status', ExerciseAttempt::STATUS_AWAITING_MARKING)
                ->count(),
        ]);
    }
}
