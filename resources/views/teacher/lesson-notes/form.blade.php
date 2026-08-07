@extends('layouts.admin')

@section('title', $note->exists ? 'Edit Lesson Note' : 'Create Lesson Note')

@section('content')
<style>
.lesson-builder-shell {
    border: 1px solid #dce4ee;
    border-radius: 8px;
    background: #fff;
    overflow: hidden;
}

.lesson-editor-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 10px;
    border-bottom: 1px solid #dce4ee;
    background: #f8fafc;
}

.lesson-editor-toolbar button,
.lesson-editor-toolbar select {
    min-height: 34px;
}

.lesson-rich-editor {
    min-height: 360px;
    padding: 18px;
    outline: none;
    background: #fff;
}

.lesson-rich-editor:focus {
    box-shadow: inset 0 0 0 2px rgba(10, 25, 49, .12);
}

.exercise-builder-panel {
    border: 1px solid #dce4ee;
    border-radius: 8px;
    background: #fbfcfe;
    padding: 16px;
}

.builder-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.builder-panel,
.questions-board {
    background: #fff;
    border: 1px solid #dde4ed;
    border-radius: 8px;
    padding: 18px;
    box-shadow: 0 1px 3px rgba(10, 25, 49, .08);
}

.btn-ai {
    border: 1px solid #e2bdec;
    background: #fff;
    color: #8a1aa1;
}

.empty-questions {
    min-height: 140px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-align: center;
    color: #667381;
}

.empty-questions p {
    flex: 0 0 100%;
    margin-bottom: 0;
}

.question-item {
    border: 1px solid #e4eaf1;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 14px;
    background: #fbfcfe;
}

.question-title {
    font-weight: 700;
    margin-bottom: 6px;
}

.question-image {
    display: block;
    max-width: min(520px, 100%);
    max-height: 260px;
    object-fit: contain;
    border: 1px solid #e4eaf1;
    border-radius: 8px;
    background: #fff;
    margin: 8px 0;
}

.option-box {
    border: 1px solid #e4eaf1;
    border-radius: 6px;
    padding: 10px 12px;
    background: #fff;
}

.option-box.is-correct {
    border-color: #24a148;
    color: #137333;
    background: #eefaf2;
    font-weight: 700;
}
</style>

<form method="POST" enctype="multipart/form-data" action="{{ $note->exists ? route('teacher.lesson-notes.update', $note) : route('teacher.lesson-notes.store') }}">
    @csrf
    @if($note->exists) @method('PUT') @endif
    <div class="card mb-3">
        <div class="card-header">Classification</div>
        <div class="card-body row g-3">
            <div class="col-md-4"><label class="form-label">Academic Session</label><input class="form-control" value="{{ $activeSession?->display_name }}" disabled></div>
            <div class="col-md-2"><label class="form-label">Week</label><select class="form-select" name="week_number">@for($i=1;$i<=15;$i++)<option value="{{ $i }}" @selected(old('week_number', $note->week_number) == $i)>Week {{ $i }}</option>@endfor</select></div>
            <div class="col-md-3"><label class="form-label">Class</label><select class="form-select" name="school_class_id">@foreach($classes as $class)<option value="{{ $class->id }}" @selected(old('school_class_id', $note->school_class_id) == $class->id)>{{ $class->full_name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Subject</label><select class="form-select" name="subject_id">@foreach($subjects as $subject)<option value="{{ $subject->id }}" @selected(old('subject_id', $note->subject_id) == $subject->id)>{{ $subject->name }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">Lesson Title</label><input class="form-control" name="title" value="{{ old('title', $note->title) }}" required></div>
            <div class="col-md-6"><label class="form-label">Topic</label><input class="form-control" name="topic" value="{{ old('topic', $note->topic) }}" required></div>
            <div class="col-md-6"><label class="form-label">Subtopic</label><input class="form-control" name="subtopic" value="{{ old('subtopic', $note->subtopic) }}"></div>
            <div class="col-md-6"><label class="form-label">Lesson Date</label><input class="form-control" type="date" name="lesson_date" value="{{ old('lesson_date', optional($note->lesson_date)->format('Y-m-d')) }}"></div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Lesson Content</div>
        <div class="card-body row g-3">
            <div class="col-12">
                <div class="alert alert-info d-flex flex-column gap-3">
                    <div>
                        <strong><i class="fas fa-magic me-1"></i> AI Lesson Builder</strong>
                        <div class="small">Build a complete classroom note from a topic, or upload a PDF and let AI reshape it into a real note.</div>
                    </div>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-7">
                            <label class="form-label">Topic to build from</label>
                            <input class="form-control" id="aiTopic" placeholder="Type the topic you want AI to build, e.g. Photosynthesis" value="{{ old('topic', $note->topic) }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Words</label>
                            <input class="form-control" id="aiTargetWords" type="number" min="1000" max="1000000" step="500" value="5000">
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-primary-custom w-100" id="generateLessonAiBtn">
                                <i class="fas fa-wand-magic-sparkles me-1"></i> Generate from Topic
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-outline-primary w-100" id="continueLessonAiBtn">
                                <i class="fas fa-forward me-1"></i> Continue Current Note
                            </button>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <strong><i class="fas fa-file-pdf me-1"></i> PDF Builder</strong>
                            <div class="small">Use a readable PDF as source material for the lesson note.</div>
                        </div>
                    </div>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-8">
                            <label class="form-label">Upload PDF source</label>
                            <input class="form-control" type="file" id="aiPdfFile" accept=".pdf">
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-outline-primary w-100" id="generatePdfAiBtn">
                                <i class="fas fa-file-pdf me-1"></i> Build Note from PDF
                            </button>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Extra instruction for AI</label>
                            <textarea class="form-control" id="aiSourceText" rows="2" placeholder="Optional: tell AI what to emphasize, e.g. add more examples, use JSS1 vocabulary, include a table..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
            @foreach(['previous_knowledge'=>'Previous Knowledge','learning_objectives'=>'Learning Objectives','teaching_materials'=>'Teaching Materials','introduction'=>'Introduction','evaluation'=>'Evaluation','conclusion'=>'Conclusion','assignment'=>'Assignment / Homework'] as $field => $label)
            <div class="col-md-6"><label class="form-label">{{ $label }}</label><textarea class="form-control lesson-rich-text lesson-rich-text-small" id="{{ $field }}" name="{{ $field }}" rows="5">{{ old($field, $note->$field) }}</textarea></div>
            @endforeach
            <div class="col-12">
                <label class="form-label">Main Lesson Content</label>
                <textarea class="form-control lesson-rich-text lesson-rich-text-main" id="main_content" name="main_content" rows="16">{{ old('main_content', $note->main_content) }}</textarea>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Attachments</div>
        <div class="card-body">
            <input class="form-control" type="file" name="attachments[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp">
            @if($note->exists && $note->attachments->isNotEmpty())
            <div class="mt-3 d-flex flex-wrap gap-2">@foreach($note->attachments as $attachment)<span class="badge bg-light text-dark">{{ $attachment->original_filename }}</span>@endforeach</div>
            @endif
        </div>
    </div>

    @php($exerciseHasSubmissions = $exercise->exists && $exercise->attempts()->exists())
    <input type="hidden" name="has_exercise" id="hasExerciseInput" value="{{ old('has_exercise', $exercise->exists ? 1 : 0) }}">
    <input type="hidden" name="exercise_builder_touched" id="exerciseBuilderTouched" value="0">

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>Exercise</span>
            <button class="btn btn-primary-custom" type="button" id="toggleExerciseBuilderBtn" data-bs-toggle="collapse" data-bs-target="#exerciseBuilderPanel">
                <i class="fas fa-plus me-1"></i> Add Exercise
            </button>
        </div>
        <div class="collapse {{ old('has_exercise', $exercise->exists ? 1 : 0) ? 'show' : '' }}" id="exerciseBuilderPanel">
            <div class="card-body">
                @if($exerciseHasSubmissions)
                    <div class="alert alert-info">
                        This exercise already has student submissions. You can edit the note and exercise settings, but the saved questions will stay unchanged.
                    </div>
                @endif
                <div class="builder-toolbar mb-3">
                    <button class="btn btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#exerciseSettingsPanel">
                        <i class="fas fa-cog me-2"></i>Settings
                    </button>
                    <button class="btn btn-ai" type="button" data-bs-toggle="collapse" data-bs-target="#exerciseAiPanel">
                        <i class="fas fa-magic me-2"></i>Generate Exercise From Note
                    </button>
                    <button class="btn btn-primary-custom" type="button" data-bs-toggle="collapse" data-bs-target="#exerciseManualPanel">
                        <i class="fas fa-plus me-2"></i>Add Question
                    </button>
                </div>

                <div class="collapse show mb-3" id="exerciseSettingsPanel">
                    <div class="builder-panel">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Exercise Title</label><input class="form-control" name="exercise_title" value="{{ old('exercise_title', $exercise->title ?: ($note->topic ? $note->topic.' Exercise' : 'Lesson Exercise')) }}"></div>
                            <div class="col-md-6"><label class="form-label">Attempt Mode</label><select class="form-select" name="attempt_mode"><option value="one" @selected(old('attempt_mode', $exercise->attempt_mode)==='one')>One attempt</option><option value="limited" @selected(old('attempt_mode', $exercise->attempt_mode)==='limited')>Limited attempts</option><option value="unlimited" @selected(old('attempt_mode', $exercise->attempt_mode)==='unlimited')>Unlimited attempts</option></select></div>
                            <div class="col-md-3"><label class="form-label">Maximum Attempts</label><input class="form-control" type="number" name="max_attempts" min="1" value="{{ old('max_attempts', $exercise->max_attempts) }}"></div>
                            <div class="col-md-3"><label class="form-label">Score Method</label><select class="form-select" name="score_selection_method"><option value="highest" @selected(old('score_selection_method', $exercise->score_selection_method)==='highest')>Highest score</option><option value="latest" @selected(old('score_selection_method', $exercise->score_selection_method)==='latest')>Latest score</option><option value="first" @selected(old('score_selection_method', $exercise->score_selection_method)==='first')>First score</option></select></div>
                            <div class="col-md-3"><label class="form-label">Opens</label><input class="form-control" type="datetime-local" name="opens_at" value="{{ old('opens_at', optional($exercise->opens_at)->format('Y-m-d\\TH:i')) }}"></div>
                            <div class="col-md-3"><label class="form-label">Deadline</label><input class="form-control" type="datetime-local" name="due_at" value="{{ old('due_at', optional($exercise->due_at)->format('Y-m-d\\TH:i')) }}"></div>
                            <div class="col-12"><label class="form-label">Instructions</label><textarea class="form-control lesson-rich-text lesson-rich-text-small" id="exercise_instructions" name="exercise_instructions" rows="4">{{ old('exercise_instructions', $exercise->instructions) }}</textarea></div>
                            @foreach(['allow_late_submission'=>'Allow late submission','shuffle_questions'=>'Shuffle Questions','shuffle_options'=>'Shuffle Options','show_score_immediately'=>'Show Score Immediately','reveal_correct_answers'=>'Reveal Correct Answers'] as $field => $label)
                            <div class="col-md form-check form-switch ms-2"><input class="form-check-input" type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $exercise->$field))><label class="form-check-label">{{ $label }}</label></div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="collapse mb-3" id="exerciseAiPanel">
                    <div class="builder-panel">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4"><label class="form-label">Topic</label><input class="form-control" id="exerciseAiTopic" value="{{ old('topic', $note->topic) }}" placeholder="Uses note topic by default"></div>
                            <div class="col-md-2"><label class="form-label">Questions</label><input class="form-control" id="exerciseAiCount" type="number" min="1" max="50" value="5"></div>
                            <div class="col-md-2"><label class="form-label">Marks Each</label><input class="form-control" id="exerciseAiMarks" type="number" step="0.5" min="0.5" max="100" value="1"></div>
                            <div class="col-md-2"><label class="form-label">Overall Marks</label><input class="form-control" id="exerciseAiOverall" type="number" step="0.5" min="0.5" max="5000" value="5"></div>
                            <div class="col-md-2"><label class="form-label">Difficulty</label><select class="form-select" id="exerciseAiDifficulty"><option value="easy">Easy</option><option value="medium" selected>Medium</option><option value="hard">Hard</option></select></div>
                            <div class="col-md-2"><button type="button" class="btn btn-success w-100" id="generateExerciseFromNoteBtn"><i class="fas fa-magic me-2"></i>Generate</button></div>
                        </div>
                    </div>
                </div>

                <div class="collapse mb-3 show" id="exerciseManualPanel">
                    <div class="builder-panel">
                        <div class="row g-3">
                            <div class="col-md-3"><label class="form-label">Question Type</label><select class="form-select" id="draftQuestionType"><option value="objective">Multiple choice</option><option value="true_false">True / false</option><option value="theory">Theory</option></select></div>
                            <div class="col-md-2"><label class="form-label">Marks</label><input class="form-control" id="draftQuestionMarks" type="number" step="0.5" min="0.5" value="1"></div>
                            <div class="col-md-7"><label class="form-label">Image</label><input class="form-control" id="draftQuestionImage" type="file" accept=".jpg,.jpeg,.png,.webp"></div>
                            <div class="col-12"><label class="form-label">Question</label><textarea class="form-control question-rich-text" id="draftQuestionText" rows="5"></textarea></div>
                        </div>
                        <div class="objective-fields row g-3 mt-1" id="draftObjectiveFields">
                            @foreach(['a','b','c','d'] as $option)
                            <div class="col-md-6"><label class="form-label">Option {{ strtoupper($option) }}</label><textarea class="form-control question-rich-text-short" id="draftOption{{ strtoupper($option) }}" rows="2"></textarea></div>
                            @endforeach
                        </div>
                        <div class="answer-fields row g-3 mt-1">
                            <div class="col-md-6" id="draftObjectiveAnswerWrap"><label class="form-label">Correct Answer</label><select class="form-select" id="draftObjectiveAnswer"><option value="">Select answer</option><option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option></select></div>
                            <div class="col-md-6 d-none" id="draftTrueFalseAnswerWrap"><label class="form-label">Correct Answer</label><select class="form-select" id="draftTrueFalseAnswer"><option value="true">True</option><option value="false">False</option></select></div>
                            <div class="col-md-6 d-none" id="draftGuideWrap"><label class="form-label">Private Marking Guide</label><textarea class="form-control question-rich-text-short" id="draftMarkingGuide" rows="2"></textarea></div>
                        </div>
                        <button type="button" class="btn btn-primary-custom mt-3" id="stageExerciseQuestionBtn"><i class="fas fa-plus me-2"></i>Add Question</button>
                    </div>
                </div>

                <div class="questions-board" id="exerciseQuestionsBoard"></div>
                <div id="exerciseHiddenFields"></div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap mb-4">
        <button class="btn btn-outline-secondary" name="action" value="draft"><i class="fas fa-save me-1"></i> Save Draft</button>
        <button class="btn btn-primary-custom" name="action" value="submit"><i class="fas fa-paper-plane me-1"></i> Submit for Approval</button>
    </div>
</form>
@php
    $existingExerciseQuestions = $questions->map(function ($question) {
        return [
            'question_type' => $question->question_type,
            'question_text' => $question->question_text,
            'options' => $question->options,
            'correct_answer' => $question->correct_answer,
            'marking_guide' => $question->marking_guide,
            'marks' => (float) $question->marks,
            'image_path' => $question->image_path,
        ];
    })->values();
@endphp
<script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
<script>
const tinyBaseConfig = {
    license_key: 'gpl',
    menubar: 'file edit insert view format table tools help',
    plugins: 'advlist autolink lists link image media table code fullscreen preview searchreplace visualblocks wordcount',
    toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | image media link table | blockquote hr removeformat | code fullscreen preview',
    branding: false,
    promotion: false,
    convert_urls: false,
    automatic_uploads: true,
    images_file_types: 'jpg,jpeg,png,webp',
    images_upload_handler: (blobInfo, progress) => new Promise((resolve, reject) => {
        const formData = new FormData();
        formData.append('file', blobInfo.blob(), blobInfo.filename());

        fetch('{{ route('teacher.lesson-notes.inline-image') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            body: formData
        })
            .then(async (response) => {
                const data = await response.json();

                if (!response.ok || !data.location) {
                    throw new Error(data.message || 'Image upload failed.');
                }

                resolve(data.location);
            })
            .catch((error) => reject(error.message));
    }),
    file_picker_types: 'image',
    image_title: true,
    image_caption: true,
    table_default_attributes: {},
    table_default_styles: {},
    setup: (editor) => {
        editor.on('change keyup', () => editor.save());
        editor.on('init', () => editor.save());
    }
};

tinymce.init({
    ...tinyBaseConfig,
    selector: '.lesson-rich-text-small',
    height: 260
});

tinymce.init({
    ...tinyBaseConfig,
    selector: '.lesson-rich-text-main',
    height: 560
});

tinymce.init({
    ...tinyBaseConfig,
    selector: '.question-rich-text',
    height: 320
});

tinymce.init({
    ...tinyBaseConfig,
    selector: '.question-rich-text-short',
    height: 180,
    menubar: false
});

document.querySelector('form').addEventListener('submit', () => {
    tinymce.triggerSave();
    renderExerciseHiddenFields();
});

const existingExerciseQuestions = @json($existingExerciseQuestions ?? collect());

let stagedExerciseQuestions = existingExerciseQuestions.map((question) => ({
    question_type: question.question_type || 'objective',
    question_text: question.question_text || '',
    options: question.options || null,
    correct_answer: question.correct_answer || '',
    marking_guide: question.marking_guide || '',
    marks: Number(question.marks || 1),
    image_path: question.image_path || null,
    file_input: null
}));

const lessonAiFields = {
    title: document.querySelector('[name="title"]'),
    topic: document.querySelector('[name="topic"]'),
    subtopic: document.querySelector('[name="subtopic"]'),
    previous_knowledge: document.querySelector('[name="previous_knowledge"]'),
    learning_objectives: document.querySelector('[name="learning_objectives"]'),
    teaching_materials: document.querySelector('[name="teaching_materials"]'),
    introduction: document.querySelector('[name="introduction"]'),
    evaluation: document.querySelector('[name="evaluation"]'),
    conclusion: document.querySelector('[name="conclusion"]'),
    assignment: document.querySelector('[name="assignment"]')
};

function lessonAiPayload() {
    return {
        school_class_id: document.querySelector('[name="school_class_id"]').value,
        subject_id: document.querySelector('[name="subject_id"]').value,
        week_number: document.querySelector('[name="week_number"]').value,
        topic: document.getElementById('aiTopic').value.trim(),
        subtopic: lessonAiFields.subtopic.value,
        source_text: document.getElementById('aiSourceText').value,
        target_words: document.getElementById('aiTargetWords').value || 5000
    };
}

function applyLessonDraft(draft) {
    Object.entries(lessonAiFields).forEach(([key, field]) => {
        if (draft[key] && field) {
            const editor = tinymce.get(field.id || field.name);
            if (editor) {
                editor.setContent(draft[key]);
                editor.save();
            } else {
                field.value = draft[key];
            }
        }
    });

    if (draft.topic) {
        document.getElementById('aiTopic').value = draft.topic;
    }

    if (draft.main_content && tinymce.get('main_content')) {
        tinymce.get('main_content').setContent(draft.main_content);
        tinymce.get('main_content').save();
    }
}

function setAiButtonLoading(button, isLoading, label) {
    button.disabled = isLoading;
    button.innerHTML = isLoading ? '<i class="fas fa-spinner fa-spin me-1"></i> Working...' : label;
}

async function readAiResponse(response) {
    let data = {};
    try {
        data = await response.json();
    } catch (error) {
        data = { message: await response.text() };
    }

    if (!response.ok || !data.success) {
        const validationMessage = data.errors ? Object.values(data.errors).flat().join('\n') : null;
        throw new Error(validationMessage || data.message || 'AI could not complete the request.');
    }

    return data;
}

document.getElementById('generateLessonAiBtn').addEventListener('click', () => {
    const button = document.getElementById('generateLessonAiBtn');
    const payload = lessonAiPayload();

    if (!payload.topic) {
        alert('Enter a topic first.');
        return;
    }

    setAiButtonLoading(button, true, '<i class="fas fa-wand-magic-sparkles me-1"></i> Generate from Topic');

    fetch('{{ route('teacher.lesson-notes.ai-draft') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
    })
        .then(async response => {
            const data = await readAiResponse(response);
            applyLessonDraft(data.draft);
        })
        .catch(error => alert(error.message))
        .finally(() => setAiButtonLoading(button, false, '<i class="fas fa-wand-magic-sparkles me-1"></i> Generate from Topic'));
});

document.getElementById('continueLessonAiBtn').addEventListener('click', () => {
    const button = document.getElementById('continueLessonAiBtn');
    const payload = {
        ...lessonAiPayload(),
        topic: lessonAiFields.topic.value.trim() || document.getElementById('aiTopic').value.trim(),
        existing_content: noteContentForExerciseAi(),
        target_words: document.getElementById('aiTargetWords').value || 4000
    };

    if (!payload.topic) {
        alert('Enter a topic first.');
        return;
    }

    if (!plainTextFromHtml(payload.existing_content)) {
        alert('Write or generate some note content first.');
        return;
    }

    setAiButtonLoading(button, true, '<i class="fas fa-forward me-1"></i> Continue Current Note');

    fetch('{{ route('teacher.lesson-notes.continue-draft') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
    })
        .then(async response => {
            const data = await readAiResponse(response);
            const editor = tinymce.get('main_content');
            if (editor && data.draft?.main_content) {
                editor.setContent(`${editor.getContent()}<hr>${data.draft.main_content}`);
                editor.save();
            }
        })
        .catch(error => alert(error.message))
        .finally(() => setAiButtonLoading(button, false, '<i class="fas fa-forward me-1"></i> Continue Current Note'));
});

document.getElementById('generatePdfAiBtn').addEventListener('click', () => {
    const button = document.getElementById('generatePdfAiBtn');
    const file = document.getElementById('aiPdfFile').files[0];
    const payload = {
        ...lessonAiPayload(),
        topic: lessonAiFields.topic.value.trim() || document.getElementById('aiTopic').value.trim()
    };

    if (!payload.topic) {
        alert('Enter a topic first.');
        return;
    }

    if (!file) {
        alert('Choose a PDF first.');
        return;
    }

    const formData = new FormData();
    Object.entries(payload).forEach(([key, value]) => formData.append(key, value || ''));
    formData.append('pdf_file', file);

    setAiButtonLoading(button, true, '<i class="fas fa-file-pdf me-1"></i> Build Note from PDF');

    fetch('{{ route('teacher.lesson-notes.pdf-draft') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        },
        body: formData
    })
        .then(async response => {
            const data = await readAiResponse(response);
            applyLessonDraft(data.draft);
        })
        .catch(error => alert(error.message))
        .finally(() => setAiButtonLoading(button, false, '<i class="fas fa-file-pdf me-1"></i> Build Note from PDF'));
});

function markExerciseBuilderTouched() {
    document.getElementById('hasExerciseInput').value = '1';
    document.getElementById('exerciseBuilderTouched').value = '1';
}

function editorContent(id) {
    const editor = tinymce.get(id);
    return editor ? editor.getContent() : (document.getElementById(id)?.value || '');
}

function plainTextFromHtml(html) {
    const holder = document.createElement('div');
    holder.innerHTML = html || '';
    return holder.textContent.trim();
}

function syncDraftQuestionType() {
    const type = document.getElementById('draftQuestionType').value;
    document.getElementById('draftObjectiveFields').classList.toggle('d-none', type !== 'objective');
    document.getElementById('draftObjectiveAnswerWrap').classList.toggle('d-none', type !== 'objective');
    document.getElementById('draftTrueFalseAnswerWrap').classList.toggle('d-none', type !== 'true_false');
    document.getElementById('draftGuideWrap').classList.toggle('d-none', type !== 'theory');
}

function resetDraftQuestionForm() {
    ['draftQuestionText', 'draftOptionA', 'draftOptionB', 'draftOptionC', 'draftOptionD', 'draftMarkingGuide'].forEach((id) => {
        const editor = tinymce.get(id);
        if (editor) {
            editor.setContent('');
            editor.save();
        }
    });
    document.getElementById('draftObjectiveAnswer').value = '';
    document.getElementById('draftTrueFalseAnswer').value = 'true';
    document.getElementById('draftQuestionMarks').value = '1';
    document.getElementById('draftQuestionImage')?.remove();
    const freshFile = document.createElement('input');
    freshFile.className = 'form-control';
    freshFile.id = 'draftQuestionImage';
    freshFile.type = 'file';
    freshFile.accept = '.jpg,.jpeg,.png,.webp';
    document.querySelector('label[for="draftQuestionImage"]')?.parentElement?.appendChild(freshFile);
}

function renderExerciseBoard() {
    const board = document.getElementById('exerciseQuestionsBoard');

    if (!stagedExerciseQuestions.length) {
        board.innerHTML = `
            <div class="empty-questions">
                <p>No questions have been added to this exercise yet.</p>
                <button class="btn btn-primary-custom" type="button" data-bs-toggle="collapse" data-bs-target="#exerciseManualPanel">Add First Question</button>
                <button class="btn btn-ai" type="button" data-bs-toggle="collapse" data-bs-target="#exerciseAiPanel"><i class="fas fa-magic me-2"></i>AI Generate</button>
            </div>
        `;
        renderExerciseHiddenFields();
        return;
    }

    board.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Questions</h5>
            <span class="badge bg-primary">${stagedExerciseQuestions.length} total</span>
        </div>
        ${stagedExerciseQuestions.map((question, index) => {
            const imageUrl = question.file_input?.files?.[0]
                ? URL.createObjectURL(question.file_input.files[0])
                : (question.image_path ? `/storage/${question.image_path}` : '');
            const options = question.options ? Object.entries(question.options).map(([key, option]) => `
                <div class="col-md-6"><div class="option-box ${question.correct_answer === key ? 'is-correct' : ''}"><strong>${key}.</strong> ${option || ''}</div></div>
            `).join('') : '';
            return `
                <div class="question-item">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="question-title">Question ${index + 1}</div>
                            <span class="badge bg-secondary">${String(question.question_type).replace('_', ' ')}</span>
                        </div>
                        <button class="btn btn-sm btn-outline-danger" type="button" onclick="removeStagedExerciseQuestion(${index})" title="Delete question"><i class="far fa-trash-alt"></i></button>
                    </div>
                    <div class="mt-2">${question.question_text || ''}</div>
                    ${imageUrl ? `<img class="question-image" src="${imageUrl}" alt="">` : ''}
                    ${options ? `<div class="row g-2 mt-2">${options}</div>` : ''}
                    ${question.question_type === 'true_false' ? `<div class="mt-2"><strong>Answer:</strong> ${question.correct_answer === 'true' ? 'True' : 'False'}</div>` : ''}
                    ${question.marking_guide ? `<div class="alert alert-light border mt-2 mb-0"><strong>Guide:</strong> ${question.marking_guide}</div>` : ''}
                    <div class="fw-bold mt-2">${question.marks} marks</div>
                </div>
            `;
        }).join('')}
    `;
    renderExerciseHiddenFields();
}

function appendHidden(container, name, value) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value ?? '';
    container.appendChild(input);
}

function renderExerciseHiddenFields() {
    const container = document.getElementById('exerciseHiddenFields');
    const fileInputs = stagedExerciseQuestions.map((question) => question.file_input).filter(Boolean);
    container.replaceChildren();

    stagedExerciseQuestions.forEach((question, index) => {
        appendHidden(container, `exercise_questions[${index}][question_type]`, question.question_type);
        appendHidden(container, `exercise_questions[${index}][question_text]`, question.question_text);
        appendHidden(container, `exercise_questions[${index}][correct_answer]`, question.correct_answer || '');
        appendHidden(container, `exercise_questions[${index}][marking_guide]`, question.marking_guide || '');
        appendHidden(container, `exercise_questions[${index}][marks]`, question.marks || 1);
        appendHidden(container, `exercise_questions[${index}][existing_image_path]`, question.image_path || '');

        if (question.options) {
            appendHidden(container, `exercise_questions[${index}][option_a]`, question.options.A || '');
            appendHidden(container, `exercise_questions[${index}][option_b]`, question.options.B || '');
            appendHidden(container, `exercise_questions[${index}][option_c]`, question.options.C || '');
            appendHidden(container, `exercise_questions[${index}][option_d]`, question.options.D || '');
        }

        if (question.file_input) {
            question.file_input.name = `exercise_questions[${index}][question_image]`;
            question.file_input.id = '';
            question.file_input.className = 'd-none';
            container.appendChild(question.file_input);
        }
    });
}

function removeStagedExerciseQuestion(index) {
    stagedExerciseQuestions.splice(index, 1);
    markExerciseBuilderTouched();
    renderExerciseBoard();
}

function noteContentForExerciseAi() {
    tinymce.triggerSave();
    return [
        editorContent('previous_knowledge'),
        editorContent('learning_objectives'),
        editorContent('teaching_materials'),
        editorContent('introduction'),
        editorContent('main_content'),
        editorContent('evaluation'),
        editorContent('conclusion'),
        editorContent('assignment')
    ].join('\n\n');
}

document.getElementById('toggleExerciseBuilderBtn').addEventListener('click', () => {
    document.getElementById('hasExerciseInput').value = '1';
});

document.getElementById('draftQuestionType').addEventListener('change', syncDraftQuestionType);
syncDraftQuestionType();

document.getElementById('stageExerciseQuestionBtn').addEventListener('click', () => {
    tinymce.triggerSave();
    const type = document.getElementById('draftQuestionType').value;
    const questionText = editorContent('draftQuestionText');

    if (!plainTextFromHtml(questionText)) {
        alert('Enter the question text.');
        tinymce.get('draftQuestionText')?.focus();
        return;
    }

    const question = {
        question_type: type,
        question_text: questionText,
        options: null,
        correct_answer: '',
        marking_guide: type === 'theory' ? editorContent('draftMarkingGuide') : '',
        marks: Number(document.getElementById('draftQuestionMarks').value || 1),
        image_path: null,
        file_input: null
    };

    if (type === 'objective') {
        question.options = {
            A: editorContent('draftOptionA'),
            B: editorContent('draftOptionB'),
            C: editorContent('draftOptionC'),
            D: editorContent('draftOptionD')
        };

        if (!['A', 'B', 'C', 'D'].every((key) => plainTextFromHtml(question.options[key]))) {
            alert('Fill all four options.');
            return;
        }

        if (!document.getElementById('draftObjectiveAnswer').value) {
            alert('Select the correct answer.');
            return;
        }

        question.correct_answer = document.getElementById('draftObjectiveAnswer').value;
    } else if (type === 'true_false') {
        question.correct_answer = document.getElementById('draftTrueFalseAnswer').value;
    }

    const fileInput = document.getElementById('draftQuestionImage');
    if (fileInput?.files?.length) {
        question.file_input = fileInput;
    }

    stagedExerciseQuestions.push(question);
    markExerciseBuilderTouched();
    resetDraftQuestionForm();
    renderExerciseBoard();
});

function syncExerciseAiOverallPoints() {
    const count = Number(document.getElementById('exerciseAiCount').value || 0);
    const marks = Number(document.getElementById('exerciseAiMarks').value || 0);
    document.getElementById('exerciseAiOverall').value = Math.max(0.5, count * marks);
}

document.getElementById('exerciseAiCount').addEventListener('input', syncExerciseAiOverallPoints);
document.getElementById('exerciseAiMarks').addEventListener('input', syncExerciseAiOverallPoints);
syncExerciseAiOverallPoints();

document.getElementById('generateExerciseFromNoteBtn').addEventListener('click', () => {
    const button = document.getElementById('generateExerciseFromNoteBtn');
    const topic = document.getElementById('exerciseAiTopic').value.trim() || lessonAiFields.topic.value.trim();

    if (!topic) {
        alert('Enter a topic first.');
        return;
    }

    setAiButtonLoading(button, true, '<i class="fas fa-magic me-2"></i>Generate');

    fetch('{{ route('teacher.lesson-notes.exercise-draft') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            school_class_id: document.querySelector('[name="school_class_id"]').value,
            subject_id: document.querySelector('[name="subject_id"]').value,
            topic,
            note_content: noteContentForExerciseAi(),
            number_of_questions: document.getElementById('exerciseAiCount').value,
            marks_per_question: document.getElementById('exerciseAiMarks').value,
            overall_points: document.getElementById('exerciseAiOverall').value,
            difficulty: document.getElementById('exerciseAiDifficulty').value
        })
    })
        .then(async response => {
            const data = await readAiResponse(response);
            stagedExerciseQuestions.push(...data.questions.map((question) => ({
                question_type: question.question_type || 'objective',
                question_text: question.question_text || '',
                options: question.options || null,
                correct_answer: question.correct_answer || '',
                marking_guide: question.marking_guide || '',
                marks: Number(question.marks || document.getElementById('exerciseAiMarks').value || 1),
                image_path: null,
                file_input: null
            })));
            markExerciseBuilderTouched();
            renderExerciseBoard();
        })
        .catch(error => alert(error.message))
        .finally(() => setAiButtonLoading(button, false, '<i class="fas fa-magic me-2"></i>Generate'));
});

renderExerciseBoard();
</script>
@endsection
