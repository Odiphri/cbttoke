@extends('layouts.admin')

@section('title', 'Exercises')

@section('content')
<div class="row g-3">
@forelse($exercises as $exercise)
@php $attempts = $exercise->attempts; $latest = $attempts->sortByDesc('attempt_number')->first(); $allowed = $exercise->allowedAttemptsFor(Auth::user()); @endphp
<div class="col-md-6"><div class="card h-100"><div class="card-body">
    <div class="d-flex justify-content-between flex-wrap"><span class="badge bg-primary">{{ $exercise->lessonNote?->subject?->name }}</span><span class="text-muted small">Week {{ $exercise->lessonNote?->week_number }}</span></div>
    <h5 class="mt-2">{{ $exercise->title }}</h5>
    <p class="text-muted">{{ $exercise->lessonNote?->topic }}</p>
    <p>Deadline: {{ $exercise->due_at ?: 'No deadline' }}<br>Attempts: {{ $exercise->attemptsUsedBy(Auth::user()) }} of {{ $allowed ?? 'Unlimited' }}</p>
    @if($latest && $latest->status !== 'in_progress')<p>Status: {{ str_replace('_',' ', $latest->status) }} @if($exercise->show_score_immediately || $latest->status === 'marked') / Score: {{ $latest->total_score }} @endif</p>@endif
    <a class="btn btn-primary-custom btn-sm" href="{{ route('student.exercises.show', $exercise) }}">{{ $latest?->status === 'in_progress' ? 'Continue' : 'Open' }}</a>
</div></div></div>
@empty
<div class="col-12"><div class="alert alert-info">No exercises are available.</div></div>
@endforelse
</div>
@endsection
