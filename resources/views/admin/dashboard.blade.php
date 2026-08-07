@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('content')
<div class="container-fluid">
    <!-- Statistics Cards -->
    <div class="row g-3 g-md-4 mb-4">
        <div class="col-6 col-md-3">
            <div class="card text-center bg-light h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <div class="stat-number">{{ $stats['pending_lesson_notes'] }}</div>
                    <div class="stat-label">Pending Lesson Notes</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center bg-light h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <div class="stat-number">{{ $stats['approved_lesson_notes'] }}</div>
                    <div class="stat-label">Approved Lesson Notes</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center bg-primary text-white h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <div class="stat-number">{{ $stats['total_users'] }}</div>
                    <div class="stat-label">Total Users</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center bg-success text-white h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <div class="stat-number">{{ $stats['total_classes'] }}</div>
                    <div class="stat-label">Classes</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center bg-info text-white h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <div class="stat-number">{{ $stats['total_exams'] }}</div>
                    <div class="stat-label">Exams</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center bg-warning text-white h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <div class="stat-number">{{ round($paymentStats['collection_rate'], 0) }}%</div>
                    <div class="stat-label">Payment Rate</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row g-3 g-md-4">
        <div class="col-12 col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0">Recent Users</h6>
                </div>
                <div class="card-body">
                    @if($recentUsers->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach($recentUsers as $user)
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold">{{ $user->full_name }}</div>
                                    <small class="text-muted">{{ $user->email ?? ucwords(str_replace('_', ' ', $user->role)) }}</small>
                                </div>
                                <span class="badge bg-secondary">{{ $user->role }}</span>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-3">
                            <i class="fas fa-users fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">No recent users</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0">Recent Exams</h6>
                </div>
                <div class="card-body">
                    @if($recentExams->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach($recentExams as $exam)
                            <div class="list-group-item">
                                <div class="fw-bold text-truncate">{{ $exam->title }}</div>
                                <small class="text-muted d-block">
                                    {{ $exam->subject->name ?? 'No Subject' }} • 
                                    {{ $exam->schoolClass->full_name ?? 'No Class' }}
                                </small>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-3">
                            <i class="fas fa-clipboard-list fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">No recent exams</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0">Pending Requests</h6>
                </div>
                <div class="card-body">
                    @if($pendingRequests->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach($pendingRequests as $request)
                            <div class="list-group-item">
                                <div class="fw-bold text-truncate">{{ ucwords(str_replace('_', ' ', $request->request_type)) }}</div>
                                <small class="text-muted d-block">{{ $request->student->full_name }}</small>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-3">
                            <i class="fas fa-clock fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">No pending requests</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
