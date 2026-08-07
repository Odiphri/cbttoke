@extends('layouts.admin')

@section('title', 'Lesson Notes')

@section('content')
<div class="mb-3"><h2 class="h4">Lesson Notes</h2><p class="text-muted">{{ $activeSession?->display_name }}</p></div>
<form class="card p-3 mb-3" method="GET"><div class="row g-2"><div class="col-md-3"><select class="form-select" name="week"><option value="">All weeks</option>@for($i=1;$i<=15;$i++)<option value="{{ $i }}" @selected(request('week') == $i)>Week {{ $i }}</option>@endfor</select></div><div class="col-md-3"><select class="form-select" name="subject_id"><option value="">All subjects</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}" @selected(request('subject_id') == $subject->id)>{{ $subject->name }}</option>@endforeach</select></div><div class="col-md-4"><input class="form-control" name="search" placeholder="Search topic" value="{{ request('search') }}"></div><div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div></div></form>
@forelse($notesByWeek as $week => $notes)
<h5>Week {{ $week }}</h5>
<div class="row g-3 mb-3">@foreach($notes as $note)<div class="col-md-6"><div class="card h-100"><div class="card-body"><div class="d-flex justify-content-between"><span class="badge bg-primary">{{ $note->subject?->name }}</span><span class="text-muted small">Week {{ $note->week_number }}</span></div><h5 class="mt-2">{{ $note->topic }}</h5><p class="text-muted">{{ $note->teacher?->full_name }} / Published {{ optional($note->published_at)->diffForHumans() }}</p><p>{{ $note->exercise ? 'Exercise available' : 'No exercise' }}</p><a class="btn btn-primary-custom btn-sm" href="{{ route('student.lesson-notes.show', $note) }}">Read Note</a></div></div></div>@endforeach</div>
@empty
<div class="alert alert-info">No approved lesson notes are available for your class yet.</div>
@endforelse
@endsection
