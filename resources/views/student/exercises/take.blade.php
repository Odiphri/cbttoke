@extends('layouts.admin')

@section('title', 'Take Exercise')

@section('content')
<form method="POST" action="{{ route('student.exercises.submit', [$lessonExercise, $attempt]) }}">
@csrf
<div class="card mb-3"><div class="card-body"><h4>{{ $lessonExercise->title }}</h4>@if($lessonExercise->instructions)<div>{!! $lessonExercise->instructions !!}</div>@endif<p class="text-muted">Attempt {{ $attempt->attempt_number }} of {{ $lessonExercise->allowedAttemptsFor(Auth::user()) ?? 'Unlimited' }}</p></div></div>
@foreach($questions as $question)
@php $saved = $attempt->answers->firstWhere('exercise_question_id', $question->id)?->answer_text; @endphp
<div class="card mb-3"><div class="card-body">
    <div class="mb-2">{!! $question->question_text !!}</div>
    @if($question->image_path)<img class="img-fluid mb-2" src="{{ asset('storage/'.$question->image_path) }}" alt="">@endif
    @if($question->question_type === 'objective')
        @foreach(($question->options ?? []) as $key => $option)<div class="form-check"><input class="form-check-input" type="radio" name="answers[{{ $question->id }}]" value="{{ $key }}" @checked($saved === $key)><label class="form-check-label">{{ $key }}. {!! $option !!}</label></div>@endforeach
    @elseif($question->question_type === 'true_false')
        @foreach(['true'=>'True','false'=>'False'] as $value => $label)<div class="form-check"><input class="form-check-input" type="radio" name="answers[{{ $question->id }}]" value="{{ $value }}" @checked($saved === $value)><label class="form-check-label">{{ $label }}</label></div>@endforeach
    @else
        <textarea class="form-control" name="answers[{{ $question->id }}]" rows="5">{{ $saved }}</textarea>
    @endif
</div></div>
@endforeach
<div class="d-flex gap-2 flex-wrap">
    <button class="btn btn-primary-custom" formaction="{{ route('student.exercises.submit', [$lessonExercise, $attempt]) }}" onclick="return confirm('Submit this exercise now? You cannot edit it after final submission.')">Final Submit</button>
    <button class="btn btn-outline-secondary" formaction="{{ route('student.exercises.save', [$lessonExercise, $attempt]) }}">Save and continue later</button>
</div>
</form>
@endsection
