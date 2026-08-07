<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\LessonNote;
use Illuminate\Support\Facades\Auth;

class LessonExerciseController extends Controller
{
    public function show(LessonNote $lessonNote)
    {
        abort_unless((int) $lessonNote->teacher_id === (int) Auth::id(), 403);
        $lessonNote->load('exercise.questions');

        return view('teacher.lesson-notes.show', ['note' => $lessonNote]);
    }
}
