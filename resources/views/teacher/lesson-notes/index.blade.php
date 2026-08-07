@extends('layouts.admin')

@section('title', 'Lesson Notes')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h2 class="h4 mb-1">Lesson Notes</h2>
        <p class="text-muted mb-0">Draft, submit and track approval for your class notes.</p>
    </div>
    <a href="{{ route('teacher.lesson-notes.create') }}" class="btn btn-primary-custom"><i class="fas fa-plus me-1"></i> Create Lesson Note</a>
</div>

<div class="row g-3 mb-3">
    @foreach(['drafts' => 'Drafts', 'pending' => 'Pending Approval', 'approved' => 'Approved', 'returned' => 'Returned/Rejected', 'awaiting_marking' => 'Awaiting Marking'] as $key => $label)
    <div class="col-6 col-md">
        <div class="card h-100"><div class="card-body"><div class="stat-number">{{ $summary[$key] }}</div><div class="stat-label">{{ $label }}</div></div></div>
    </div>
    @endforeach
</div>

<form method="GET" class="card p-3 mb-3">
    <div class="row g-2">
        <div class="col-md-2"><input class="form-control" name="search" placeholder="Search" value="{{ request('search') }}"></div>
        <div class="col-md-2"><select class="form-select" name="week"><option value="">All weeks</option>@for($i=1;$i<=15;$i++)<option value="{{ $i }}" @selected(request('week') == $i)>Week {{ $i }}</option>@endfor</select></div>
        <div class="col-md-2"><select class="form-select" name="status"><option value="">All status</option>@foreach(['draft','pending','approved','returned','rejected','archived'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
        <div class="col-md-3"><select class="form-select" name="school_class_id"><option value="">All classes</option>@foreach($classes as $class)<option value="{{ $class->id }}" @selected(request('school_class_id') == $class->id)>{{ $class->full_name }}</option>@endforeach</select></div>
        <div class="col-md-3"><select class="form-select" name="subject_id"><option value="">All subjects</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}" @selected(request('subject_id') == $subject->id)>{{ $subject->name }}</option>@endforeach</select></div>
        <div class="col-12"><button class="btn btn-outline-primary btn-sm"><i class="fas fa-filter me-1"></i> Filter</button></div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead><tr><th>Title / Topic</th><th>Session</th><th>Week</th><th>Class</th><th>Subject</th><th>Exercise</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse($notes as $note)
                <tr>
                    <td><strong>{{ $note->title }}</strong><br><span class="text-muted">{{ $note->topic }}</span></td>
                    <td>{{ $note->academicSession?->display_name }}</td>
                    <td>Week {{ $note->week_number }}</td>
                    <td>{{ $note->schoolClass?->full_name }}</td>
                    <td>{{ $note->subject?->name }}</td>
                    <td>{{ $note->exercise ? $note->exercise->questions->count().' questions' : 'None' }}</td>
                    <td><span class="badge {{ $note->statusBadgeClass() }}">{{ $note->statusLabel() }}</span></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('teacher.lesson-notes.show', $note) }}"><i class="fas fa-eye"></i></a>
                        @if($note->isEditable())<a class="btn btn-sm btn-outline-secondary" href="{{ route('teacher.lesson-notes.edit', $note) }}"><i class="fas fa-edit"></i></a>@endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No lesson notes yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $notes->links() }}
@endsection
