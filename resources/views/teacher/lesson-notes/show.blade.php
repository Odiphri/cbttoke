@extends('layouts.admin')

@section('title', 'Lesson Note Details')

@section('content')
<div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
    <div><h2 class="h4">{{ $note->title }}</h2><span class="badge {{ $note->statusBadgeClass() }}">{{ $note->statusLabel() }}</span></div>
    <div>
        @if($note->isEditable())<a class="btn btn-outline-secondary" href="{{ route('teacher.lesson-notes.edit', $note) }}"><i class="fas fa-edit me-1"></i> Edit</a>@endif
        @if($note->isEditable())<form class="d-inline" method="POST" action="{{ route('teacher.lesson-notes.submit', $note) }}">@csrf<button class="btn btn-primary-custom"><i class="fas fa-paper-plane me-1"></i> Submit</button></form>@endif
        @if(in_array($note->status, ['pending','approved'], true))<form class="d-inline" method="POST" action="{{ route('teacher.lesson-notes.withdraw', $note) }}">@csrf<button class="btn btn-outline-danger">Withdraw</button></form>@endif
    </div>
</div>

<ul class="nav nav-tabs mb-3"><li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#note">Lesson Note</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#exercise">Exercise</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#submissions">Submissions</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#history">Approval History</button></li></ul>
<div class="tab-content">
    <div class="tab-pane fade show active" id="note">
        <div class="card"><div class="card-body">
            <p class="text-muted">{{ $note->academicSession?->display_name }} / Week {{ $note->week_number }} / {{ $note->schoolClass?->full_name }} / {{ $note->subject?->name }}</p>
            <h5>{{ $note->topic }} @if($note->subtopic)<small class="text-muted">- {{ $note->subtopic }}</small>@endif</h5>
            <div class="lesson-content">{!! $note->main_content !!}</div>
            @foreach(['previous_knowledge'=>'Previous Knowledge','learning_objectives'=>'Learning Objectives','teaching_materials'=>'Teaching Materials','introduction'=>'Introduction','evaluation'=>'Evaluation','conclusion'=>'Conclusion','assignment'=>'Assignment'] as $field => $label)
                @if($note->$field)<h6 class="mt-3">{{ $label }}</h6><div>{!! $note->$field !!}</div>@endif
            @endforeach
            @if($note->attachments->isNotEmpty())<h6>Attachments</h6>@foreach($note->attachments as $attachment)<a class="btn btn-sm btn-outline-primary me-1 mb-1" href="{{ asset('storage/'.$attachment->stored_path) }}" target="_blank"><i class="fas fa-paperclip"></i> {{ $attachment->original_filename }}</a>@endforeach @endif
        </div></div>
    </div>
    <div class="tab-pane fade" id="exercise">
        @if($note->exercise)
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>{{ $note->exercise->title }}</span>
                <a class="btn btn-sm btn-light" href="{{ route('teacher.exercises.submissions.index', $note->exercise) }}"><i class="fas fa-users me-1"></i> View Submissions</a>
            </div>
            <div class="card-body">
                @if($note->exercise->instructions)<div class="mb-3">{!! $note->exercise->instructions !!}</div>@endif
                <div class="row g-2">
                    <div class="col-md-3"><span class="badge bg-light text-dark">Mode: {{ str_replace('_',' ', $note->exercise->attempt_mode) }}</span></div>
                    <div class="col-md-3"><span class="badge bg-light text-dark">Max: {{ $note->exercise->attempt_mode === 'unlimited' ? 'Unlimited' : ($note->exercise->max_attempts ?: 1) }}</span></div>
                    <div class="col-md-3"><span class="badge bg-light text-dark">Score: {{ $note->exercise->score_selection_method }}</span></div>
                    <div class="col-md-3"><span class="badge bg-light text-dark">{{ $note->exercise->questions->count() }} questions</span></div>
                </div>
            </div>
        </div>
        @if($note->isEditable())
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-magic me-1"></i> AI Exercise Generator</div>
            <div class="card-body">
                <form id="aiExerciseQuestionForm" class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Topic</label>
                        <input class="form-control" name="topic" value="{{ $note->topic }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Questions</label>
                        <input class="form-control" name="number_of_questions" type="number" min="1" max="15" value="5" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Marks Each</label>
                        <input class="form-control" name="marks_per_question" type="number" step="0.5" min="0.5" value="1" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Difficulty</label>
                        <select class="form-select" name="difficulty">
                            <option value="easy">Easy</option>
                            <option value="medium" selected>Medium</option>
                            <option value="hard">Hard</option>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button class="btn btn-primary-custom w-100" id="aiExerciseBtn" title="Generate"><i class="fas fa-magic"></i></button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-plus-circle me-1"></i> Set Exercise Question</div>
            <div class="card-body">@include('teacher.lesson-notes.partials.question-form', ['action' => route('teacher.lesson-notes.questions.store', $note), 'method' => 'POST'])</div>
        </div>
        @endif
        <div class="questions-board">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Questions</h5>
                <span class="badge bg-primary">{{ $note->exercise->questions->count() }} total</span>
            </div>
            @forelse($note->exercise->questions as $index => $question)
                <div class="question-item">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="fw-bold">Question {{ $index + 1 }}</div>
                            <span class="badge bg-secondary">{{ str_replace('_',' ', $question->question_type) }}</span>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold">{{ $question->marks }} marks</div>
                            @if($note->isEditable())
                            <form method="POST" action="{{ route('teacher.lesson-notes.questions.destroy', [$note, $question]) }}" onsubmit="return confirm('Delete this exercise question?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger mt-1"><i class="far fa-trash-alt"></i></button>
                            </form>
                            @endif
                        </div>
                    </div>
                    <div class="mt-2">{!! $question->question_text !!}</div>
                    @if($question->options)
                        <div class="row g-2 mt-2">
                        @foreach($question->options as $key => $option)
                            <div class="col-md-6"><div class="option-box {{ $question->correct_answer === $key ? 'is-correct' : '' }}"><strong>{{ $key }}.</strong> {!! $option !!}</div></div>
                        @endforeach
                        </div>
                    @elseif($question->question_type === 'true_false')
                        <div class="mt-2"><strong>Answer:</strong> {{ ucfirst($question->correct_answer) }}</div>
                    @endif
                    @if($question->marking_guide)<div class="alert alert-light border mt-2 mb-0"><strong>Guide:</strong> {!! $question->marking_guide !!}</div>@endif
                </div>
            @empty
                <div class="text-center text-muted py-4">No questions have been added yet.</div>
            @endforelse
        </div>
        @else
        <div class="alert alert-info">No exercise has been attached.</div>
        @endif
    </div>
    <div class="tab-pane fade" id="submissions">
        @if($note->exercise)<a class="btn btn-primary-custom" href="{{ route('teacher.exercises.submissions.index', $note->exercise) }}">Open submissions</a>@else <p class="text-muted">No exercise submissions.</p>@endif
    </div>
    <div class="tab-pane fade" id="history">@forelse($note->reviews as $review)<div class="card"><div class="card-body"><strong>{{ ucfirst($review->action) }}</strong> by {{ $review->reviewer?->full_name }}<br>{{ $review->comments }}</div></div>@empty<p class="text-muted">No review history yet.</p>@endforelse</div>
</div>
<style>
.questions-board {
    background: #fff;
    border: 1px solid #dde4ed;
    border-radius: 8px;
    padding: 18px;
}
.question-item {
    border: 1px solid #e4eaf1;
    border-radius: 8px;
    padding: 14px;
    margin-bottom: 12px;
    background: #fbfcfe;
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
<script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
<script>
const questionTinyBaseConfig = {
    license_key: 'gpl',
    menubar: 'file edit insert view format table tools help',
    plugins: 'advlist autolink lists link image media table code fullscreen preview searchreplace visualblocks wordcount',
    toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | image link table | blockquote hr removeformat | code fullscreen preview',
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
    setup: (editor) => {
        editor.on('change keyup', () => editor.save());
        editor.on('init', () => editor.save());
    }
};

tinymce.init({
    ...questionTinyBaseConfig,
    selector: '.question-rich-text',
    height: 320
});

tinymce.init({
    ...questionTinyBaseConfig,
    selector: '.question-rich-text-short',
    height: 180,
    menubar: false
});

document.querySelectorAll('.exercise-question-builder').forEach((form) => {
    const type = form.querySelector('.question-type-select');
    const objectiveFields = form.querySelector('.objective-fields');
    const objectiveAnswer = form.querySelector('.objective-answer');
    const trueFalseAnswer = form.querySelector('.true-false-answer');
    const guideField = form.querySelector('.theory-guide-field');

    const sync = () => {
        const value = type.value;
        objectiveFields.classList.toggle('d-none', value !== 'objective');
        objectiveAnswer.classList.toggle('d-none', value !== 'objective');
        trueFalseAnswer.classList.toggle('d-none', value !== 'true_false');
        guideField.classList.toggle('d-none', value !== 'theory');
        objectiveAnswer.name = value === 'objective' ? 'correct_answer' : '';
        trueFalseAnswer.name = value === 'true_false' ? 'correct_answer' : '';
    };

    type.addEventListener('change', sync);
    form.addEventListener('submit', () => tinymce.triggerSave());
    sync();
});

if (window.location.hash === '#exercise') {
    document.querySelector('[data-bs-target="#exercise"]')?.click();
}

document.getElementById('aiExerciseQuestionForm')?.addEventListener('submit', (event) => {
    event.preventDefault();
    tinymce.triggerSave();
    const form = event.currentTarget;
    const button = document.getElementById('aiExerciseBtn');
    const original = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    fetch('{{ $note->exercise ? route('teacher.lesson-notes.questions.ai-generate', $note) : '#' }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        },
        body: new FormData(form)
    })
        .then(async response => {
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'AI could not generate questions.');
            }
            window.location.href = '{{ route('teacher.lesson-notes.show', $note) }}#exercise';
            window.location.reload();
        })
        .catch(error => alert(error.message))
        .finally(() => {
            button.disabled = false;
            button.innerHTML = original;
        });
});
</script>
@endsection
