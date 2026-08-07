<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ExerciseQuestion;
use App\Models\LessonExercise;
use App\Models\LessonNote;
use App\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ExerciseQuestionController extends Controller
{
    public function __construct(private AIService $aiService)
    {
    }

    public function store(Request $request, LessonNote $lessonNote)
    {
        $this->ensureEditable($lessonNote);
        $exercise = $lessonNote->exercise()->firstOrFail();
        $validated = $this->validatedQuestion($request);

        $exercise->questions()->create($this->payload($request, $validated, $exercise));

        return back()->with('success', 'Exercise question added.');
    }

    public function update(Request $request, LessonNote $lessonNote, ExerciseQuestion $question)
    {
        $this->ensureEditable($lessonNote);
        abort_unless((int) $question->lesson_exercise_id === (int) $lessonNote->exercise?->id, 403);
        $validated = $this->validatedQuestion($request);
        $payload = $this->payload($request, $validated, $lessonNote->exercise);

        if ($request->hasFile('question_image') && $question->image_path) {
            Storage::disk('public')->delete($question->image_path);
        } elseif (! $request->hasFile('question_image')) {
            unset($payload['image_path']);
        }

        $question->update($payload);

        return back()->with('success', 'Exercise question updated.');
    }

    public function destroy(LessonNote $lessonNote, ExerciseQuestion $question)
    {
        $this->ensureEditable($lessonNote);
        abort_unless((int) $question->lesson_exercise_id === (int) $lessonNote->exercise?->id, 403);

        if ($question->image_path) {
            Storage::disk('public')->delete($question->image_path);
        }

        $question->delete();

        return back()->with('success', 'Exercise question removed.');
    }

    public function generateWithAi(Request $request, LessonNote $lessonNote)
    {
        $this->ensureEditable($lessonNote);
        $exercise = $lessonNote->exercise()->firstOrFail();

        $validated = $request->validate([
            'topic' => 'required|string|max:255',
            'number_of_questions' => 'required|integer|min:1|max:15',
            'difficulty' => 'required|in:easy,medium,hard',
            'marks_per_question' => 'required|numeric|min:0.5|max:100',
        ]);

        try {
            $questions = $this->aiService->generateLessonExerciseQuestions(
                $validated['topic'],
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
                'message' => 'AI did not return usable questions.',
            ], 422);
        }

        $currentOrder = (int) $exercise->questions()->max('display_order');
        $created = [];

        foreach ($questions as $question) {
            $created[] = $exercise->questions()->create([
                'question_type' => $question['question_type'],
                'question_text' => $this->sanitizeHtml($question['question_text']),
                'options' => $this->sanitizeOptions($question['options']),
                'correct_answer' => $this->normalizeGeneratedAnswer($question),
                'marking_guide' => $question['marking_guide'] ? $this->sanitizeHtml($question['marking_guide']) : null,
                'marks' => $question['marks'],
                'display_order' => ++$currentOrder,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => count($created) . ' AI question(s) added.',
            'questions' => $created,
        ]);
    }

    private function validatedQuestion(Request $request): array
    {
        return $request->validate([
            'question_type' => ['required', Rule::in([ExerciseQuestion::TYPE_OBJECTIVE, ExerciseQuestion::TYPE_TRUE_FALSE, ExerciseQuestion::TYPE_THEORY])],
            'question_text' => 'required|string',
            'option_a' => 'nullable|required_if:question_type,objective|string',
            'option_b' => 'nullable|required_if:question_type,objective|string',
            'option_c' => 'nullable|required_if:question_type,objective|string',
            'option_d' => 'nullable|required_if:question_type,objective|string',
            'correct_answer' => 'nullable|required_unless:question_type,theory|string',
            'marking_guide' => 'nullable|string',
            'marks' => 'required|numeric|min:0.5|max:100',
            'display_order' => 'nullable|integer|min:1',
            'question_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);
    }

    private function payload(Request $request, array $validated, LessonExercise $exercise): array
    {
        $type = $validated['question_type'];
        $options = $type === ExerciseQuestion::TYPE_OBJECTIVE ? [
            'A' => $validated['option_a'],
            'B' => $validated['option_b'],
            'C' => $validated['option_c'],
            'D' => $validated['option_d'],
        ] : null;

        $correct = $type === ExerciseQuestion::TYPE_THEORY
            ? null
            : strtoupper((string) $validated['correct_answer']);

        if ($type === ExerciseQuestion::TYPE_TRUE_FALSE) {
            $correct = strtolower((string) $validated['correct_answer']) === 'true' ? 'true' : 'false';
        }

        return [
            'question_type' => $type,
            'question_text' => $this->sanitizeHtml($validated['question_text']),
            'options' => $this->sanitizeOptions($options),
            'correct_answer' => $correct,
            'marking_guide' => $type === ExerciseQuestion::TYPE_THEORY && filled($validated['marking_guide'] ?? null) ? $this->sanitizeHtml($validated['marking_guide']) : null,
            'marks' => $validated['marks'],
            'display_order' => $validated['display_order'] ?? (((int) $exercise->questions()->max('display_order')) + 1),
            'image_path' => $request->hasFile('question_image') ? $request->file('question_image')->store('exercise-question-images', 'public') : null,
        ];
    }

    private function ensureEditable(LessonNote $lessonNote): void
    {
        abort_unless((int) $lessonNote->teacher_id === (int) Auth::id(), 403);
        abort_unless($lessonNote->isEditable(), 403);
    }

    private function normalizeGeneratedAnswer(array $question): ?string
    {
        if ($question['question_type'] === ExerciseQuestion::TYPE_THEORY) {
            return null;
        }

        if ($question['question_type'] === ExerciseQuestion::TYPE_TRUE_FALSE) {
            return strtolower((string) $question['correct_answer']) === 'true' ? 'true' : 'false';
        }

        $answer = strtoupper((string) $question['correct_answer']);

        return in_array($answer, ['A', 'B', 'C', 'D'], true) ? $answer : 'A';
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

    private function sanitizeHtml(string $html): string
    {
        $allowedTags = '<h1><h2><h3><h4><h5><h6><p><br><strong><b><em><i><u><s><ol><ul><li><blockquote><code><pre><sub><sup><span><a><img><table><thead><tbody><tr><th><td><hr>';
        $cleanHtml = strip_tags($html, $allowedTags);

        if (! class_exists(\DOMDocument::class)) {
            return trim($cleanHtml);
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
}
