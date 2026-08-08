@extends('layouts.admin')

@section('title', 'Teacher Workload')

@section('content')
<div class="container-fluid">
    <form method="GET" class="card p-3 mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-10">
                <label class="form-label">Search teacher</label>
                <input class="form-control" type="search" name="search" value="{{ $search }}" placeholder="Name or portal ID">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary-custom flex-fill">Search</button>
                <a class="btn btn-light" href="{{ route('admin.teacher-workload') }}">Clear</a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Staff Academic Workload</span>
            <span class="small text-white-50">{{ $activeSession?->display_name ?? 'No active session' }}</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>Subjects</th>
                            <th>Classes</th>
                            <th>Lesson Notes</th>
                            <th>Pending</th>
                            <th>Approved</th>
                            <th>Returned</th>
                            <th>Unmarked Exercises</th>
                            <th>Exams</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($teachers as $teacher)
                            <tr>
                                <td>
                                    <strong>{{ $teacher->full_name }}</strong>
                                    <div class="small text-muted">{{ $teacher->portal_id }} / {{ ucwords(str_replace('_', ' ', $teacher->role)) }}</div>
                                </td>
                                <td>
                                    @forelse($teacher->teachingSubjects->take(4) as $subject)
                                        <span class="badge bg-light text-dark mb-1">{{ $subject->name }}</span>
                                    @empty
                                        <span class="text-muted">No subjects</span>
                                    @endforelse
                                </td>
                                <td>
                                    @forelse($teacher->assignedClasses->take(4) as $class)
                                        <span class="badge bg-light text-dark mb-1">{{ $class->full_name }}</span>
                                    @empty
                                        <span class="text-muted">No assigned classes</span>
                                    @endforelse
                                </td>
                                <td>{{ $teacher->lesson_notes_count }}</td>
                                <td>{{ $teacher->pending_lesson_notes_count }}</td>
                                <td>{{ $teacher->approved_lesson_notes_count }}</td>
                                <td>{{ $teacher->returned_lesson_notes_count }}</td>
                                <td><span class="badge {{ ($unmarkedCounts[$teacher->id] ?? 0) > 0 ? 'bg-warning text-dark' : 'bg-light text-dark' }}">{{ $unmarkedCounts[$teacher->id] ?? 0 }}</span></td>
                                <td>{{ $teacher->created_exams_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-muted">No staff found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $teachers->links() }}
        </div>
    </div>
</div>
@endsection
