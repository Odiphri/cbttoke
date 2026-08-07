<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;

class AIService
{
    protected $apiKey;
    protected $apiUrl;
    protected $model;
    protected $fallbackModels;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.model', 'gemini-2.5-flash');
        $this->fallbackModels = config('services.gemini.fallback_models', []);
        $this->apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';
    }

    public function generateQuestions($topic, $numberOfQuestions = 5, $difficulty = 'medium', int $pointsPerQuestion = 1, ?int $overallPoints = null)
    {
        if (blank($this->apiKey)) {
            throw new \Exception('Gemini API key is not configured.');
        }

        $prompt = $this->buildPrompt($topic, $numberOfQuestions, $difficulty, $pointsPerQuestion, $overallPoints);

        $response = null;
        $lastError = null;

        foreach ($this->candidateModels() as $model) {
            $payload = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => $this->maxOutputTokens((int) $numberOfQuestions),
                    'responseMimeType' => 'application/json',
                    'responseSchema' => $this->questionResponseSchema(),
                ],
                'systemInstruction' => [
                    'parts' => [
                        [
                            'text' => 'You are an educational content creator specializing in multiple-choice questions for high school students. Always create questions with 4 options (A, B, C, D) where only one option is correct.',
                        ],
                    ],
                ],
            ];

            Log::info('Gemini AI request', [
                'model' => $model,
                'url' => sprintf($this->apiUrl, $model),
                'request' => ['contents' => $payload['contents'], 'generationConfig' => $payload['generationConfig']],
                'api_key_present' => ! blank($this->apiKey),
            ]);

            $response = Http::timeout(55)
                ->retry(2, 500, function ($exception) {
                    if ($exception instanceof RequestException) {
                        return in_array($exception->response->status(), [429, 500, 502, 503, 504], true);
                    }

                    return true;
                }, false)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post(sprintf($this->apiUrl, $model) . '?key=' . $this->apiKey, $payload);

            Log::info('Gemini AI response', [
                'model' => $model,
                'status' => $response->status(),
                'successful' => $response->successful(),
                'body' => $response->successful() ? null : $response->body(),
            ]);

            if ($response->successful()) {
                break;
            }

            $lastError = $response->body();

            if (! in_array($response->status(), [404, 429, 500, 502, 503, 504], true)) {
                break;
            }
        }

        if (!$response?->successful()) {
            Log::error('Gemini API Error: ' . $lastError);
            throw new \Exception('Failed to generate questions using Gemini AI');
        }

        $data = $response->json();
        $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return $this->parseQuestions($content, $pointsPerQuestion);
    }

    public function generateLessonNoteDraft(array $context): array
    {
        if (blank($this->apiKey)) {
            throw new \Exception('Gemini API key is not configured.');
        }

        $targetWords = $this->targetWordsFor($context);
        $sourceText = mb_substr((string) ($context['source_text'] ?? ''), 0, 120000);
        $prompt = "Create a polished Nigerian secondary-school lesson note from this context.

Class: {$context['class']}
Subject: {$context['subject']}
Week: {$context['week']}
Topic: {$context['topic']}
Subtopic: " . ($context['subtopic'] ?? 'N/A') . "
Requested length: about {$targetWords} words. If the requested length is very large, write the longest complete, useful lesson note the model can fit in one response and make each section detailed.

Source material:
" . $sourceText . "

Return a complete, practical lesson note with substantial detail. The main_content must be rich HTML using headings, paragraphs, lists, tables where useful, worked examples, teacher activities, learner activities, guided practice, common mistakes, assessment prompts, and a summary. Do not include unsafe scripts or external images.";

        return $this->generateJson($prompt, $this->lessonNoteResponseSchema(), [
            'You are an experienced lesson-note writer for TOKE Schools. Write clear, classroom-ready notes for secondary-school learners.',
        ], 0.55, $this->lessonNoteMaxOutputTokens($targetWords));
    }

    public function generateLessonExerciseQuestions(string $topic, int $numberOfQuestions = 5, string $difficulty = 'medium', float $marksPerQuestion = 1): array
    {
        if (blank($this->apiKey)) {
            throw new \Exception('Gemini API key is not configured.');
        }

        $prompt = "Generate {$numberOfQuestions} lesson exercise questions about '{$topic}'.

Difficulty: {$difficulty}
Marks per question: {$marksPerQuestion}

Mix objective, true_false, and theory questions when appropriate. Objective questions need options A-D and a correct option. True/false questions need correct_answer true or false. Theory questions need a private marking guide.

Return only valid JSON.";

        $questions = $this->generateJson($prompt, $this->lessonExerciseResponseSchema(), [
            'You create classroom exercise questions for lesson notes. Keep questions age-appropriate, clear, and directly tied to the topic.',
        ], 0.65, 8192);

        return array_values(array_filter(array_map(function (array $question) use ($marksPerQuestion) {
            $type = $question['question_type'] ?? 'objective';
            if (! in_array($type, ['objective', 'true_false', 'theory'], true)) {
                $type = 'objective';
            }

            return [
                'question_type' => $type,
                'question_text' => $question['question_text'] ?? '',
                'options' => $type === 'objective' ? [
                    'A' => $question['option_a'] ?? '',
                    'B' => $question['option_b'] ?? '',
                    'C' => $question['option_c'] ?? '',
                    'D' => $question['option_d'] ?? '',
                ] : null,
                'correct_answer' => $type === 'theory' ? null : (string) ($question['correct_answer'] ?? ''),
                'marking_guide' => $type === 'theory' ? ($question['marking_guide'] ?? null) : null,
                'marks' => (float) ($question['marks'] ?? $marksPerQuestion),
            ];
        }, $questions), fn ($question) => filled($question['question_text'])));
    }

    public function continueLessonNote(array $context): array
    {
        if (blank($this->apiKey)) {
            throw new \Exception('Gemini API key is not configured.');
        }

        $targetWords = min(12000, max(1000, (int) ($context['target_words'] ?? 4000)));
        $existingContent = mb_substr((string) ($context['existing_content'] ?? ''), -90000);

        $prompt = "Continue and expand this existing Nigerian secondary-school lesson note.

Class: {$context['class']}
Subject: {$context['subject']}
Topic: {$context['topic']}
Subtopic: " . ($context['subtopic'] ?? 'N/A') . "
Requested continuation length: about {$targetWords} words.

Existing note content:
{$existingContent}

Write only the new continuation content, not the full note again. Continue naturally from where the current note stops. Add deeper explanations, more examples, learner activities, teacher prompts, common mistakes, class exercises, evaluation questions, and assignment ideas where useful. Use rich HTML. Do not include unsafe scripts or external images.";

        return $this->generateJson($prompt, [
            'type' => 'OBJECT',
            'properties' => [
                'main_content' => ['type' => 'STRING'],
            ],
            'required' => ['main_content'],
        ], [
            'You extend lesson notes with coherent, classroom-ready continuation content. Return only new content that can be appended to the existing note.',
        ], 0.6, $this->lessonNoteMaxOutputTokens($targetWords));
    }

    private function candidateModels(): array
    {
        return array_values(array_unique(array_filter(array_merge([$this->model], $this->fallbackModels))));
    }

    private function generateJson(string $prompt, array $schema, array $systemInstructions, float $temperature, int $maxOutputTokens): array
    {
        $response = null;
        $lastError = null;

        foreach ($this->candidateModels() as $model) {
            $payload = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => $temperature,
                    'maxOutputTokens' => $maxOutputTokens,
                    'responseMimeType' => 'application/json',
                    'responseSchema' => $schema,
                ],
                'systemInstruction' => [
                    'parts' => array_map(fn (string $text) => ['text' => $text], $systemInstructions),
                ],
            ];

            $response = Http::timeout(70)
                ->retry(2, 500, function ($exception) {
                    if ($exception instanceof RequestException) {
                        return in_array($exception->response->status(), [429, 500, 502, 503, 504], true);
                    }

                    return true;
                }, false)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post(sprintf($this->apiUrl, $model) . '?key=' . $this->apiKey, $payload);

            if ($response->successful()) {
                break;
            }

            $lastError = $response->body();
            Log::warning('Gemini structured AI response failed', [
                'model' => $model,
                'status' => $response->status(),
                'body' => $lastError,
            ]);
        }

        if (! $response?->successful()) {
            Log::error('Gemini API Error: ' . $lastError);
            throw new \Exception('Failed to generate content using Gemini AI');
        }

        $content = $response->json('candidates.0.content.parts.0.text', '');
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            $jsonStart = strpos($content, '{');
            $jsonEnd = strrpos($content, '}');

            if ($jsonStart !== false && $jsonEnd !== false) {
                $decoded = json_decode(substr($content, $jsonStart, $jsonEnd - $jsonStart + 1), true);
            }
        }

        if (! is_array($decoded)) {
            $jsonStart = strpos($content, '[');
            $jsonEnd = strrpos($content, ']');

            if ($jsonStart !== false && $jsonEnd !== false) {
                $decoded = json_decode(substr($content, $jsonStart, $jsonEnd - $jsonStart + 1), true);
            }
        }

        if (! is_array($decoded)) {
            Log::warning('Gemini structured AI returned invalid JSON', [
                'content' => mb_substr($content, 0, 1000),
            ]);

            throw new \Exception('AI returned an invalid response.');
        }

        return $decoded;
    }

    private function lessonNoteResponseSchema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'title' => ['type' => 'STRING'],
                'topic' => ['type' => 'STRING'],
                'subtopic' => ['type' => 'STRING'],
                'previous_knowledge' => ['type' => 'STRING'],
                'learning_objectives' => ['type' => 'STRING'],
                'teaching_materials' => ['type' => 'STRING'],
                'introduction' => ['type' => 'STRING'],
                'main_content' => ['type' => 'STRING'],
                'evaluation' => ['type' => 'STRING'],
                'conclusion' => ['type' => 'STRING'],
                'assignment' => ['type' => 'STRING'],
            ],
            'required' => ['title', 'topic', 'previous_knowledge', 'learning_objectives', 'teaching_materials', 'introduction', 'main_content', 'evaluation', 'conclusion', 'assignment'],
        ];
    }

    private function lessonExerciseResponseSchema(): array
    {
        return [
            'type' => 'ARRAY',
            'items' => [
                'type' => 'OBJECT',
                'properties' => [
                    'question_type' => ['type' => 'STRING', 'enum' => ['objective', 'true_false', 'theory']],
                    'question_text' => ['type' => 'STRING'],
                    'option_a' => ['type' => 'STRING'],
                    'option_b' => ['type' => 'STRING'],
                    'option_c' => ['type' => 'STRING'],
                    'option_d' => ['type' => 'STRING'],
                    'correct_answer' => ['type' => 'STRING'],
                    'marking_guide' => ['type' => 'STRING'],
                    'marks' => ['type' => 'NUMBER'],
                ],
                'required' => ['question_type', 'question_text', 'marks'],
            ],
        ];
    }

    private function maxOutputTokens(int $numberOfQuestions): int
    {
        return min(8192, max(2500, 900 + ($numberOfQuestions * 450)));
    }

    private function targetWordsFor(array $context): int
    {
        return min(1000000, max(1000, (int) ($context['target_words'] ?? 5000)));
    }

    private function lessonNoteMaxOutputTokens(int $targetWords): int
    {
        return min(65536, max(8192, (int) ceil($targetWords * 1.8)));
    }

    private function questionResponseSchema(): array
    {
        return [
            'type' => 'ARRAY',
            'items' => [
                'type' => 'OBJECT',
                'properties' => [
                    'question' => ['type' => 'STRING'],
                    'option_a' => ['type' => 'STRING'],
                    'option_b' => ['type' => 'STRING'],
                    'option_c' => ['type' => 'STRING'],
                    'option_d' => ['type' => 'STRING'],
                    'correct_answer' => [
                        'type' => 'STRING',
                        'enum' => ['A', 'B', 'C', 'D'],
                    ],
                ],
                'required' => ['question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer'],
            ],
        ];
    }

    private function buildPrompt($topic, $numberOfQuestions, $difficulty, int $pointsPerQuestion, ?int $overallPoints)
    {
        $overallPointInstruction = $overallPoints
            ? "The generated set is planned to be scored over {$overallPoints} total point(s). Each question must carry {$pointsPerQuestion} point(s), so the generated questions should total " . ($numberOfQuestions * $pointsPerQuestion) . " point(s)."
            : "Each question must carry {$pointsPerQuestion} point(s).";

        return "Generate {$numberOfQuestions} multiple-choice questions about '{$topic}' for high school students.

Requirements:
1. Difficulty level: {$difficulty}
2. Each question must have exactly 4 options (A, B, C, D)
3. Only one option should be correct
4. Include the correct answer for each question
5. {$overallPointInstruction}
6. Return only valid JSON. Do not include markdown, prose, or code fences.
7. Format as JSON array with this structure:
[
  {
    \"question\": \"Question text here\",
    \"option_a\": \"Option A\",
    \"option_b\": \"Option B\",
    \"option_c\": \"Option C\",
    \"option_d\": \"Option D\",
    \"correct_answer\": \"A\"
  }
]

Please generate exactly {$numberOfQuestions} questions following this format.";
    }

    private function parseQuestions($content, int $pointsPerQuestion = 1)
    {
        try {
            $questions = json_decode($content, true);

            if (! is_array($questions)) {
                // Extract JSON from responses that still include surrounding text or fences.
                $jsonStart = strpos($content, '[');
                $jsonEnd = strrpos($content, ']');

                if ($jsonStart !== false && $jsonEnd !== false) {
                    $jsonString = substr($content, $jsonStart, $jsonEnd - $jsonStart + 1);
                    $questions = json_decode($jsonString, true);
                }
            }

            if (is_array($questions)) {
                return array_values(array_filter(array_map(function ($question) use ($pointsPerQuestion) {
                    $correctAnswer = strtoupper($question['correct_answer'] ?? 'A');

                    if (! in_array($correctAnswer, ['A', 'B', 'C', 'D'], true)) {
                        $correctAnswer = 'A';
                    }

                    return [
                        'question_text' => $question['question'] ?? $question['question_text'] ?? '',
                        'option_a' => $question['option_a'] ?? '',
                        'option_b' => $question['option_b'] ?? '',
                        'option_c' => $question['option_c'] ?? '',
                        'option_d' => $question['option_d'] ?? '',
                        'correct_answer' => $correctAnswer,
                        'points' => $pointsPerQuestion,
                    ];
                }, $questions), fn ($question) => filled($question['question_text'])));
            }
        } catch (\Exception $e) {
            Log::error('Error parsing Gemini response: ' . $e->getMessage());
        }

        return [];
    }
}
