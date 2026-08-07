@extends('layouts.admin')

@section('title', 'HOD Dashboard')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">HOD Dashboard</h5>
                </div>
                <div class="card-body">
                    <h6>Welcome, {{ Auth::user()->full_name }}</h6>
                    <p class="text-muted">Head of Department Dashboard</p>
                    
                    <div class="row mt-4">
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3>{{ $pendingLessonNotesCount }}</h3>
                                    <p>Lesson Notes Awaiting Approval</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3>{{ $approvedLessonNotesThisTermCount }}</h3>
                                    <p>Approved This Term</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3>{{ $returnedLessonNotesThisTermCount }}</h3>
                                    <p>Returned / Rejected</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3>{{ \App\Models\User::where('role', 'student')->count() }}</h3>
                                    <p>Total Students</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3>{{ \App\Models\Exam::count() }}</h3>
                                    <p>Total Exams</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3>{{ \App\Models\Override::where('is_active', true)->count() }}</h3>
                                    <p>Active Overrides</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h3>{{ \App\Models\ChangeRequest::where('status', 'pending')->count() }}</h3>
                                    <p>Pending Requests</p>
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
