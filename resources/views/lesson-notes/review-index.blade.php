@extends('layouts.admin')

@section('title', 'Lesson Note Review Centre')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h2 class="h4 mb-0">Lesson Note Review Centre</h2>
        <p class="text-muted mb-0">Review, return, approve and archive lesson notes.</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route($routePrefix.'.lesson-notes.archives') }}">
        <i class="fas fa-archive me-1"></i> Archived Notes
    </a>
</div>

<form method="GET" class="card p-3 mb-3">
    <div class="row g-2">
        <div class="col-md-3"><select class="form-select" name="status"><option value="">All notes</option>@foreach(['pending'=>'Pending Approval','approved'=>'Approved','returned'=>'Returned','rejected'=>'Rejected','archived'=>'Archived'] as $value => $label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-3"><select class="form-select" name="teacher_id"><option value="">All teachers</option>@foreach($teachers as $teacher)<option value="{{ $teacher->id }}" @selected(request('teacher_id') == $teacher->id)>{{ $teacher->full_name }}</option>@endforeach</select></div>
        <div class="col-md-2"><select class="form-select" name="week"><option value="">All weeks</option>@for($i=1;$i<=15;$i++)<option value="{{ $i }}" @selected(request('week') == $i)>Week {{ $i }}</option>@endfor</select></div>
        <div class="col-md-2"><button class="btn btn-outline-primary"><i class="fas fa-filter me-1"></i> Filter</button></div>
    </div>
</form>

<ul class="nav nav-tabs mb-3">
    @foreach(['pending'=>'Pending Approval','approved'=>'Approved','returned'=>'Returned/Rejected',''=>'All Notes'] as $status => $label)
    <li class="nav-item"><a class="nav-link {{ request('status') === $status ? 'active' : '' }}" href="{{ route($routePrefix.'.lesson-notes.index', array_filter(['status' => $status])) }}">{{ $label }}</a></li>
    @endforeach
</ul>

<div class="card"><div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><th>Note</th><th>Teacher</th><th>Class</th><th>Subject</th><th>Week</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($notes as $note)
<tr><td><strong>{{ $note->title }}</strong><br><span class="text-muted">{{ $note->topic }}</span></td><td>{{ $note->teacher?->full_name }}</td><td>{{ $note->schoolClass?->full_name }}</td><td>{{ $note->subject?->name }}</td><td>Week {{ $note->week_number }}</td><td><span class="badge {{ $note->statusBadgeClass() }}">{{ $note->statusLabel() }}</span></td><td><a class="btn btn-sm btn-primary-custom" href="{{ route($routePrefix.'.lesson-notes.show', $note) }}">Review</a></td></tr>
@empty
<tr><td colspan="7" class="text-center text-muted py-4">No lesson notes found.</td></tr>
@endforelse
</tbody></table></div></div>
{{ $notes->links() }}
@endsection
