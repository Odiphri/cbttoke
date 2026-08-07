<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\LessonNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LessonNoteController extends Controller
{
    public function index(Request $request)
    {
        $student = Auth::user();
        $notes = LessonNote::approved()
            ->with(['academicSession', 'subject', 'teacher', 'exercise.attempts' => fn ($query) => $query->where('student_id', $student->id)])
            ->where('school_class_id', $student->school_class_id)
            ->when($request->filled('week'), fn ($query) => $query->where('week_number', $request->query('week')))
            ->when($request->filled('subject_id'), fn ($query) => $query->where('subject_id', $request->query('subject_id')))
            ->when($request->filled('search'), fn ($query) => $query->whereRaw('LOWER(topic) like ?', ['%' . strtolower($request->query('search')) . '%']))
            ->orderBy('week_number')
            ->latest('published_at')
            ->get()
            ->groupBy('week_number');

        return view('student.lesson-notes.index', [
            'notesByWeek' => $notes,
            'activeSession' => AcademicSession::active()->first(),
            'subjects' => LessonNote::approved()->where('school_class_id', $student->school_class_id)->with('subject')->get()->pluck('subject')->filter()->unique('id'),
        ]);
    }

    public function show(LessonNote $lessonNote)
    {
        $student = Auth::user();
        abort_unless($lessonNote->isApproved() && (int) $lessonNote->school_class_id === (int) $student->school_class_id, 403);
        $lessonNote->load(['academicSession', 'subject', 'teacher', 'attachments', 'exercise.attempts' => fn ($query) => $query->where('student_id', $student->id)]);

        return view('student.lesson-notes.show', ['note' => $lessonNote]);
    }
}
