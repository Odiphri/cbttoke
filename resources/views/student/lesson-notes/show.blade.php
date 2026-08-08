@extends('layouts.admin')

@section('title', 'Read Lesson Note')

@section('content')
<div class="d-flex justify-content-end mb-3">
    @include('lesson-notes.partials.subject-export-buttons', ['note' => $note, 'routeName' => (request()->routeIs('prefect.*') ? 'prefect' : 'student').'.lesson-notes.exports.subject'])
</div>

<div class="card mb-3"><div class="card-body">
    <p class="text-muted">{{ $note->academicSession?->display_name }} / Week {{ $note->week_number }} / {{ $note->subject?->name }}</p>
    <h2 class="h4">{{ $note->topic }}</h2>@if($note->subtopic)<p>{{ $note->subtopic }}</p>@endif
    <p class="text-muted">Teacher: {{ $note->teacher?->full_name }}</p>
    @if($note->learning_objectives)<h6>Learning Objectives</h6><div>{!! $note->learning_objectives !!}</div>@endif
    <div class="lesson-reader">{!! $note->main_content !!}</div>
    @foreach(['previous_knowledge'=>'Previous Knowledge','teaching_materials'=>'Teaching Materials','introduction'=>'Introduction','evaluation'=>'Evaluation','conclusion'=>'Conclusion','assignment'=>'Assignment'] as $field => $label)
        @if($note->$field)<h6 class="mt-3">{{ $label }}</h6><div>{!! $note->$field !!}</div>@endif
    @endforeach
    @if($note->attachments->isNotEmpty())<h6>Attachments</h6>@foreach($note->attachments as $attachment)<a class="btn btn-sm btn-outline-primary me-1 mb-1" href="{{ asset('storage/'.$attachment->stored_path) }}">{{ $attachment->original_filename }}</a>@endforeach @endif
</div></div>
@if($note->exercise)
<div class="card"><div class="card-body"><h5>{{ $note->exercise->title }}</h5><p>{{ $note->exercise->instructions }}</p><a class="btn btn-primary-custom" href="{{ route('student.exercises.show', $note->exercise) }}">Open Exercise</a></div></div>
@endif
@endsection
