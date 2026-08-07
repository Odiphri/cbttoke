@extends('layouts.admin')

@section('title', 'Archived Lesson Notes')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h2 class="h4 mb-0">Archived Lesson Notes</h2>
        <p class="text-muted mb-0">Restore useful notes or permanently remove old records.</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route($routePrefix.'.lesson-notes.index') }}">
        <i class="fas fa-arrow-left me-1"></i> Review Centre
    </a>
</div>

<form method="GET" class="card p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label">Teacher</label>
            <select class="form-select" name="teacher_id">
                <option value="">All teachers</option>
                @foreach($teachers as $teacher)
                    <option value="{{ $teacher->id }}" @selected(request('teacher_id') == $teacher->id)>{{ $teacher->full_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <button class="btn btn-outline-primary"><i class="fas fa-filter me-1"></i> Filter</button>
        </div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th>Lesson Note</th>
                    <th>Teacher</th>
                    <th>Class</th>
                    <th>Subject</th>
                    <th>Archived</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($notes as $note)
                <tr>
                    <td><strong>{{ $note->title }}</strong><br><span class="text-muted">{{ $note->topic }}</span></td>
                    <td>{{ $note->teacher?->full_name }}</td>
                    <td>{{ $note->schoolClass?->full_name }}</td>
                    <td>{{ $note->subject?->name }}</td>
                    <td>{{ optional($note->reviews->firstWhere('action', 'archived')?->reviewed_at)->diffForHumans() ?? $note->updated_at->diffForHumans() }}</td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route($routePrefix.'.lesson-notes.show', $note) }}">View</a>
                        <form class="d-inline" method="POST" action="{{ route($routePrefix.'.lesson-notes.restore', $note) }}">
                            @csrf
                            <button class="btn btn-sm btn-success">Restore</button>
                        </form>
                        <form class="d-inline" method="POST" action="{{ route($routePrefix.'.lesson-notes.destroy', $note) }}" onsubmit="return confirm('Permanently delete this archived note?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No archived lesson notes yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $notes->links() }}
@endsection
