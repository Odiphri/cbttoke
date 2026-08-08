@extends('layouts.admin')

@section('title', 'Lesson Note Coverage')

@section('content')
<div class="container-fluid">
    <form method="GET" class="card p-3 mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Session</label>
                <select class="form-select" name="academic_session_id">
                    <option value="">Active session</option>
                    @foreach($sessions as $session)
                        <option value="{{ $session->id }}" @selected((string) $selectedSessionId === (string) $session->id)>{{ $session->display_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Teacher</label>
                <select class="form-select" name="teacher_id">
                    <option value="">All teachers</option>
                    @foreach($teachers as $teacher)
                        <option value="{{ $teacher->id }}" @selected((string) $selectedTeacherId === (string) $teacher->id)>{{ $teacher->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Class</label>
                <select class="form-select" name="school_class_id">
                    <option value="">All classes</option>
                    @foreach($classes as $class)
                        <option value="{{ $class->id }}" @selected((string) $selectedClassId === (string) $class->id)>{{ $class->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Subject</label>
                <select class="form-select" name="subject_id">
                    <option value="">All subjects</option>
                    @foreach($subjects as $subject)
                        <option value="{{ $subject->id }}" @selected((string) $selectedSubjectId === (string) $subject->id)>{{ $subject->name }} - {{ $subject->schoolClass->full_name ?? 'No class' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary-custom flex-fill">Filter</button>
                <a class="btn btn-light" href="{{ route('admin.lesson-note-coverage') }}">Clear</a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Expected Notes By Week</span>
            <span class="small text-white-50">{{ $assignments->count() }} teacher/class/subject assignment(s)</span>
        </div>
        <div class="card-body">
            <div class="table-responsive coverage-table">
                <table class="table table-sm table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>Class</th>
                            <th>Subject</th>
                            @foreach($weeks as $week)
                                <th class="text-center">W{{ $week }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assignments as $assignment)
                            <tr>
                                <td>{{ $assignment->first_name }} {{ $assignment->last_name }}</td>
                                <td>{{ trim($assignment->level.' '.$assignment->stream) }}</td>
                                <td>{{ $assignment->subject_name }}</td>
                                @foreach($weeks as $week)
                                    @php
                                        $key = $assignment->teacher_id . ':' . $assignment->class_id . ':' . $assignment->subject_id . ':' . $week;
                                        $note = $notes->get($key);
                                    @endphp
                                    <td class="text-center">
                                        @if($note)
                                            <a class="coverage-pill {{ $note->status }}" href="{{ route('admin.lesson-notes.show', $note) }}" title="{{ $note->statusLabel() }}">
                                                {{ strtoupper(substr($note->status, 0, 1)) }}
                                            </a>
                                        @else
                                            <span class="coverage-pill missing" title="Missing">M</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($weeks) + 3 }}" class="text-muted">No teacher subject assignments found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3 small">
                <span class="coverage-pill approved">A</span> Approved
                <span class="coverage-pill pending">P</span> Pending
                <span class="coverage-pill returned">R</span> Returned
                <span class="coverage-pill rejected">J</span> Rejected
                <span class="coverage-pill draft">D</span> Draft
                <span class="coverage-pill missing">M</span> Missing
            </div>
        </div>
    </div>
</div>

<style>
.coverage-table {
    max-height: 70vh;
}
.coverage-pill {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: .72rem;
    font-weight: 700;
    color: #0a1931;
    background: #eef3f8;
    text-decoration: none;
}
.coverage-pill.approved { background: #dff5e7; color: #137333; }
.coverage-pill.pending { background: #fff2cc; color: #8a5b00; }
.coverage-pill.returned { background: #dff3ff; color: #075985; }
.coverage-pill.rejected { background: #ffe4e6; color: #b42318; }
.coverage-pill.draft { background: #edf1f5; color: #4b5563; }
.coverage-pill.missing { background: #f8d7da; color: #842029; }
</style>
@endsection
