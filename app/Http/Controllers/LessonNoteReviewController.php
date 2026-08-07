<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\LessonNote;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LessonNoteReviewController extends Controller
{
    public function index(Request $request)
    {
        $notes = LessonNote::with(['teacher', 'schoolClass', 'subject', 'academicSession'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('teacher_id'), fn ($query) => $query->where('teacher_id', $request->query('teacher_id')))
            ->when($request->filled('school_class_id'), fn ($query) => $query->where('school_class_id', $request->query('school_class_id')))
            ->when($request->filled('subject_id'), fn ($query) => $query->where('subject_id', $request->query('subject_id')))
            ->when($request->filled('week'), fn ($query) => $query->where('week_number', $request->query('week')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('lesson-notes.review-index', [
            'notes' => $notes,
            'teachers' => User::where('role', 'teacher')->orderBy('first_name')->get(),
            'routePrefix' => $this->routePrefix(),
        ]);
    }

    public function show(LessonNote $lessonNote)
    {
        $lessonNote->load(['teacher', 'schoolClass', 'subject', 'academicSession', 'attachments', 'exercise.questions', 'reviews.reviewer']);

        return view('lesson-notes.review-show', [
            'note' => $lessonNote,
            'routePrefix' => $this->routePrefix(),
        ]);
    }

    public function archives(Request $request)
    {
        $notes = LessonNote::with(['teacher', 'schoolClass', 'subject', 'academicSession', 'reviews.reviewer'])
            ->where('status', LessonNote::STATUS_ARCHIVED)
            ->when($request->filled('teacher_id'), fn ($query) => $query->where('teacher_id', $request->query('teacher_id')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('lesson-notes.archives', [
            'notes' => $notes,
            'teachers' => User::where('role', 'teacher')->orderBy('first_name')->get(),
            'routePrefix' => $this->routePrefix(),
        ]);
    }

    public function approve(LessonNote $lessonNote)
    {
        abort_if((int) $lessonNote->teacher_id === (int) Auth::id(), 403);
        abort_unless(in_array($lessonNote->status, [LessonNote::STATUS_PENDING, LessonNote::STATUS_RETURNED], true), 403);

        DB::transaction(function () use ($lessonNote) {
            $lessonNote = LessonNote::whereKey($lessonNote->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lessonNote->status, [LessonNote::STATUS_PENDING, LessonNote::STATUS_RETURNED], true), 403);
            $lessonNote->update([
                'status' => LessonNote::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by' => Auth::id(),
                'published_at' => now(),
            ]);
            $lessonNote->reviews()->create([
                'reviewer_id' => Auth::id(),
                'action' => LessonNote::STATUS_APPROVED,
                'comments' => null,
                'reviewed_at' => now(),
            ]);
        });

        return back()->with('success', 'Lesson note approved.');
    }

    public function return(Request $request, LessonNote $lessonNote)
    {
        return $this->reviewWithReason($request, $lessonNote, LessonNote::STATUS_RETURNED, 'Lesson note returned for correction.');
    }

    public function reject(Request $request, LessonNote $lessonNote)
    {
        return $this->reviewWithReason($request, $lessonNote, LessonNote::STATUS_REJECTED, 'Lesson note rejected.');
    }

    public function archive(LessonNote $lessonNote)
    {
        abort_unless($lessonNote->status === LessonNote::STATUS_APPROVED, 403);
        $lessonNote->update(['status' => LessonNote::STATUS_ARCHIVED]);
        $lessonNote->reviews()->create([
            'reviewer_id' => Auth::id(),
            'action' => LessonNote::STATUS_ARCHIVED,
            'comments' => null,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Lesson note archived.');
    }

    public function restore(LessonNote $lessonNote)
    {
        abort_unless($lessonNote->status === LessonNote::STATUS_ARCHIVED, 403);

        $lessonNote->update([
            'status' => LessonNote::STATUS_APPROVED,
            'published_at' => $lessonNote->published_at ?: now(),
        ]);
        $lessonNote->reviews()->create([
            'reviewer_id' => Auth::id(),
            'action' => LessonNote::STATUS_APPROVED,
            'comments' => 'Restored from archive.',
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Lesson note restored.');
    }

    public function destroy(LessonNote $lessonNote)
    {
        abort_unless(in_array($lessonNote->status, [LessonNote::STATUS_APPROVED, LessonNote::STATUS_ARCHIVED, LessonNote::STATUS_REJECTED], true), 403);

        $lessonNote->delete();

        return redirect()->route($this->routePrefix() . '.lesson-notes.archives')
            ->with('success', 'Lesson note permanently deleted.');
    }

    private function reviewWithReason(Request $request, LessonNote $lessonNote, string $status, string $message)
    {
        abort_if((int) $lessonNote->teacher_id === (int) Auth::id(), 403);
        abort_unless(in_array($lessonNote->status, [LessonNote::STATUS_PENDING, LessonNote::STATUS_APPROVED, LessonNote::STATUS_RETURNED], true), 403);
        $validated = $request->validate(['comments' => 'required|string|max:5000']);

        DB::transaction(function () use ($lessonNote, $status, $validated) {
            $lessonNote = LessonNote::whereKey($lessonNote->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lessonNote->status, [LessonNote::STATUS_PENDING, LessonNote::STATUS_APPROVED, LessonNote::STATUS_RETURNED], true), 403);
            $lessonNote->update(['status' => $status]);
            $lessonNote->reviews()->create([
                'reviewer_id' => Auth::id(),
                'action' => $status,
                'comments' => $validated['comments'],
                'reviewed_at' => now(),
            ]);
        });

        return back()->with('success', $message);
    }

    private function routePrefix(): string
    {
        return str_starts_with((string) request()->route()->getName(), 'hod.') ? 'hod' : 'admin';
    }
}
