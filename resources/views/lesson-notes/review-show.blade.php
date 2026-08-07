@extends('layouts.admin')

@section('title', 'Review Lesson Note')

@section('content')
<div class="card mb-3"><div class="card-header d-flex justify-content-between flex-wrap gap-2"><span>{{ $note->title }}</span><span class="badge {{ $note->statusBadgeClass() }}">{{ $note->statusLabel() }}</span></div><div class="card-body">
    <p class="text-muted">{{ $note->teacher?->full_name }} / {{ $note->academicSession?->display_name }} / Week {{ $note->week_number }} / {{ $note->schoolClass?->full_name }} / {{ $note->subject?->name }}</p>
    <h5>{{ $note->topic }}</h5>
    <div>{!! $note->main_content !!}</div>
    @foreach(['previous_knowledge'=>'Previous Knowledge','learning_objectives'=>'Learning Objectives','teaching_materials'=>'Teaching Materials','introduction'=>'Introduction','evaluation'=>'Evaluation','conclusion'=>'Conclusion','assignment'=>'Assignment'] as $field => $label)
        @if($note->$field)<h6 class="mt-3">{{ $label }}</h6><div>{!! $note->$field !!}</div>@endif
    @endforeach
    @if($note->attachments->isNotEmpty())<h6>Attachments</h6>@foreach($note->attachments as $attachment)<a class="btn btn-sm btn-outline-primary me-1" href="{{ asset('storage/'.$attachment->stored_path) }}">{{ $attachment->original_filename }}</a>@endforeach @endif
</div></div>

<div class="card mb-3"><div class="card-header">Exercise Content</div><div class="card-body">
@if($note->exercise)
<h5>{{ $note->exercise->title }}</h5><p>{{ $note->exercise->instructions }}</p>
@foreach($note->exercise->questions as $question)<div class="border rounded p-2 mb-2"><span class="badge bg-secondary">{{ str_replace('_',' ', $question->question_type) }}</span> {!! $question->question_text !!}<div class="text-muted">{{ $question->marks }} marks</div>@if($question->marking_guide)<div class="small">Guide: {{ $question->marking_guide }}</div>@endif</div>@endforeach
@else
<p class="text-muted">No exercise attached.</p>
@endif
</div></div>

<div class="card mb-3"><div class="card-header">Review History</div><div class="card-body">@forelse($note->reviews as $review)<p><strong>{{ ucfirst($review->action) }}</strong> by {{ $review->reviewer?->full_name }} on {{ $review->reviewed_at }}<br>{{ $review->comments }}</p>@empty<p class="text-muted">No previous reviews.</p>@endforelse</div></div>

@if($note->status === 'pending')
<div class="card"><div class="card-body d-flex gap-2 flex-wrap">
    <form method="POST" action="{{ route($routePrefix.'.lesson-notes.approve', $note) }}">@csrf<button class="btn btn-success"><i class="fas fa-check me-1"></i> Approve</button></form>
    <form method="POST" action="{{ route($routePrefix.'.lesson-notes.return', $note) }}" class="d-flex gap-2 flex-wrap">@csrf<input class="form-control" name="comments" placeholder="Reason for return" required><button class="btn btn-warning">Return for Correction</button></form>
    <form method="POST" action="{{ route($routePrefix.'.lesson-notes.reject', $note) }}" class="d-flex gap-2 flex-wrap">@csrf<input class="form-control" name="comments" placeholder="Reason for rejection" required><button class="btn btn-danger">Reject</button></form>
</div></div>
@elseif($note->status === 'approved')
<div class="card"><div class="card-body d-flex gap-2 flex-wrap">
    <form method="POST" action="{{ route($routePrefix.'.lesson-notes.return', $note) }}" class="d-flex gap-2 flex-wrap">@csrf<input class="form-control" name="comments" placeholder="Reason for return" required><button class="btn btn-warning">Return for Correction</button></form>
    <form method="POST" action="{{ route($routePrefix.'.lesson-notes.archive', $note) }}">@csrf<button class="btn btn-outline-danger">Archive</button></form>
    <form method="POST" action="{{ route($routePrefix.'.lesson-notes.destroy', $note) }}" onsubmit="return confirm('Permanently delete this approved lesson note?')">@csrf @method('DELETE')<button class="btn btn-danger">Delete</button></form>
</div></div>
@elseif($note->status === 'returned')
<div class="card"><div class="card-body d-flex gap-2 flex-wrap">
    <form method="POST" action="{{ route($routePrefix.'.lesson-notes.approve', $note) }}">@csrf<button class="btn btn-success"><i class="fas fa-check me-1"></i> Accept / Approve</button></form>
    <form method="POST" action="{{ route($routePrefix.'.lesson-notes.reject', $note) }}" class="d-flex gap-2 flex-wrap">@csrf<input class="form-control" name="comments" placeholder="Reason for rejection" required><button class="btn btn-danger">Reject</button></form>
</div></div>
@endif
@endsection
