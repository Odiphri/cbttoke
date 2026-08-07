<?php

namespace App\Http\Controllers\HOD;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\LessonNote;

class DashboardController extends Controller
{
    public function index()
    {
        $activeSession = AcademicSession::active()->first();

        return view('hod.dashboard', [
            'pendingLessonNotesCount' => LessonNote::where('status', LessonNote::STATUS_PENDING)->count(),
            'approvedLessonNotesThisTermCount' => LessonNote::where('status', LessonNote::STATUS_APPROVED)
                ->when($activeSession, fn ($query) => $query->where('academic_session_id', $activeSession->id))
                ->count(),
            'returnedLessonNotesThisTermCount' => LessonNote::whereIn('status', [LessonNote::STATUS_RETURNED, LessonNote::STATUS_REJECTED])
                ->when($activeSession, fn ($query) => $query->where('academic_session_id', $activeSession->id))
                ->count(),
        ]);
    }
}
