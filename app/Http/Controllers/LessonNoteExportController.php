<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\LessonNote;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LessonNoteExportController extends Controller
{
    public function subject(Request $request)
    {
        $validated = $request->validate([
            'academic_session_id' => ['nullable', 'exists:academic_sessions,id'],
            'school_class_id' => ['nullable', 'exists:school_classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'teacher_id' => ['nullable', 'exists:users,id'],
            'format' => ['required', 'in:pdf,word'],
        ]);

        $user = Auth::user();
        $session = isset($validated['academic_session_id'])
            ? AcademicSession::find($validated['academic_session_id'])
            : AcademicSession::active()->first();
        $subject = Subject::with('schoolClass')->findOrFail($validated['subject_id']);
        $schoolClass = isset($validated['school_class_id'])
            ? SchoolClass::find($validated['school_class_id'])
            : $subject->schoolClass;

        abort_unless($schoolClass, 422, 'Select a class before downloading this subject note.');

        $notesQuery = LessonNote::with(['academicSession', 'schoolClass', 'subject', 'teacher', 'exercise.questions'])
            ->where('subject_id', $subject->id)
            ->where('school_class_id', $schoolClass->id)
            ->when($session, fn ($query) => $query->where('academic_session_id', $session->id));

        $teacher = null;

        if (in_array($user->role, ['student', 'prefect'], true)) {
            abort_unless((int) $user->school_class_id === (int) $schoolClass->id, 403);
            $notesQuery->where('status', LessonNote::STATUS_APPROVED);
        } elseif ($user->role === 'teacher') {
            $teacher = $user;
            $notesQuery->where('teacher_id', $user->id)
                ->where('status', '!=', LessonNote::STATUS_ARCHIVED);
        } elseif (in_array($user->role, ['admin', 'hod'], true)) {
            if (!empty($validated['teacher_id'])) {
                $teacher = User::find($validated['teacher_id']);
                $notesQuery->where('teacher_id', $teacher?->id);
            }

            $notesQuery->whereNotIn('status', [LessonNote::STATUS_ARCHIVED, LessonNote::STATUS_REJECTED]);
        } else {
            abort(403);
        }

        $notes = $notesQuery
            ->orderBy('week_number')
            ->orderBy('lesson_date')
            ->get();

        abort_if($notes->isEmpty(), 404, 'No lesson notes are available for this subject yet.');

        $teacher ??= $notes->first()->teacher;
        $settings = SchoolSetting::current();
        $payload = [
            'notes' => $notes,
            'settings' => $settings,
            'logoDataUri' => $this->logoDataUri($settings),
            'schoolClass' => $schoolClass,
            'subject' => $subject,
            'teacher' => $teacher,
            'session' => $session ?? $notes->first()->academicSession,
            'startWeek' => $notes->min('week_number'),
            'endWeek' => $notes->max('week_number'),
            'generatedFor' => $user,
        ];

        $filename = $this->filename($settings, $subject, $schoolClass, $payload['startWeek'], $payload['endWeek']);

        if ($validated['format'] === 'word') {
            return response()
                ->view('lesson-notes.exports.subject-word', $payload)
                ->header('Content-Type', 'application/msword; charset=UTF-8')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '.doc"');
        }

        return Pdf::loadView('lesson-notes.exports.subject-pdf', $payload)
            ->setPaper('a4')
            ->download($filename . '.pdf');
    }

    private function logoDataUri(SchoolSetting $settings): ?string
    {
        if (!$settings->logo_path || !Storage::disk('public')->exists($settings->logo_path)) {
            return null;
        }

        $path = Storage::disk('public')->path($settings->logo_path);
        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
    }

    private function filename(SchoolSetting $settings, Subject $subject, SchoolClass $schoolClass, int $startWeek, int $endWeek): string
    {
        return Str::slug($settings->school_name . '-' . $schoolClass->full_name . '-' . $subject->name . '-weeks-' . $startWeek . '-' . $endWeek);
    }
}
