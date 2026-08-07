<form method="POST" enctype="multipart/form-data" action="{{ $action }}" class="exercise-question-builder">
    @csrf
    @if(($method ?? 'POST') !== 'POST') @method($method) @endif
    <div class="row g-3">
        <div class="col-md-3">
            <label class="form-label">Question Type</label>
            <select class="form-select question-type-select" name="question_type">
                <option value="objective">Multiple choice</option>
                <option value="true_false">True / false</option>
                <option value="theory">Theory</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Marks</label>
            <input class="form-control" name="marks" type="number" step="0.5" min="0.5" value="1">
        </div>
        <div class="col-md-2">
            <label class="form-label">Order</label>
            <input class="form-control" name="display_order" type="number" min="1" placeholder="Auto">
        </div>
        <div class="col-md-5">
            <label class="form-label">Image</label>
            <input class="form-control" name="question_image" type="file" accept=".jpg,.jpeg,.png,.webp">
        </div>
        <div class="col-12">
            <label class="form-label">Question</label>
            <textarea class="form-control question-rich-text" name="question_text" rows="5" placeholder="Type the question exactly as students should see it"></textarea>
        </div>
    </div>

    <div class="objective-fields row g-3 mt-1">
        @foreach(['a','b','c','d'] as $option)
        <div class="col-md-6">
            <label class="form-label">Option {{ strtoupper($option) }}</label>
            <textarea class="form-control question-rich-text-short" name="option_{{ $option }}" rows="2" placeholder="Option {{ strtoupper($option) }}"></textarea>
        </div>
        @endforeach
    </div>

    <div class="answer-fields row g-3 mt-1">
        <div class="col-md-6 correct-answer-field">
            <label class="form-label">Correct Answer</label>
            <select class="form-select objective-answer" name="correct_answer">
                <option value="">Select answer</option>
                <option value="A">A</option>
                <option value="B">B</option>
                <option value="C">C</option>
                <option value="D">D</option>
            </select>
            <select class="form-select true-false-answer d-none" data-name="correct_answer">
                <option value="true">True</option>
                <option value="false">False</option>
            </select>
        </div>
        <div class="col-md-6 theory-guide-field d-none">
            <label class="form-label">Private Marking Guide</label>
            <textarea class="form-control question-rich-text-short" name="marking_guide" rows="2" placeholder="Only teachers, HOD and admin see this"></textarea>
        </div>
    </div>

    <div class="mt-3">
        <button class="btn btn-primary-custom"><i class="fas fa-plus me-1"></i> Add Question</button>
    </div>
</form>
