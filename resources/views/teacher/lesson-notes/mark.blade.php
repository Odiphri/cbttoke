@extends('layouts.admin')

@section('title', $attempt->status === 'marked' ? 'Edit Marking' : 'View Submission')

@section('content')
@php
    $answersByQuestion = $attempt->answers->keyBy('exercise_question_id');
    $totalMarks = $lessonExercise->totalMarks();
@endphp

<form method="POST" action="{{ route('teacher.exercises.submissions.update', [$lessonExercise, $attempt]) }}" id="paperMarkingForm">
    @csrf
    @method('PUT')

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h2 class="h4 mb-1">{{ $attempt->student?->full_name }}</h2>
            <p class="text-muted mb-0">{{ $lessonExercise->title }} / Attempt {{ $attempt->attempt_number }}</p>
        </div>
        <div class="text-end">
            <span class="badge {{ $attempt->status === 'awaiting_marking' ? 'bg-warning text-dark' : 'bg-success' }}">{{ str_replace('_', ' ', $attempt->status) }}</span>
            @if($attempt->status === 'marked')
                <div class="small text-muted mt-1">You can edit these marks and save again.</div>
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Final total score</label>
                <div class="input-group">
                    <input class="form-control" id="final_total_score" name="final_total_score" type="number" step="0.5" min="0" max="{{ $totalMarks }}" value="{{ old('final_total_score', $attempt->total_score) }}" required>
                    <span class="input-group-text">/ {{ $totalMarks }}</span>
                </div>
            </div>
            <div class="col-md-8">
                <label class="form-label">Overall feedback</label>
                <input class="form-control" name="overall_feedback" value="{{ old('overall_feedback', $attempt->overall_feedback) }}" placeholder="Overall comment for the student">
            </div>
        </div>
    </div>

    @foreach($lessonExercise->questions as $index => $question)
        @php
            $answer = $answersByQuestion->get($question->id);
            $isMarked = (bool) $answer?->marked_at;
            $isFailed = $isMarked && (float) ($answer?->awarded_marks ?? 0) <= 0;
        @endphp
        <div class="card marking-script mb-3 {{ $isMarked ? ($isFailed ? 'is-failed' : 'is-marked') : '' }}">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>Question {{ $index + 1 }} <small class="text-white-50">({{ str_replace('_', ' ', $question->question_type) }})</small></span>
                <span class="d-flex align-items-center gap-2">
                    <span class="marked-indicator badge bg-light text-dark {{ $isMarked ? '' : 'd-none' }}">
                        <i class="status-icon fas fa-{{ $isFailed ? 'times' : 'check' }} me-1"></i><span class="status-text">{{ $isFailed ? 'Failed' : 'Marked' }}</span>
                        <button type="button" class="btn btn-link btn-sm p-0 ms-1 edit-mark-btn" title="Edit mark"><i class="fas fa-pencil-alt"></i></button>
                    </span>
                    <span>{{ $question->marks }} marks</span>
                </span>
            </div>
            <div class="card-body">
                <div class="mb-3">{!! $question->question_text !!}</div>
                @if($question->marking_guide)
                    <div class="alert alert-light border"><strong>Marking guide:</strong> {!! $question->marking_guide !!}</div>
                @elseif($question->correct_answer)
                    <div class="alert alert-light border"><strong>Expected answer:</strong> {{ $question->correct_answer }}</div>
                @endif

                <div class="student-answer mb-3">
                    <div class="small text-muted text-uppercase">Student answer</div>
                    <div>{{ $answer?->answer_text ?: 'No answer submitted' }}</div>
                </div>

                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Awarded marks</label>
                        <input class="form-control mark-input" type="number" step="0.5" min="0" max="{{ $question->marks }}" name="marks[{{ $answer->id }}]" value="{{ old('marks.'.$answer->id, $answer?->awarded_marks ?? 0) }}" data-max="{{ $question->marks }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Feedback / correction</label>
                        <input class="form-control feedback-input" name="feedback[{{ $answer->id }}]" value="{{ old('feedback.'.$answer->id, $answer?->teacher_feedback) }}" placeholder="Teacher note or correct answer">
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="button" class="btn btn-success flex-fill mark-full-btn"><i class="fas fa-check me-1"></i> Mark</button>
                        <button type="button" class="btn btn-outline-danger flex-fill fail-btn"><i class="fas fa-times me-1"></i> Fail</button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <div class="d-flex gap-2 flex-wrap mb-4">
        <button class="btn btn-primary-custom"><i class="fas fa-save me-1"></i> {{ $attempt->status === 'marked' ? 'Update Marking' : 'Save Marking' }}</button>
        <a class="btn btn-outline-secondary" href="{{ route('teacher.exercises.submissions.index', $lessonExercise) }}">Back to Submissions</a>
    </div>
</form>

<style>
.student-answer {
    border: 1px solid #dce4ee;
    border-radius: 8px;
    padding: 12px;
    background: #fbfcfe;
}
.marking-script.is-marked {
    border-color: #24a148;
}
.marking-script.is-marked .card-header {
    background: #eefaf2;
    color: #137333;
}
.marking-script.is-marked .card-header small {
    color: #4d7d5d !important;
}
.marking-script.is-failed {
    border-color: #dc3545;
}
.marking-script.is-failed .card-header {
    background: #fff1f2;
    color: #b42318;
}
.marking-script.is-failed .card-header small {
    color: #b95c56 !important;
}
.marked-indicator .edit-mark-btn {
    color: inherit;
    line-height: 1;
    text-decoration: none;
    vertical-align: baseline;
}
</style>

<script>
function setQuestionMarked(card, state = 'marked') {
    const failed = state === 'failed';

    card.classList.add('is-marked');
    card.classList.toggle('is-failed', failed);
    card.classList.toggle('is-marked', !failed);

    const indicator = card.querySelector('.marked-indicator');
    const icon = card.querySelector('.status-icon');
    const text = card.querySelector('.status-text');

    indicator?.classList.remove('d-none');
    icon?.classList.toggle('fa-check', !failed);
    icon?.classList.toggle('fa-times', failed);

    if (text) {
        text.textContent = failed ? 'Failed' : 'Marked';
    }
}

function recalculateFinalScore() {
    const total = [...document.querySelectorAll('.mark-input')]
        .reduce((sum, input) => sum + Number(input.value || 0), 0);
    document.getElementById('final_total_score').value = total;
}

document.querySelectorAll('.mark-input').forEach((input) => {
    input.addEventListener('input', () => {
        setQuestionMarked(input.closest('.marking-script'), Number(input.value || 0) <= 0 ? 'failed' : 'marked');
        recalculateFinalScore();
    });
});

document.querySelectorAll('.mark-full-btn').forEach((button) => {
    button.addEventListener('click', () => {
        const card = button.closest('.marking-script');
        const marks = card.querySelector('.mark-input');
        marks.value = marks.dataset.max;
        setQuestionMarked(card, 'marked');
        recalculateFinalScore();
    });
});

document.querySelectorAll('.fail-btn').forEach((button) => {
    button.addEventListener('click', () => {
        const card = button.closest('.marking-script');
        const marks = card.querySelector('.mark-input');
        const feedback = card.querySelector('.feedback-input');
        const correction = window.prompt('Enter the correct answer or correction for this question:');

        if (correction === null) {
            return;
        }

        marks.value = 0;
        feedback.value = correction.trim() ? `Correct answer: ${correction.trim()}` : 'Marked incorrect.';
        setQuestionMarked(card, 'failed');
        recalculateFinalScore();
    });
});

document.querySelectorAll('.edit-mark-btn').forEach((button) => {
    button.addEventListener('click', () => {
        const input = button.closest('.marking-script')?.querySelector('.mark-input');
        input?.focus();
        input?.select();
    });
});
</script>
@endsection
