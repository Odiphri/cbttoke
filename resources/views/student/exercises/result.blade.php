@extends('layouts.admin')

@section('title', 'Exercise Result')

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <h4>{{ $lessonExercise->title }}</h4>
        <p>Status: {{ str_replace('_', ' ', $attempt->status) }}</p>

        @if($lessonExercise->show_score_immediately || $attempt->status === 'marked')
            <h5>Score: {{ $attempt->total_score }} / {{ $lessonExercise->totalMarks() }}</h5>
        @else
            <p class="text-muted">Your score will be available after review.</p>
        @endif

        @if($attempt->overall_feedback)
            <div>{!! $attempt->overall_feedback !!}</div>
        @endif
    </div>
</div>

@foreach($attempt->answers as $answer)
    <div class="card mb-3">
        <div class="card-body">
            <div class="mb-2">{!! $answer->question?->question_text !!}</div>
            <p><strong>Your answer:</strong> {{ $answer->answer_text }}</p>

            @if($lessonExercise->reveal_correct_answers && $answer->question?->question_type !== 'theory')
                <p><strong>Correct answer:</strong> {{ $answer->question->correct_answer }}</p>
            @endif

            @if($answer->teacher_feedback)
                <div>{!! $answer->teacher_feedback !!}</div>
            @endif
        </div>
    </div>
@endforeach
@endsection
