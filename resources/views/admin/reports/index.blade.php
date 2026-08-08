@extends('layouts.admin')

@section('title', 'Reports Center')

@section('content')
<div class="container-fluid">
    <form method="GET" class="card p-3 mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Session</label>
                <select class="form-select" name="academic_session_id">
                    <option value="">All sessions</option>
                    @foreach($sessions as $session)
                        <option value="{{ $session->id }}" @selected((string) $selectedSessionId === (string) $session->id)>{{ $session->display_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Class</label>
                <select class="form-select" name="school_class_id">
                    <option value="">All classes</option>
                    @foreach($classes as $class)
                        <option value="{{ $class->id }}" @selected((string) $selectedClassId === (string) $class->id)>{{ $class->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Subject</label>
                <select class="form-select" name="subject_id">
                    <option value="">All subjects</option>
                    @foreach($subjects as $subject)
                        <option value="{{ $subject->id }}" @selected((string) $selectedSubjectId === (string) $subject->id)>{{ $subject->name }} - {{ $subject->schoolClass->full_name ?? 'No class' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary-custom flex-fill"><i class="fas fa-filter me-1"></i> Filter</button>
                <a class="btn btn-light" href="{{ route('admin.reports') }}">Clear</a>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="stat-number">{{ $summary['submitted_exam_attempts'] }}</div><div class="stat-label">Submitted Exams</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="stat-number">{{ $summary['average_exam_score'] }}%</div><div class="stat-label">Average Exam Score</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="stat-number">{{ $summary['exercise_attempts'] }}</div><div class="stat-label">Exercise Attempts</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="stat-number">{{ $summary['awaiting_marking'] }}</div><div class="stat-label">Awaiting Marking</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="stat-number">{{ $summary['lesson_notes'] }}</div><div class="stat-label">Lesson Notes</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="stat-number">{{ $summary['approved_lesson_notes'] }}</div><div class="stat-label">Approved Notes</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="stat-number">{{ $summary['payments_recorded'] }}</div><div class="stat-label">Payment Records</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="stat-number">{{ number_format($summary['payment_balance']) }}</div><div class="stat-label">Outstanding Balance</div></div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">Exam Results Snapshot</div>
                <div class="card-body">
                    @forelse($examRows as $exam)
                        <a class="report-row" href="{{ route('admin.results.show', $exam) }}">
                            <div>
                                <div class="fw-semibold">{{ $exam->title }}</div>
                                <div class="small text-muted">{{ $exam->subject->name ?? 'No subject' }} / {{ $exam->schoolClass->full_name ?? 'No class' }}</div>
                            </div>
                            <span class="badge bg-light text-dark">{{ $exam->submitted_attempts_count }} attempts</span>
                        </a>
                    @empty
                        <div class="text-muted">No exams match this filter.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">Exercise Snapshot</div>
                <div class="card-body">
                    @forelse($exerciseRows as $exercise)
                        <a class="report-row" href="{{ route('admin.exercises') }}">
                            <div>
                                <div class="fw-semibold">{{ $exercise->title }}</div>
                                <div class="small text-muted">{{ $exercise->lessonNote?->subject?->name }} / {{ $exercise->lessonNote?->schoolClass?->full_name }}</div>
                            </div>
                            <span class="badge {{ $exercise->awaiting_marking_count ? 'bg-warning text-dark' : 'bg-light text-dark' }}">{{ $exercise->awaiting_marking_count }} unmarked</span>
                        </a>
                    @empty
                        <div class="text-muted">No exercises match this filter.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">Payments Snapshot</div>
                <div class="card-body">
                    @forelse($paymentRows as $payment)
                        <a class="report-row" href="{{ route('admin.payments.students.show', $payment->student) }}">
                            <div>
                                <div class="fw-semibold">{{ $payment->student?->full_name }}</div>
                                <div class="small text-muted">{{ $payment->student?->assignedClass?->full_name }} / {{ ucfirst($payment->status) }}</div>
                            </div>
                            <strong>{{ number_format((float) $payment->amount_paid) }} / {{ number_format((float) $payment->total_fees) }}</strong>
                        </a>
                    @empty
                        <div class="text-muted">No payment rows match this filter.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">Lesson Note Status</div>
        <div class="card-body d-flex flex-wrap gap-2">
            @forelse($lessonStatusCounts as $status => $total)
                <a class="btn btn-light" href="{{ route('admin.lesson-notes.index', ['status' => $status]) }}">{{ ucfirst($status) }}: {{ $total }}</a>
            @empty
                <span class="text-muted">No lesson note records for this filter.</span>
            @endforelse
        </div>
    </div>
</div>

<style>
.report-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    color: inherit;
    text-decoration: none;
    padding: 10px 0;
    border-bottom: 1px solid #edf1f5;
}
.report-row:last-child {
    border-bottom: 0;
}
</style>
@endsection
