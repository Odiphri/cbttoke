@extends('layouts.admin')

@section('title', 'Teacher Dashboard')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Teacher Dashboard</h5>
                </div>
                <div class="card-body">
                    <h6>Welcome, {{ Auth::user()->full_name }}</h6>
                    <p class="text-muted">Teacher Dashboard</p>
                    
                    <div class="row mt-4">
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3>{{ $lessonNotesCount }}</h3>
                                    <p>Lesson Notes</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3>{{ $pendingLessonNotesCount }}</h3>
                                    <p>Pending Approval</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3>{{ $returnedLessonNotesCount }}</h3>
                                    <p>Returned Notes</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3>{{ $exerciseMarkingCount }}</h3>
                                    <p>Awaiting Marking</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3>{{ $myExamsCount }}</h3>
                                    <p>My Exams</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3>{{ $liveExamsCount }}</h3>
                                    <p>Live Exams</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3>{{ $attendanceMarkedCount }}</h3>
                                    <p>Attendance Marked</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
