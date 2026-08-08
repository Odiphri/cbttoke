@extends('layouts.admin')

@section('title', 'Admin Command Center')

@section('content')
<div class="container-fluid">
    <div class="row g-3 mb-4">
        @foreach($needsAttention as $item)
            <div class="col-6 col-xl-2">
                <a class="card h-100 text-decoration-none text-reset action-card" href="{{ $item['url'] }}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="stat-number">{{ $item['value'] }}</div>
                                <div class="stat-label">{{ $item['label'] }}</div>
                            </div>
                            <span class="action-icon"><i class="fas {{ $item['icon'] }}"></i></span>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-6">
            <div class="card h-100"><div class="card-body"><div class="stat-number">{{ $stats['total_students'] }}</div><div class="stat-label">Students</div></div></div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="card h-100"><div class="card-body"><div class="stat-number">{{ $stats['total_teachers'] }}</div><div class="stat-label">Teachers</div></div></div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="card h-100"><div class="card-body"><div class="stat-number">{{ round($paymentStats['collection_rate'], 0) }}%</div><div class="stat-label">Payment Rate</div></div></div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="card h-100"><div class="card-body"><div class="stat-number">{{ $academicHealth['exam_average'] }}%</div><div class="stat-label">Exam Average</div></div></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Live Activity</span>
                    <a class="btn btn-sm btn-light" href="{{ route('traffic.index') }}">Traffic</a>
                </div>
                <div class="card-body">
                    @forelse($recentActivity as $activity)
                        <a class="activity-row" href="{{ $activity['url'] }}">
                            <div>
                                <div class="fw-semibold">{{ $activity['title'] }}</div>
                                <div class="small text-muted">{{ $activity['meta'] }}</div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-light text-dark">{{ $activity['badge'] }}</span>
                                <div class="small text-muted mt-1">{{ $activity['time']->diffForHumans() }}</div>
                            </div>
                        </a>
                    @empty
                        <div class="text-muted py-3">No recent school activity yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Teacher Workload</span>
                    <a class="btn btn-sm btn-light" href="{{ route('admin.teacher-workload') }}">Open</a>
                </div>
                <div class="card-body">
                    @forelse($teacherHighlights as $teacher)
                        <div class="workload-row">
                            <div>
                                <div class="fw-semibold">{{ $teacher->full_name }}</div>
                                <div class="small text-muted">{{ ucwords(str_replace('_', ' ', $teacher->role)) }}</div>
                            </div>
                            <div class="text-end small">
                                <div>{{ $teacher->pending_lesson_notes_count }} pending</div>
                                <div class="text-muted">{{ $teacher->returned_lesson_notes_count }} returned</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted py-3">No staff records found.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-3">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Academic Health</span>
                    <a class="btn btn-sm btn-light" href="{{ route('admin.reports') }}">Reports</a>
                </div>
                <div class="card-body">
                    <div class="health-box mb-3">
                        <span>Exam attempts</span>
                        <strong>{{ $academicHealth['submitted_exam_attempts'] }}</strong>
                    </div>
                    <div class="health-box mb-3">
                        <span>Marked exercises</span>
                        <strong>{{ $academicHealth['marked_exercise_attempts'] }}</strong>
                    </div>
                    <div class="small text-muted text-uppercase mb-2">Classes to watch</div>
                    @forelse($academicHealth['weak_classes'] as $row)
                        <div class="d-flex justify-content-between gap-2 small mb-2">
                            <span>{{ $row['class']->full_name }}</span>
                            <strong>{{ $row['exam_average'] === null ? 'No exams' : $row['exam_average'].'%' }}</strong>
                        </div>
                    @empty
                        <div class="text-muted small">No class performance yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Low Exercise Performance</span>
                    <a class="btn btn-sm btn-light" href="{{ route('admin.exercises') }}">Exercises</a>
                </div>
                <div class="card-body">
                    @forelse($academicHealth['low_exercise_performance'] as $row)
                        <div class="health-row">
                            <div>
                                <div class="fw-semibold">{{ $row['subject']?->name ?? 'Subject' }}</div>
                                <div class="small text-muted">{{ $row['class']?->full_name ?? 'Class' }} / {{ $row['attempts'] }} attempt(s)</div>
                            </div>
                            <span class="badge {{ $row['average'] < 50 ? 'bg-danger' : 'bg-warning text-dark' }}">{{ $row['average'] }}%</span>
                        </div>
                    @empty
                        <div class="text-muted py-3">No marked exercise performance yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Students Awaiting Marking</span>
                    <a class="btn btn-sm btn-light" href="{{ route('admin.exercises', ['status' => 'awaiting_marking']) }}">Open</a>
                </div>
                <div class="card-body">
                    @forelse($academicHealth['students_awaiting_marking'] as $attempt)
                        <div class="health-row">
                            <div>
                                <div class="fw-semibold">{{ $attempt->student?->full_name ?? 'Student' }}</div>
                                <div class="small text-muted">{{ $attempt->exercise?->title }} / {{ $attempt->exercise?->lessonNote?->subject?->name }}</div>
                            </div>
                            <div class="text-end small">
                                <div>{{ $attempt->student?->assignedClass?->full_name ?? 'Class' }}</div>
                                <div class="text-muted">{{ $attempt->submitted_at?->diffForHumans() ?? 'Submitted' }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted py-3">No student is awaiting exercise marking.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Subjects Missing Lesson Notes</span>
                    <a class="btn btn-sm btn-light" href="{{ route('admin.lesson-note-coverage') }}">Coverage</a>
                </div>
                <div class="card-body">
                    @forelse($academicHealth['subjects_missing_lesson_notes'] as $row)
                        <div class="health-row">
                            <div>
                                <div class="fw-semibold">{{ $row['subject'] }}</div>
                                <div class="small text-muted">{{ $row['class'] }} / {{ $row['teacher'] }}</div>
                            </div>
                            <span class="badge bg-warning text-dark">{{ count($row['missing_weeks']) }} missing</span>
                        </div>
                    @empty
                        <div class="text-muted py-3">No missing lesson-note subjects for the current range.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">Recent Users</div>
                <div class="card-body">
                    @forelse($recentUsers as $user)
                        <div class="workload-row">
                            <div>
                                <div class="fw-semibold">{{ $user->full_name }}</div>
                                <div class="small text-muted">{{ $user->email ?: $user->portal_id }}</div>
                            </div>
                            <span class="badge bg-secondary">{{ $user->role }}</span>
                        </div>
                    @empty
                        <div class="text-muted">No recent users.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">Recent Exams</div>
                <div class="card-body">
                    @forelse($recentExams as $exam)
                        <a class="activity-row" href="{{ route('admin.exams.show', $exam) }}">
                            <div>
                                <div class="fw-semibold">{{ $exam->title }}</div>
                                <div class="small text-muted">{{ $exam->subject->name ?? 'No subject' }} / {{ $exam->schoolClass->full_name ?? 'No class' }}</div>
                            </div>
                            <span class="badge {{ $exam->is_live ? 'bg-success' : 'bg-secondary' }}">{{ $exam->is_live ? 'Live' : 'Offline' }}</span>
                        </a>
                    @empty
                        <div class="text-muted">No recent exams.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">Pending Requests</div>
                <div class="card-body">
                    @forelse($pendingRequests as $request)
                        <div class="workload-row">
                            <div>
                                <div class="fw-semibold">{{ ucwords(str_replace('_', ' ', $request->request_type)) }}</div>
                                <div class="small text-muted">{{ $request->student->full_name ?? 'Student' }}</div>
                            </div>
                            <span class="badge bg-warning text-dark">Pending</span>
                        </div>
                    @empty
                        <div class="text-muted">No pending requests.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.action-card {
    border: 1px solid #e7edf4;
    transition: transform .15s ease, box-shadow .15s ease;
}
.action-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(10, 25, 49, .12);
}
.action-icon {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #0a1931;
    background: #eef3f8;
}
.activity-row,
.workload-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid #edf1f5;
}
.activity-row {
    color: inherit;
    text-decoration: none;
}
.activity-row:last-child,
.workload-row:last-child {
    border-bottom: 0;
}
.health-box {
    border: 1px solid #edf1f5;
    border-radius: 8px;
    padding: 12px;
    display: flex;
    justify-content: space-between;
    gap: 10px;
}
.health-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid #edf1f5;
}
.health-row:last-child {
    border-bottom: 0;
}
</style>
@endsection
