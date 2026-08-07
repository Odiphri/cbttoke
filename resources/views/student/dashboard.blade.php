@extends('layouts.admin')

@section('title', 'Student Dashboard')

@section('content')
<div class="container-fluid">
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Welcome, {{ Auth::user()->full_name }}</h5>
                    <small class="text-muted d-block d-md-none">{{ Auth::user()->schoolClass->full_name ?? 'No Class Assigned' }}</small>
                </div>
                <div class="card-body text-center py-3">
                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <div class="card bg-primary text-white h-100">
                                <div class="card-body d-flex flex-column justify-content-center">
                                    <div class="stat-number">{{ $examStats['total'] }}</div>
                                    <div class="stat-label">Available Exams</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card bg-success text-white h-100">
                                <div class="card-body d-flex flex-column justify-content-center">
                                    <div class="stat-number">{{ \App\Models\ExamAttempt::where('student_id', Auth::id())->count() }}</div>
                                    <div class="stat-label">Exam Attempts</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card bg-info text-white h-100">
                                <div class="card-body d-flex flex-column justify-content-center">
                                    <div class="stat-number">{{ \App\Models\Payment::where('student_id', Auth::id())->where('status', 'paid')->count() }}</div>
                                    <div class="stat-label">Paid Fees</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card bg-warning text-white h-100">
                                <div class="card-body d-flex flex-column justify-content-center">
                                    <div class="stat-number">{{ \App\Models\Attendance::where('student_id', Auth::id())->where('status', 'present')->count() }}</div>
                                    <div class="stat-label">Present Days</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card bg-primary text-white h-100">
                                <div class="card-body d-flex flex-column justify-content-center">
                                    <div class="stat-number">{{ $lessonStats['new_notes'] ?? 0 }}</div>
                                    <div class="stat-label">New Lesson Notes</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card bg-info text-white h-100">
                                <div class="card-body d-flex flex-column justify-content-center">
                                    <div class="stat-number">{{ $lessonStats['exercises_due'] ?? 0 }}</div>
                                    <div class="stat-label">Exercises Due</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card bg-secondary text-white h-100">
                                <div class="card-body d-flex flex-column justify-content-center">
                                    <div class="stat-number">{{ $lessonStats['awaiting_marking'] ?? 0 }}</div>
                                    <div class="stat-label">Awaiting Marking</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row g-3">
        <div class="col-12 col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
                        <a href="{{ route('student.exams') }}" class="btn btn-outline-primary w-100">
                            <i class="fas fa-clipboard-list me-2"></i>
                            <span class="d-none d-md-inline">View Exams</span>
                            <span class="d-md-none">Exams</span>
                        </a>
                        <a href="{{ route('student.lesson-notes.index') }}" class="btn btn-outline-primary w-100">
                            <i class="fas fa-book-open me-2"></i>
                            <span>Lesson Notes</span>
                        </a>
                        <a href="{{ route('student.exercises.index') }}" class="btn btn-outline-primary w-100">
                            <i class="fas fa-tasks me-2"></i>
                            <span>Exercises</span>
                        </a>
                        <a href="{{ route('student.payments') }}" class="btn btn-outline-success w-100">
                            <i class="fas fa-money-bill-wave me-2"></i>
                            <span class="d-none d-md-inline">View Payments</span>
                            <span class="d-md-none">Payments</span>
                        </a>
                        <a href="{{ route('student.attendance') }}" class="btn btn-outline-info w-100">
                            <i class="fas fa-calendar-check me-2"></i>
                            <span class="d-none d-md-inline">View Attendance</span>
                            <span class="d-md-none">Attendance</span>
                        </a>
                        <a href="{{ route('student.profile.edit') }}" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-user me-2"></i>
                            <span class="d-none d-md-inline">Edit Profile</span>
                            <span class="d-md-none">Profile</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0">Recent Activity</h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        @if($recentExams->count() > 0)
                            @foreach($recentExams->take(3) as $exam)
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold text-truncate">{{ $exam->title }}</div>
                                    <small class="text-muted d-block">{{ $exam->created_at->format('M d, Y') }}</small>
                                </div>
                                <span class="badge bg-success">Live</span>
                            </div>
                            @endforeach
                        @else
                            <div class="text-center py-3">
                                <i class="fas fa-history fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">No recent activity</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
@endsection
