@extends('layouts.admin')

@section('title', 'Exercise Oversight')

@section('content')
<div class="container-fluid">
    <form method="GET" class="card p-3 mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Class</label>
                <select class="form-select" name="school_class_id">
                    <option value="">All classes</option>
                    @foreach($classes as $class)
                        <option value="{{ $class->id }}" @selected((string) $selectedClassId === (string) $class->id)>{{ $class->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Subject</label>
                <select class="form-select" name="subject_id">
                    <option value="">All subjects</option>
                    @foreach($subjects as $subject)
                        <option value="{{ $subject->id }}" @selected((string) $selectedSubjectId === (string) $subject->id)>{{ $subject->name }} - {{ $subject->schoolClass->full_name ?? 'No class' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">All</option>
                    <option value="awaiting_marking" @selected($selectedStatus === 'awaiting_marking')>Awaiting marking</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary-custom flex-fill">Filter</button>
                <a class="btn btn-light" href="{{ route('admin.exercises') }}">Clear</a>
            </div>
        </div>
    </form>

    <div class="card mb-3">
        <div class="card-header">Submissions Awaiting Marking</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Exercise</th>
                            <th>Teacher</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($awaitingAttempts as $attempt)
                            <tr>
                                <td>{{ $attempt->student?->full_name }}<br><span class="text-muted small">{{ $attempt->student?->assignedClass?->full_name }}</span></td>
                                <td>{{ $attempt->exercise?->title }}<br><span class="text-muted small">{{ $attempt->exercise?->lessonNote?->subject?->name }}</span></td>
                                <td>{{ $attempt->exercise?->lessonNote?->teacher?->full_name }}</td>
                                <td>{{ $attempt->submitted_at?->format('M j, Y g:i A') ?? 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted">Nothing is waiting for marking.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">All Lesson Exercises</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Exercise</th>
                            <th>Class / Subject</th>
                            <th>Teacher</th>
                            <th>Questions</th>
                            <th>Attempts</th>
                            <th>Awaiting</th>
                            <th>Marked</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($exercises as $exercise)
                            <tr>
                                <td>
                                    <strong>{{ $exercise->title }}</strong>
                                    <div class="small text-muted">{{ $exercise->lessonNote?->title }}</div>
                                </td>
                                <td>{{ $exercise->lessonNote?->schoolClass?->full_name }}<br><span class="text-muted small">{{ $exercise->lessonNote?->subject?->name }}</span></td>
                                <td>{{ $exercise->lessonNote?->teacher?->full_name }}</td>
                                <td>{{ $exercise->questions_count }}</td>
                                <td>{{ $exercise->attempts_count }}</td>
                                <td><span class="badge {{ $exercise->awaiting_marking_count ? 'bg-warning text-dark' : 'bg-light text-dark' }}">{{ $exercise->awaiting_marking_count }}</span></td>
                                <td>{{ $exercise->marked_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted">No lesson exercises found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $exercises->links() }}
        </div>
    </div>
</div>
@endsection
