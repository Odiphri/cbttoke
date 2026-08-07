<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ExerciseAttempt;
use App\Models\ExerciseQuestion;
use App\Models\LessonExercise;
use App\Models\LessonNote;
use App\Models\LessonNoteAttachment;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Smalot\PdfParser\Parser;

class LessonNoteController extends Controller
{
    public function __construct(private AIService $aiService)
    {
    }

    public function index(Request $request)
    {
        $teacher = Auth::user();
        $notes = LessonNote::with(['academicSession', 'schoolClass', 'subject', 'exercise.questions'])
            ->withCount('attachments')
            ->where('teacher_id', $teacher->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('week'), fn ($query) => $query->where('week_number', $request->query('week')))
            ->when($request->filled('school_class_id'), fn ($query) => $query->where('school_class_id', $request->query('school_class_id')))
            ->when($request->filled('subject_id'), fn ($query) => $query->where('subject_id', $request->query('subject_id')))
            ->when($request->filled('search'), fn ($query) => $this->applySearch($query, (string) $request->query('search')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('teacher.lesson-notes.index', [
            'notes' => $notes,
            'summary' => $this->summary($teacher->id),
            'classes' => $this->assignedClasses(),
            'subjects' => $this->assignedSubjects(),
        ]);
    }

    public function create()
    {
        $questions = collect();

        return view('teacher.lesson-notes.form', [
            'note' => new LessonNote(['week_number' => 1, 'status' => LessonNote::STATUS_DRAFT]),
            'activeSession' => $this->activeSession(),
            'classes' => $this->assignedClasses(),
            'subjects' => $this->assignedSubjects(),
            'exercise' => new LessonExercise(['attempt_mode' => LessonExercise::ATTEMPT_ONE, 'score_selection_method' => LessonExercise::SCORE_HIGHEST]),
            'questions' => $questions,
            'existingExerciseQuestions' => $this->existingExerciseQuestions($questions),
        ]);
    }

    public function store(Request $request)
    {
        $activeSession = $this->activeSession();
        $validated = $this->validatedNote($request);
        $this->ensureAssigned((int) $validated['school_class_id'], (int) $validated['subject_id']);
        $this->ensureNoDuplicate($activeSession->id, (int) $validated['week_number'], (int) $validated['school_class_id'], (int) $validated['subject_id']);

        $note = DB::transaction(function () use ($request, $validated, $activeSession) {
            $payload = $this->sanitizeNotePayload($validated);

            $note = LessonNote::create($payload + [
                'academic_session_id' => $activeSession->id,
                'teacher_id' => Auth::id(),
                'status' => $request->input('action') === 'submit' ? LessonNote::STATUS_PENDING : LessonNote::STATUS_DRAFT,
                'submitted_at' => $request->input('action') === 'submit' ? now() : null,
                'submitted_by' => $request->input('action') === 'submit' ? Auth::id() : null,
            ]);

            $this->syncExercise($request, $note);
            $this->storeAttachments($request, $note);

            return $note;
        });

        return redirect()->route('teacher.lesson-notes.show', $note)->with('success', 'Lesson note saved successfully.');
    }

    public function show(LessonNote $lessonNote)
    {
        $this->ensureOwner($lessonNote);
        $lessonNote->load(['academicSession', 'schoolClass', 'subject', 'attachments', 'exercise.questions', 'exercise.attempts.student', 'reviews.reviewer']);

        return view('teacher.lesson-notes.show', ['note' => $lessonNote]);
    }

    public function edit(LessonNote $lessonNote)
    {
        $this->ensureOwner($lessonNote);
        abort_unless($lessonNote->isEditable(), 403, 'This note is locked until it is returned, rejected, or withdrawn.');
        $lessonNote->load(['attachments', 'exercise.questions']);
        $questions = $lessonNote->exercise?->questions ?? collect();

        return view('teacher.lesson-notes.form', [
            'note' => $lessonNote,
            'activeSession' => $lessonNote->academicSession,
            'classes' => $this->assignedClasses(),
            'subjects' => $this->assignedSubjects(),
            'exercise' => $lessonNote->exercise ?: new LessonExercise(['attempt_mode' => LessonExercise::ATTEMPT_ONE, 'score_selection_method' => LessonExercise::SCORE_HIGHEST]),
            'questions' => $questions,
            'existingExerciseQuestions' => $this->existingExerciseQuestions($questions),
        ]);
    }

    private function existingExerciseQuestions($questions)
    {
        return $questions->map(fn (ExerciseQuestion $question) => [
            'question_type' => $question->question_type,
            'question_text' => $question->question_text,
            'options' => $question->options,
            'correct_answer' => $question->correct_answer,
            'marking_guide' => $question->marking_guide,
            'marks' => $question->marks,
            'image_path' => $question->image_path,
        ])->values();
    }

    public function update(Request $request, LessonNote $lessonNote)
    {
        $this->ensureOwner($lessonNote);
        abort_unless($lessonNote->isEditable(), 403);
        $validated = $this->validatedNote($request);
        $this->ensureAssigned((int) $validated['school_class_id'], (int) $validated['subject_id']);
        $this->ensureNoDuplicate($lessonNote->academic_session_id, (int) $validated['week_number'], (int) $validated['school_class_id'], (int) $validated['subject_id'], $lessonNote->id);

        DB::transaction(function () use ($request, $lessonNote, $validated) {
            $payload = $this->sanitizeNotePayload($validated);

            if ($request->input('action') === 'submit') {
                $payload['status'] = LessonNote::STATUS_PENDING;
                $payload['submitted_at'] = now();
                $payload['submitted_by'] = Auth::id();
            } elseif ($lessonNote->status === LessonNote::STATUS_REJECTED) {
                $payload['status'] = LessonNote::STATUS_DRAFT;
            }

            $lessonNote->update($payload);
            $this->syncExercise($request, $lessonNote);
            $this->storeAttachments($request, $lessonNote);
        });

        return redirect()->route('teacher.lesson-notes.show', $lessonNote)->with('success', 'Lesson note updated successfully.');
    }

    public function submit(LessonNote $lessonNote)
    {
        $this->ensureOwner($lessonNote);
        abort_unless($lessonNote->isEditable(), 403);

        $lessonNote->update([
            'status' => LessonNote::STATUS_PENDING,
            'submitted_at' => now(),
            'submitted_by' => Auth::id(),
        ]);

        return back()->with('success', 'Lesson note submitted for approval.');
    }

    public function withdraw(LessonNote $lessonNote)
    {
        $this->ensureOwner($lessonNote);
        abort_unless(in_array($lessonNote->status, [LessonNote::STATUS_PENDING, LessonNote::STATUS_APPROVED], true), 403);

        $lessonNote->update(['status' => LessonNote::STATUS_DRAFT]);

        return back()->with('success', 'Lesson note moved back to draft.');
    }

    public function destroyAttachment(LessonNote $lessonNote, LessonNoteAttachment $attachment)
    {
        $this->ensureOwner($lessonNote);
        abort_unless($lessonNote->isEditable() && (int) $attachment->lesson_note_id === (int) $lessonNote->id, 403);
        Storage::disk('public')->delete($attachment->stored_path);
        $attachment->delete();

        return back()->with('success', 'Attachment removed.');
    }

    public function uploadInlineImage(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $path = $validated['file']->store('lesson-note-inline-images', 'public');

        return response()->json([
            'location' => asset('storage/' . $path),
        ]);
    }

    public function generateAiDraft(Request $request)
    {
        $validated = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'week_number' => 'required|integer|min:1|max:15',
            'topic' => 'required|string|max:255',
            'subtopic' => 'nullable|string|max:255',
            'source_text' => 'nullable|string|max:30000',
            'target_words' => 'nullable|integer|min:1000|max:1000000',
        ]);

        $this->ensureAssigned((int) $validated['school_class_id'], (int) $validated['subject_id']);

        $class = SchoolClass::findOrFail($validated['school_class_id']);
        $subject = Subject::findOrFail($validated['subject_id']);
        try {
            $draft = $this->aiService->generateLessonNoteDraft([
                'class' => $class->full_name,
                'subject' => $subject->name,
                'week' => $validated['week_number'],
                'topic' => $validated['topic'],
                'subtopic' => $validated['subtopic'] ?? null,
                'source_text' => $validated['source_text'] ?? '',
                'target_words' => $validated['target_words'] ?? 5000,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        $draft = $this->sanitizeLessonDraft($draft);

        return response()->json(['success' => true, 'draft' => $draft]);
    }

    public function generateDraftFromPdf(Request $request)
    {
        $validated = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'week_number' => 'required|integer|min:1|max:15',
            'topic' => 'required|string|max:255',
            'subtopic' => 'nullable|string|max:255',
            'source_text' => 'nullable|string|max:30000',
            'target_words' => 'nullable|integer|min:1000|max:1000000',
            'pdf_file' => 'required|file|mimes:pdf|max:10240',
        ]);

        $this->ensureAssigned((int) $validated['school_class_id'], (int) $validated['subject_id']);

        $parser = new Parser();
        $pdf = $parser->parseFile($request->file('pdf_file')->getRealPath());
        $text = trim(preg_replace('/\s+/', ' ', $pdf->getText()));

        abort_if($text === '', 422, 'Could not extract readable text from this PDF.');

        $class = SchoolClass::findOrFail($validated['school_class_id']);
        $subject = Subject::findOrFail($validated['subject_id']);
        try {
            $draft = $this->aiService->generateLessonNoteDraft([
                'class' => $class->full_name,
                'subject' => $subject->name,
                'week' => $validated['week_number'],
                'topic' => $validated['topic'],
                'subtopic' => $validated['subtopic'] ?? null,
                'source_text' => trim(($validated['source_text'] ?? '') . "\n\n" . $text),
                'target_words' => $validated['target_words'] ?? 5000,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        $draft = $this->sanitizeLessonDraft($draft);

        return response()->json([
            'success' => true,
            'draft' => $draft,
            'extracted_characters' => mb_strlen($text),
        ]);
    }

    public function continueAiDraft(Request $request)
    {
        $validated = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'topic' => 'required|string|max:255',
            'subtopic' => 'nullable|string|max:255',
            'existing_content' => 'required|string|max:200000',
            'target_words' => 'nullable|integer|min:1000|max:12000',
        ]);

        $this->ensureAssigned((int) $validated['school_class_id'], (int) $validated['subject_id']);

        $class = SchoolClass::findOrFail($validated['school_class_id']);
        $subject = Subject::findOrFail($validated['subject_id']);

        try {
            $draft = $this->aiService->continueLessonNote([
                'class' => $class->full_name,
                'subject' => $subject->name,
                'topic' => $validated['topic'],
                'subtopic' => $validated['subtopic'] ?? null,
                'existing_content' => $validated['existing_content'],
                'target_words' => $validated['target_words'] ?? 4000,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'draft' => [
                'main_content' => $this->sanitizeHtml((string) ($draft['main_content'] ?? '')),
            ],
        ]);
    }

    public function generateExerciseDraft(Request $request)
    {
        $validated = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'topic' => 'required|string|max:255',
            'note_content' => 'nullable|string|max:120000',
            'number_of_questions' => 'required|integer|min:1|max:50',
            'difficulty' => 'required|in:easy,medium,hard',
            'marks_per_question' => 'required|numeric|min:0.5|max:100',
            'overall_points' => 'required|numeric|min:0.5|max:5000',
        ]);

        $this->ensureAssigned((int) $validated['school_class_id'], (int) $validated['subject_id']);

        $expectedGeneratedPoints = (float) $validated['number_of_questions'] * (float) $validated['marks_per_question'];
        if (abs((float) $validated['overall_points'] - $expectedGeneratedPoints) > 0.01) {
            return response()->json([
                'success' => false,
                'message' => "Overall points must equal number of questions x marks per question ({$expectedGeneratedPoints}).",
            ], 422);
        }

        $topic = trim($validated['topic'] . "\n\nLesson note source:\n" . strip_tags($validated['note_content'] ?? ''));

        try {
            $questions = $this->aiService->generateLessonExerciseQuestions(
                $topic,
                (int) $validated['number_of_questions'],
                $validated['difficulty'],
                (float) $validated['marks_per_question']
            );
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        if (empty($questions)) {
            return response()->json([
                'success' => false,
                'message' => 'AI did not return usable exercise questions.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => count($questions) . ' exercise question(s) generated.',
            'questions' => array_map(fn (array $question) => [
                'question_type' => $question['question_type'] ?? ExerciseQuestion::TYPE_OBJECTIVE,
                'question_text' => $this->sanitizeHtml($question['question_text'] ?? ''),
                'options' => $this->sanitizeOptions($question['options'] ?? null),
                'correct_answer' => $this->normalizeExerciseAnswer($question),
                'marking_guide' => filled($question['marking_guide'] ?? null) ? $this->sanitizeHtml($question['marking_guide']) : null,
                'marks' => (float) ($question['marks'] ?? $validated['marks_per_question']),
            ], $questions),
        ]);
    }

    private function validatedNote(Request $request): array
    {
        return $request->validate([
            'week_number' => 'required|integer|min:1|max:15',
            'school_class_id' => 'required|exists:school_classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'title' => 'required|string|max:255',
            'topic' => 'required|string|max:255',
            'subtopic' => 'nullable|string|max:255',
            'lesson_date' => 'nullable|date',
            'previous_knowledge' => 'nullable|string',
            'learning_objectives' => 'nullable|string',
            'teaching_materials' => 'nullable|string',
            'introduction' => 'nullable|string',
            'main_content' => 'required|string',
            'evaluation' => 'nullable|string',
            'conclusion' => 'nullable|string',
            'assignment' => 'nullable|string',
            'attachments.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png,webp|max:5120',
        ]);
    }

    private function sanitizeNotePayload(array $payload): array
    {
        foreach ([
            'previous_knowledge',
            'learning_objectives',
            'teaching_materials',
            'introduction',
            'main_content',
            'evaluation',
            'conclusion',
            'assignment',
        ] as $field) {
            if (array_key_exists($field, $payload) && filled($payload[$field])) {
                $payload[$field] = $this->sanitizeHtml($payload[$field]);
            }
        }

        return $payload;
    }

    private function sanitizeLessonDraft(array $draft): array
    {
        foreach ([
            'previous_knowledge',
            'learning_objectives',
            'teaching_materials',
            'introduction',
            'main_content',
            'evaluation',
            'conclusion',
            'assignment',
        ] as $field) {
            $draft[$field] = $this->sanitizeHtml((string) ($draft[$field] ?? ''));
        }

        return $draft;
    }

    private function syncExercise(Request $request, LessonNote $note): void
    {
        if (! $request->boolean('has_exercise')) {
            return;
        }

        $validated = $request->validate([
            'exercise_title' => 'required|string|max:255',
            'exercise_instructions' => 'nullable|string',
            'opens_at' => 'nullable|date',
            'due_at' => 'nullable|date|after_or_equal:opens_at',
            'attempt_mode' => ['required', Rule::in([LessonExercise::ATTEMPT_ONE, LessonExercise::ATTEMPT_LIMITED, LessonExercise::ATTEMPT_UNLIMITED])],
            'max_attempts' => 'nullable|required_if:attempt_mode,limited|integer|min:1',
            'score_selection_method' => ['required', Rule::in([LessonExercise::SCORE_HIGHEST, LessonExercise::SCORE_LATEST, LessonExercise::SCORE_FIRST])],
        ]);

        $exercise = $note->exercise()->updateOrCreate([], [
            'title' => $validated['exercise_title'],
            'instructions' => filled($validated['exercise_instructions'] ?? null) ? $this->sanitizeHtml($validated['exercise_instructions']) : null,
            'opens_at' => $validated['opens_at'] ?? null,
            'due_at' => $validated['due_at'] ?? null,
            'allow_late_submission' => $request->boolean('allow_late_submission'),
            'shuffle_questions' => $request->boolean('shuffle_questions'),
            'shuffle_options' => $request->boolean('shuffle_options'),
            'show_score_immediately' => $request->boolean('show_score_immediately', true),
            'reveal_correct_answers' => $request->boolean('reveal_correct_answers'),
            'attempt_mode' => $validated['attempt_mode'],
            'max_attempts' => $validated['attempt_mode'] === LessonExercise::ATTEMPT_LIMITED ? $validated['max_attempts'] : null,
            'score_selection_method' => $validated['score_selection_method'],
        ]);

        $this->syncExerciseQuestions($request, $exercise);
    }

    private function syncExerciseQuestions(Request $request, LessonExercise $exercise): void
    {
        if (! $request->boolean('exercise_builder_touched')) {
            return;
        }

        if ($exercise->attempts()->exists()) {
            session()->flash('info', 'This exercise already has student submissions, so the note and exercise settings were saved but the questions were left unchanged.');

            return;
        }

        $validated = $request->validate([
            'exercise_questions' => 'nullable|array|max:100',
            'exercise_questions.*.question_type' => ['required', Rule::in([ExerciseQuestion::TYPE_OBJECTIVE, ExerciseQuestion::TYPE_TRUE_FALSE, ExerciseQuestion::TYPE_THEORY])],
            'exercise_questions.*.question_text' => 'required|string',
            'exercise_questions.*.option_a' => 'nullable|required_if:exercise_questions.*.question_type,objective|string',
            'exercise_questions.*.option_b' => 'nullable|required_if:exercise_questions.*.question_type,objective|string',
            'exercise_questions.*.option_c' => 'nullable|required_if:exercise_questions.*.question_type,objective|string',
            'exercise_questions.*.option_d' => 'nullable|required_if:exercise_questions.*.question_type,objective|string',
            'exercise_questions.*.correct_answer' => 'nullable|required_unless:exercise_questions.*.question_type,theory|string',
            'exercise_questions.*.marking_guide' => 'nullable|string',
            'exercise_questions.*.marks' => 'required|numeric|min:0.5|max:100',
            'exercise_questions.*.existing_image_path' => 'nullable|string|max:255',
            'exercise_questions.*.question_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $preservedImagePaths = collect($validated['exercise_questions'] ?? [])
            ->pluck('existing_image_path')
            ->filter(fn ($path) => is_string($path) && str_starts_with($path, 'exercise-question-images/'))
            ->values()
            ->all();

        foreach ($exercise->questions as $question) {
            if ($question->image_path && ! in_array($question->image_path, $preservedImagePaths, true)) {
                Storage::disk('public')->delete($question->image_path);
            }
        }

        $exercise->questions()->delete();

        foreach (($validated['exercise_questions'] ?? []) as $index => $question) {
            $type = $question['question_type'];
            $options = $type === ExerciseQuestion::TYPE_OBJECTIVE ? $this->sanitizeOptions([
                'A' => $question['option_a'] ?? '',
                'B' => $question['option_b'] ?? '',
                'C' => $question['option_c'] ?? '',
                'D' => $question['option_d'] ?? '',
            ]) : null;

            $correct = $type === ExerciseQuestion::TYPE_THEORY ? null : strtoupper((string) ($question['correct_answer'] ?? ''));
            if ($type === ExerciseQuestion::TYPE_TRUE_FALSE) {
                $correct = strtolower((string) ($question['correct_answer'] ?? 'true')) === 'true' ? 'true' : 'false';
            }

            $file = $request->file("exercise_questions.{$index}.question_image");
            $existingImagePath = (string) ($question['existing_image_path'] ?? '');
            $imagePath = str_starts_with($existingImagePath, 'exercise-question-images/') ? $existingImagePath : null;

            $exercise->questions()->create([
                'question_type' => $type,
                'question_text' => $this->sanitizeHtml($question['question_text']),
                'options' => $options,
                'correct_answer' => $correct,
                'marking_guide' => $type === ExerciseQuestion::TYPE_THEORY && filled($question['marking_guide'] ?? null) ? $this->sanitizeHtml($question['marking_guide']) : null,
                'marks' => $question['marks'],
                'image_path' => $file ? $file->store('exercise-question-images', 'public') : $imagePath,
                'display_order' => $index + 1,
            ]);
        }
    }

    private function storeAttachments(Request $request, LessonNote $note): void
    {
        foreach ($request->file('attachments', []) as $file) {
            $note->attachments()->create([
                'original_filename' => $file->getClientOriginalName(),
                'stored_path' => $file->store('lesson-note-attachments', 'public'),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }
    }

    private function ensureOwner(LessonNote $note): void
    {
        abort_unless((int) $note->teacher_id === (int) Auth::id(), 403);
    }

    private function ensureAssigned(int $classId, int $subjectId): void
    {
        abort_unless(Subject::whereKey($subjectId)->where('school_class_id', $classId)->exists(), 422);

        $isAssigned = Auth::user()->teachingSubjects()
            ->where('subjects.id', $subjectId)
            ->wherePivot('school_class_id', $classId)
            ->exists();

        abort_unless($isAssigned, 403, 'You can only create lesson notes for assigned class and subject combinations.');
    }

    private function ensureNoDuplicate(int $sessionId, int $week, int $classId, int $subjectId, ?int $ignoreId = null): void
    {
        $exists = LessonNote::where('academic_session_id', $sessionId)
            ->where('week_number', $week)
            ->where('school_class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('teacher_id', Auth::id())
            ->where('status', '!=', LessonNote::STATUS_ARCHIVED)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        abort_if($exists, 422, 'A lesson note already exists for this class, subject, week and session.');
    }

    private function activeSession(): AcademicSession
    {
        return AcademicSession::active()->firstOrFail();
    }

    private function assignedClasses()
    {
        return Auth::user()->teachingClasses()->select('school_classes.*')->distinct()->orderBy('level')->orderBy('stream')->get();
    }

    private function assignedSubjects()
    {
        return Auth::user()->teachingSubjects()->select('subjects.*')->distinct()->orderBy('name')->get();
    }

    private function summary(int $teacherId): array
    {
        return [
            'drafts' => LessonNote::where('teacher_id', $teacherId)->where('status', LessonNote::STATUS_DRAFT)->count(),
            'pending' => LessonNote::where('teacher_id', $teacherId)->where('status', LessonNote::STATUS_PENDING)->count(),
            'approved' => LessonNote::where('teacher_id', $teacherId)->where('status', LessonNote::STATUS_APPROVED)->count(),
            'returned' => LessonNote::where('teacher_id', $teacherId)->whereIn('status', [LessonNote::STATUS_RETURNED, LessonNote::STATUS_REJECTED])->count(),
            'awaiting_marking' => ExerciseAttempt::whereHas('exercise.lessonNote', fn ($query) => $query->where('teacher_id', $teacherId))
                ->where('status', ExerciseAttempt::STATUS_AWAITING_MARKING)
                ->count(),
        ];
    }

    private function sanitizeHtml(string $html): string
    {
        $allowedTags = '<h1><h2><h3><h4><h5><h6><p><br><strong><b><em><i><u><s><ol><ul><li><blockquote><code><pre><sub><sup><span><a><img><table><thead><tbody><tr><th><td><hr>';
        $cleanHtml = strip_tags($html, $allowedTags);

        if (! class_exists(\DOMDocument::class)) {
            return $cleanHtml;
        }

        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<div>' . $cleanHtml . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        foreach ($document->getElementsByTagName('*') as $node) {
            $allowedAttributes = match ($node->nodeName) {
                'img' => ['src', 'alt', 'title', 'width', 'height'],
                'a' => ['href', 'title', 'target', 'rel'],
                'td', 'th' => ['colspan', 'rowspan'],
                default => [],
            };

            $attributes = [];
            if ($node->attributes) {
                foreach ($node->attributes as $attribute) {
                    $attributes[] = $attribute->name;
                }
            }

            foreach ($attributes as $attributeName) {
                if (! in_array($attributeName, $allowedAttributes, true)) {
                    $node->removeAttribute($attributeName);
                }
            }

            if ($node->nodeName === 'img') {
                $src = (string) $node->getAttribute('src');
                $path = parse_url($src, PHP_URL_PATH) ?: '';

                if (! str_starts_with($path, '/storage/lesson-note-inline-images/')) {
                    $node->parentNode?->removeChild($node);
                    continue;
                }
            }

            if ($node->nodeName === 'a') {
                $href = (string) $node->getAttribute('href');
                $scheme = parse_url($href, PHP_URL_SCHEME);

                if ($href === '' || ($scheme && ! in_array(strtolower($scheme), ['http', 'https', 'mailto'], true))) {
                    $node->removeAttribute('href');
                }

                if ($node->getAttribute('target') === '_blank') {
                    $node->setAttribute('rel', 'noopener noreferrer');
                }
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

    private function sanitizeOptions(?array $options): ?array
    {
        if (! $options) {
            return null;
        }

        return collect($options)
            ->map(fn ($option) => $this->sanitizeHtml((string) $option))
            ->all();
    }

    private function normalizeExerciseAnswer(array $question): ?string
    {
        $type = $question['question_type'] ?? ExerciseQuestion::TYPE_OBJECTIVE;
        if ($type === ExerciseQuestion::TYPE_THEORY) {
            return null;
        }

        if ($type === ExerciseQuestion::TYPE_TRUE_FALSE) {
            return strtolower((string) ($question['correct_answer'] ?? 'true')) === 'true' ? 'true' : 'false';
        }

        $answer = strtoupper((string) ($question['correct_answer'] ?? 'A'));

        return in_array($answer, ['A', 'B', 'C', 'D'], true) ? $answer : 'A';
    }

    private function applySearch($query, string $search): void
    {
        $search = strtolower(trim($search));
        $query->where(fn ($query) => $query
            ->whereRaw('LOWER(title) like ?', ["%{$search}%"])
            ->orWhereRaw('LOWER(topic) like ?', ["%{$search}%"]));
    }
}
