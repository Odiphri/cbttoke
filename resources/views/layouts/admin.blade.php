<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $portalTitle = match(Auth::user()->role ?? null) {
            'admin' => 'Admin Portal',
            'teacher' => 'Teacher Portal',
            'student' => 'Student Portal',
            'hod' => 'HOD Portal',
            'cbt_personnel' => 'CBT Portal',
            'prefect' => 'Prefect Portal',
            default => 'School Portal',
        };
        $schoolName = $schoolSettings?->school_name ?? 'TOKE Schools';
        $schoolIcon = $schoolSettings?->logo_path ? asset('storage/' . $schoolSettings->logo_path) : asset('images/default-school-icon.svg');
        $defaultAvatar = asset('images/default-avatar.svg');
        $dashboardRoute = match(Auth::user()->role ?? null) {
            'admin' => route('admin.dashboard'),
            'teacher' => route('teacher.dashboard'),
            'student' => route('student.dashboard'),
            'prefect' => route('prefect.dashboard'),
            'hod' => route('hod.dashboard'),
            'cbt_personnel' => route('cbt.dashboard'),
            default => route('home'),
        };
        $isFormPage = request()->routeIs(
            '*.create',
            '*.edit',
            '*.mark',
            '*.take',
            'teacher.lesson-notes.create',
            'teacher.lesson-notes.edit',
            'student.exercises.show',
            'prefect.exercises.show'
        );
    @endphp
    <title>@yield('title', $portalTitle) - {{ $schoolName }} CBT Portal</title>
    <link rel="icon" href="{{ $schoolIcon }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ $schoolIcon }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --navy-blue: #0a1931;
            --light-pink: #f9c4d2;
            --white: #ffffff;
            --gray-light: #f8f9fa;
            --gray-dark: #6c757d;
        }
        
        body {
            background-color: var(--gray-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background: var(--navy-blue);
            height: 100vh;
            height: 100dvh;
            color: var(--white);
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 250px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
            touch-action: pan-y;
        }
        
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            flex: 0 0 auto;
        }
        
        .sidebar-logo {
            width: 60px;
            height: 60px;
            background: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: bold;
            color: var(--navy-blue);
            font-size: 18px;
            overflow: hidden;
        }

        .sidebar-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .sidebar-menu {
            padding: 20px 0;
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
            touch-action: pan-y;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.35) transparent;
        }

        .sidebar-menu::-webkit-scrollbar {
            width: 7px;
        }

        .sidebar-menu::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar-menu::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 999px;
        }
        
        .sidebar-item {
            padding: 12px 20px;
            display: block;
            color: var(--white);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            position: relative;
            font-size: 14px;
        }
        
        .sidebar-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left-color: var(--light-pink);
            color: var(--white);
            transform: translateX(2px);
        }
        
        .sidebar-item.active {
            background: rgba(255, 255, 255, 0.15);
            border-left-color: var(--light-pink);
            color: var(--white);
            font-weight: 600;
        }
        
        .sidebar-item.active::before {
            content: '';
            position: absolute;
            left: -3px;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 20px;
            background: var(--light-pink);
            border-radius: 0 2px 2px 0;
        }
        
        .sidebar-divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
            margin: 8px 20px;
        }
        
        .sidebar-section {
            padding: 10px 20px 5px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.6);
            font-weight: 600;
        }
        
        .logout-btn {
            width: 100%;
            text-align: left;
            border: none;
            background: transparent;
            color: white;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left-color: #dc3545;
        }
        
        .logout-text {
            display: inline;
        }
        
        @media (max-width: 768px) {
            .sidebar-item {
                padding: 15px 20px;
                font-size: 15px;
            }
            
            .sidebar-section {
                padding: 15px 20px 8px;
                font-size: 12px;
            }
            
            .logout-btn {
                padding: 15px 20px;
                font-size: 15px;
            }
            
            .logout-text {
                display: inline;
            }
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .top-header {
            background: var(--white);
            padding: 15px 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-title {
            color: var(--navy-blue);
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--light-pink);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--navy-blue);
            font-weight: bold;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: var(--navy-blue);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--gray-dark);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .card {
            background: var(--white);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .card-header {
            background: var(--navy-blue);
            color: var(--white);
            border-radius: 10px 10px 0 0;
            padding: 15px 20px;
            font-weight: 600;
        }
        
        .table-custom {
            background: var(--white);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .btn-primary-custom {
            background: var(--navy-blue);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            color: var(--white);
            transition: all 0.3s ease;
        }
        
        .btn-primary-custom:hover {
            background: #1a2941;
            transform: translateY(-1px);
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .pagination svg {
            width: 1rem;
            height: 1rem;
            vertical-align: middle;
        }

        .pagination nav > div:first-child,
        .pagination nav > div:last-child > div:first-child {
            display: none;
        }
        
        .badge-primary {
            background: #007bff;
            color: var(--white);
        }
        
        .badge-danger {
            background: #dc3545;
            color: var(--white);
        }
        
        .badge-success {
            background: #28a745;
            color: var(--white);
        }
        
        .bg-success {
            background-color: #28a745 !important;
        }
        
        .bg-danger {
            background-color: #dc3545 !important;
        }
        
        .text-white {
            color: var(--white) !important;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                z-index: 1050;
                max-width: 320px;
                height: 100vh;
                height: 100dvh;
                box-shadow: 8px 0 24px rgba(0, 0, 0, 0.2);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 74px 12px 12px;
            }
            
            .top-header {
                padding: 12px 14px;
                margin-bottom: 15px;
                display: grid;
                grid-template-columns: 1fr auto;
                gap: 8px 12px;
                align-items: center;
            }
            
            .page-title {
                font-size: 18px;
                padding-left: 0;
                line-height: 1.25;
            }
            
            .user-info {
                width: 100%;
                grid-column: 1 / -1;
                justify-content: space-between;
                min-width: 0;
                gap: 10px;
            }

            .top-actions {
                grid-column: 1 / -1;
                justify-content: space-between;
                width: 100%;
            }

            .user-info span {
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            
            .card {
                margin-bottom: 15px;
            }
            
            .card-header {
                padding: 10px 15px;
                font-size: 14px;
            }
            
            .table-custom {
                font-size: 12px;
            }
            
            .btn-primary-custom {
                padding: 8px 16px;
                font-size: 14px;
            }
            
            .badge {
                font-size: 10px;
                padding: 3px 8px;
            }
        }
        
        @media (max-width: 576px) {
            .sidebar {
                width: min(88vw, 320px);
            }
            
            .sidebar-header {
                padding: 15px;
            }
            
            .sidebar-logo {
                width: 50px;
                height: 50px;
                font-size: 16px;
            }
            
            .sidebar-menu {
                padding: 15px 0;
                padding-bottom: max(15px, env(safe-area-inset-bottom));
            }
            
            .sidebar-item {
                padding: 10px 15px;
                font-size: 14px;
            }
            
            .main-content {
                padding: 68px 8px 8px;
            }
            
            .top-header {
                padding: 10px 12px;
                margin-bottom: 10px;
            }
            
            .page-title {
                font-size: 16px;
            }
            
            .user-avatar {
                width: 35px;
                height: 35px;
                font-size: 12px;
            }
            
            .card {
                border-radius: 8px;
                margin-bottom: 10px;
            }
            
            .card-header {
                padding: 8px 12px;
                font-size: 13px;
                border-radius: 8px 8px 0 0;
            }
            
            .table-custom {
                font-size: 11px;
            }
            
            .btn-primary-custom {
                padding: 6px 12px;
                font-size: 13px;
                width: 100%;
                margin-bottom: 5px;
            }
            
            .badge {
                font-size: 9px;
                padding: 2px 6px;
            }
        }
        
        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 12px;
            left: 12px;
            z-index: 1100;
            background: var(--navy-blue);
            color: var(--white);
            border: none;
            border-radius: 8px;
            width: 44px;
            height: 44px;
            padding: 0;
            cursor: pointer;
            font-size: 18px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.18);
        }

        .live-search-card {
            margin-bottom: 16px;
        }

        [aria-busy="true"].live-search-loading {
            position: relative;
            min-height: 90px;
            opacity: .65;
            pointer-events: none;
        }

        [aria-busy="true"].live-search-loading::after {
            content: "Loading results...";
            position: absolute;
            top: 12px;
            right: 12px;
            z-index: 3;
            background: #0a1931;
            color: #fff;
            border-radius: 8px;
            padding: 7px 12px;
            font-size: .82rem;
            box-shadow: 0 4px 16px rgba(10, 25, 49, .18);
        }
        
        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .top-header {
                margin-left: 52px;
            }
        }
        
        /* Responsive Tables */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        @media (max-width: 768px) {
            .table-responsive table {
                font-size: 12px;
            }
            
            .table-responsive th,
            .table-responsive td {
                padding: 5px;
                white-space: nowrap;
            }
        }
        
        @media (max-width: 576px) {
            .table-responsive table {
                font-size: 11px;
            }
            
            .table-responsive th,
            .table-responsive td {
                padding: 3px;
            }
        }
        
        /* Responsive Dashboard Cards */
        @media (max-width: 768px) {
            .row > .col-md-3 {
                margin-bottom: 15px;
            }
            
            .row > .col-md-4 {
                margin-bottom: 15px;
            }
            
            .row > .col-md-6 {
                margin-bottom: 15px;
            }
        }
        
        @media (max-width: 576px) {
            .row > .col-md-3,
            .row > .col-md-4,
            .row > .col-md-6 {
                margin-bottom: 10px;
            }
        }
        
        /* Responsive Forms */
        @media (max-width: 768px) {
            .form-control,
            .form-select {
                font-size: 14px;
                padding: 8px 12px;
            }
            
            .form-label {
                font-size: 13px;
                margin-bottom: 5px;
            }
        }
        
        @media (max-width: 576px) {
            .form-control,
            .form-select {
                font-size: 13px;
                padding: 6px 10px;
            }
            
            .form-label {
                font-size: 12px;
                margin-bottom: 3px;
            }
        }
        
        /* Responsive Buttons */
        @media (max-width: 768px) {
            .btn-group {
                flex-direction: column;
                width: 100%;
            }
            
            .btn-group .btn {
                margin-bottom: 5px;
                width: 100%;
            }
        }
        
        @media (max-width: 576px) {
            .btn-group .btn {
                font-size: 12px;
                padding: 8px 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <img src="{{ $schoolIcon }}" alt="{{ $schoolName }} logo">
            </div>
            <div class="h6 mb-0">
            @if(Auth::user()->role === 'admin')
                Admin Portal
            @elseif(Auth::user()->role === 'teacher')
                Teacher Portal
            @elseif(Auth::user()->role === 'student')
                Student Portal
            @elseif(Auth::user()->role === 'prefect')
                Prefect Portal
            @elseif(Auth::user()->role === 'hod')
                HOD Portal
            @elseif(Auth::user()->role === 'cbt_personnel')
                CBT Portal
            @else
                TOKE Portal
            @endif
        </div>
        </div>
        <div class="sidebar-menu">
            @if(Auth::user()->role === 'admin')
            <div class="sidebar-section">Main</div>
            <a href="{{ route('admin.dashboard') }}" class="sidebar-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
            
            <div class="sidebar-section">User Management</div>
            <a href="{{ route('admin.users') }}" class="sidebar-item {{ request()->routeIs('admin.users*') ? 'active' : '' }}">
                <i class="fas fa-users me-2"></i> User Management
            </a>
            <a href="{{ route('admin.students') }}" class="sidebar-item {{ request()->routeIs('admin.students*') ? 'active' : '' }}">
                <i class="fas fa-user-graduate me-2"></i> Students
            </a>
            <a href="{{ route('admin.staff') }}" class="sidebar-item {{ request()->routeIs('admin.staff*') ? 'active' : '' }}">
                <i class="fas fa-chalkboard-teacher me-2"></i> Teachers & Staff
            </a>
            
            <div class="sidebar-section">Academic</div>
            <a href="{{ route('admin.lesson-notes.index') }}" class="sidebar-item {{ request()->routeIs('admin.lesson-notes*') ? 'active' : '' }}">
                <i class="fas fa-book-open me-2"></i> Lesson Notes
                @php($adminPendingLessonNotes = \App\Models\LessonNote::where('status', 'pending')->count())
                @if($adminPendingLessonNotes > 0)<span class="badge bg-warning text-dark float-end">{{ $adminPendingLessonNotes }}</span>@endif
            </a>
            <a href="{{ route('admin.classes') }}" class="sidebar-item {{ request()->routeIs('admin.classes*') ? 'active' : '' }}">
                <i class="fas fa-school me-2"></i> Classes
            </a>
            <a href="{{ route('admin.subjects') }}" class="sidebar-item {{ request()->routeIs('admin.subjects*') ? 'active' : '' }}">
                <i class="fas fa-book me-2"></i> Subjects
            </a>
            <a href="{{ route('academic-sessions.index') }}" class="sidebar-item {{ request()->routeIs('academic-sessions*') ? 'active' : '' }}">
                <i class="fas fa-calendar-alt me-2"></i> Academic Sessions
            </a>
            
            <div class="sidebar-section">Student Roles</div>
            <a href="{{ route('student-roles.index') }}" class="sidebar-item {{ request()->routeIs('student-roles*') ? 'active' : '' }}">
                <i class="fas fa-id-badge me-2"></i> Class Roles
            </a>
            <a href="{{ route('prefect-roles.index') }}" class="sidebar-item {{ request()->routeIs('prefect-roles*') ? 'active' : '' }}">
                <i class="fas fa-user-shield me-2"></i> Prefect Roles
            </a>
            
            <div class="sidebar-section">Operations</div>
            <a href="{{ route('admin.exams') }}" class="sidebar-item {{ request()->routeIs('admin.exams*') ? 'active' : '' }}">
                <i class="fas fa-clipboard-list me-2"></i> Exams
            </a>
            <a href="{{ route('admin.payments') }}" class="sidebar-item {{ request()->routeIs('admin.payments*') ? 'active' : '' }}">
                <i class="fas fa-money-bill-wave me-2"></i> Bursary
            </a>
            <a href="{{ route('admin.overrides') }}" class="sidebar-item {{ request()->routeIs('admin.overrides*') ? 'active' : '' }}">
                <i class="fas fa-shield-alt me-2"></i> Overrides
            </a>
            <a href="{{ route('admin.exercises') }}" class="sidebar-item {{ request()->routeIs('admin.exercises*') ? 'active' : '' }}">
                <i class="fas fa-pen-alt me-2"></i> Exercises
            </a>
            <a href="{{ route('admin.results') }}" class="sidebar-item {{ request()->routeIs('admin.results*') ? 'active' : '' }}">
                <i class="fas fa-chart-bar me-2"></i> Results
            </a>
            <a href="{{ route('admin.monitor') }}" class="sidebar-item {{ request()->routeIs('admin.monitor*') ? 'active' : '' }}">
                <i class="fas fa-eye me-2"></i> Monitor
            </a>
            <a href="{{ route('traffic.index') }}" class="sidebar-item {{ request()->routeIs('traffic*') ? 'active' : '' }}">
                <i class="fas fa-chart-line me-2"></i> Traffic
            </a>
            <a href="{{ route('admin.reports') }}" class="sidebar-item {{ request()->routeIs('admin.reports*') ? 'active' : '' }}">
                <i class="fas fa-file-alt me-2"></i> Reports
            </a>
            <a href="{{ route('admin.teacher-workload') }}" class="sidebar-item {{ request()->routeIs('admin.teacher-workload*') ? 'active' : '' }}">
                <i class="fas fa-user-clock me-2"></i> Teacher Workload
            </a>
            <a href="{{ route('admin.lesson-note-coverage') }}" class="sidebar-item {{ request()->routeIs('admin.lesson-note-coverage*') ? 'active' : '' }}">
                <i class="fas fa-calendar-check me-2"></i> Note Coverage
            </a>
            
            <div class="sidebar-divider"></div>
            <a href="{{ route('admin.profile.edit') }}" class="sidebar-item {{ request()->routeIs('admin.profile*') ? 'active' : '' }}">
                <i class="fas fa-user-cog me-2"></i> Profile
            </a>
            <a href="{{ route('admin.settings') }}" class="sidebar-item {{ request()->routeIs('admin.settings*') ? 'active' : '' }}">
                <i class="fas fa-cog me-2"></i> Settings
            </a>
        @elseif(Auth::user()->role === 'teacher')
            <div class="sidebar-section">Main</div>
            <a href="{{ route('teacher.dashboard') }}" class="sidebar-item {{ request()->routeIs('teacher.dashboard') ? 'active' : '' }}">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
            
            <div class="sidebar-section">Academic</div>
            <a href="{{ route('teacher.lesson-notes.index') }}" class="sidebar-item {{ request()->routeIs('teacher.lesson-notes*') || request()->routeIs('teacher.exercises*') ? 'active' : '' }}">
                <i class="fas fa-book-open me-2"></i> Lesson Notes
            </a>
            <a href="{{ route('teacher.exams') }}" class="sidebar-item {{ request()->routeIs('teacher.exams*') ? 'active' : '' }}">
                <i class="fas fa-clipboard-list me-2"></i> Exams
            </a>
            <a href="{{ route('teacher.results') }}" class="sidebar-item {{ request()->routeIs('teacher.results*') ? 'active' : '' }}">
                <i class="fas fa-chart-bar me-2"></i> Results
            </a>
            <a href="{{ route('teacher.classes') }}" class="sidebar-item {{ request()->routeIs('teacher.classes*') ? 'active' : '' }}">
                <i class="fas fa-school me-2"></i> My Classes
            </a>
            @if(\App\Models\SchoolClass::where('class_teacher_id', Auth::id())->orWhereHas('teachers', fn ($query) => $query->whereKey(Auth::id()))->orWhereHas('assignedStaff', fn ($query) => $query->whereKey(Auth::id()))->exists())
            <a href="{{ route('teacher.promotions') }}" class="sidebar-item {{ request()->routeIs('teacher.promotions*') ? 'active' : '' }}">
                <i class="fas fa-user-check me-2"></i> Promote / Demote
            </a>
            @endif
            <a href="{{ route('teacher.students') }}" class="sidebar-item {{ request()->routeIs('teacher.students*') ? 'active' : '' }}">
                <i class="fas fa-users me-2"></i> Students
            </a>
            <a href="{{ route('academic-sessions.index') }}" class="sidebar-item {{ request()->routeIs('academic-sessions*') ? 'active' : '' }}">
                <i class="fas fa-calendar-alt me-2"></i> Academic Sessions
            </a>
            
            <div class="sidebar-section">Operations</div>
            @if(Auth::user()->role === 'teacher' && \App\Models\SchoolClass::where('class_teacher_id', Auth::id())->exists())
            <a href="{{ route('teacher.attendance') }}" class="sidebar-item {{ request()->routeIs('teacher.attendance*') ? 'active' : '' }}">
                <i class="fas fa-calendar-check me-2"></i> Attendance
            </a>
            @endif
            @can('bursary.manage')
            <a href="{{ route('teacher.payments') }}" class="sidebar-item {{ request()->routeIs('teacher.payments*') ? 'active' : '' }}">
                <i class="fas fa-money-bill-wave me-2"></i> Bursary
            </a>
            @endcan
            @can('exams.override_access')
            <a href="{{ route('teacher.overrides') }}" class="sidebar-item {{ request()->routeIs('teacher.overrides*') ? 'active' : '' }}">
                <i class="fas fa-shield-alt me-2"></i> Overrides
            </a>
            @endcan
            @can('student_roles.manage')
            <a href="{{ route('student-roles.index') }}" class="sidebar-item {{ request()->routeIs('student-roles*') ? 'active' : '' }}">
                <i class="fas fa-id-badge me-2"></i> Class Roles
            </a>
            <a href="{{ route('prefect-roles.index') }}" class="sidebar-item {{ request()->routeIs('prefect-roles*') ? 'active' : '' }}">
                <i class="fas fa-user-shield me-2"></i> Prefect Roles
            </a>
            @endcan
            <a href="{{ route('teacher.profile.edit') }}" class="sidebar-item {{ request()->routeIs('teacher.profile*') ? 'active' : '' }}">
                <i class="fas fa-user-cog me-2"></i> Profile
            </a>
        @elseif(in_array(Auth::user()->role, ['student', 'prefect'], true))
            <div class="sidebar-section">Main</div>
            @if(Auth::user()->role === 'prefect')
            <a href="{{ route('prefect.dashboard') }}" class="sidebar-item {{ request()->routeIs('prefect.dashboard') ? 'active' : '' }}">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
            <a href="{{ route('prefect.students') }}" class="sidebar-item {{ request()->routeIs('prefect.students*') ? 'active' : '' }}">
                <i class="fas fa-users me-2"></i> Students
            </a>
            @else
            <a href="{{ route('student.dashboard') }}" class="sidebar-item {{ request()->routeIs('student.dashboard') ? 'active' : '' }}">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
            @endif
            
            <div class="sidebar-section">Academic</div>
            <a href="{{ route('student.lesson-notes.index') }}" class="sidebar-item {{ request()->routeIs('student.lesson-notes*') || request()->routeIs('prefect.lesson-notes*') ? 'active' : '' }}">
                <i class="fas fa-book-open me-2"></i> Lesson Notes
            </a>
            <a href="{{ route('student.exercises.index') }}" class="sidebar-item {{ request()->routeIs('student.exercises*') || request()->routeIs('prefect.exercises*') ? 'active' : '' }}">
                <i class="fas fa-tasks me-2"></i> Exercises
            </a>
            <a href="{{ route(Auth::user()->role === 'prefect' ? 'prefect.exams' : 'student.exams') }}" class="sidebar-item {{ request()->routeIs('student.exams*') || request()->routeIs('prefect.exams*') ? 'active' : '' }}">
                <i class="fas fa-clipboard-list me-2"></i> Exams
            </a>
            <a href="{{ route('academic-sessions.index') }}" class="sidebar-item {{ request()->routeIs('academic-sessions*') ? 'active' : '' }}">
                <i class="fas fa-calendar-alt me-2"></i> Academic Sessions
            </a>
            
            <div class="sidebar-section">Personal</div>
            <a href="{{ route('student.payments') }}" class="sidebar-item {{ request()->routeIs('student.payments*') ? 'active' : '' }}">
                <i class="fas fa-money-bill-wave me-2"></i> Payments
            </a>
            <a href="{{ route('student.attendance') }}" class="sidebar-item {{ request()->routeIs('student.attendance*') ? 'active' : '' }}">
                <i class="fas fa-calendar-check me-2"></i> Attendance
            </a>
            <a href="{{ route('student.requests') }}" class="sidebar-item {{ request()->routeIs('student.requests*') ? 'active' : '' }}">
                <i class="fas fa-edit me-2"></i> Requests
            </a>
            <a href="{{ route('student.profile.edit') }}" class="sidebar-item {{ request()->routeIs('student.profile*') ? 'active' : '' }}">
                <i class="fas fa-user-cog me-2"></i> Profile
            </a>
        @elseif(Auth::user()->role === 'hod')
            <div class="sidebar-section">Main</div>
            <a href="{{ route('hod.dashboard') }}" class="sidebar-item {{ request()->routeIs('hod.dashboard') ? 'active' : '' }}">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
            
            <div class="sidebar-section">Academic</div>
            <a href="{{ route('hod.lesson-notes.index') }}" class="sidebar-item {{ request()->routeIs('hod.lesson-notes*') ? 'active' : '' }}">
                <i class="fas fa-book-open me-2"></i> Lesson Notes
                @php($hodPendingLessonNotes = \App\Models\LessonNote::where('status', 'pending')->count())
                @if($hodPendingLessonNotes > 0)<span class="badge bg-warning text-dark float-end">{{ $hodPendingLessonNotes }}</span>@endif
            </a>
            <a href="{{ route('hod.exams') }}" class="sidebar-item {{ request()->routeIs('hod.exams*') ? 'active' : '' }}">
                <i class="fas fa-clipboard-list me-2"></i> Exams
            </a>
            <a href="{{ route('hod.classes') }}" class="sidebar-item {{ request()->routeIs('hod.classes*') ? 'active' : '' }}">
                <i class="fas fa-school me-2"></i> Classes
            </a>
            <a href="{{ route('hod.subjects') }}" class="sidebar-item {{ request()->routeIs('hod.subjects*') ? 'active' : '' }}">
                <i class="fas fa-book me-2"></i> Subjects
            </a>
            <a href="{{ route('academic-sessions.index') }}" class="sidebar-item {{ request()->routeIs('academic-sessions*') ? 'active' : '' }}">
                <i class="fas fa-calendar-alt me-2"></i> Academic Sessions
            </a>
            
            <div class="sidebar-section">User Management</div>
            <a href="{{ route('hod.students') }}" class="sidebar-item {{ request()->routeIs('hod.students*') ? 'active' : '' }}">
                <i class="fas fa-users me-2"></i> Students
            </a>
            <a href="{{ route('hod.staff') }}" class="sidebar-item {{ request()->routeIs('hod.staff*') ? 'active' : '' }}">
                <i class="fas fa-chalkboard-teacher me-2"></i> Teachers & Staff
            </a>
            
            <div class="sidebar-section">Student Roles</div>
            <a href="{{ route('student-roles.index') }}" class="sidebar-item {{ request()->routeIs('student-roles*') ? 'active' : '' }}">
                <i class="fas fa-id-badge me-2"></i> Class Roles
            </a>
            <a href="{{ route('prefect-roles.index') }}" class="sidebar-item {{ request()->routeIs('prefect-roles*') ? 'active' : '' }}">
                <i class="fas fa-user-shield me-2"></i> Prefect Roles
            </a>
            
            <div class="sidebar-section">Operations</div>
            <a href="{{ route('hod.overrides') }}" class="sidebar-item {{ request()->routeIs('hod.overrides*') ? 'active' : '' }}">
                <i class="fas fa-shield-alt me-2"></i> Overrides
            </a>
            <a href="{{ route('hod.monitor') }}" class="sidebar-item {{ request()->routeIs('hod.monitor*') ? 'active' : '' }}">
                <i class="fas fa-eye me-2"></i> Monitor
            </a>
            <a href="{{ route('traffic.index') }}" class="sidebar-item {{ request()->routeIs('traffic*') ? 'active' : '' }}">
                <i class="fas fa-chart-line me-2"></i> Traffic
            </a>
            <a href="{{ route('hod.results') }}" class="sidebar-item {{ request()->routeIs('hod.results*') ? 'active' : '' }}">
                <i class="fas fa-chart-bar me-2"></i> Results
            </a>
            <a href="{{ route('hod.payments') }}" class="sidebar-item {{ request()->routeIs('hod.payments*') ? 'active' : '' }}">
                <i class="fas fa-money-bill-wave me-2"></i> Bursary
            </a>
            <a href="{{ route('hod.profile.edit') }}" class="sidebar-item {{ request()->routeIs('hod.profile*') ? 'active' : '' }}">
                <i class="fas fa-user-cog me-2"></i> Profile
            </a>
        @elseif(Auth::user()->role === 'cbt_personnel')
            <div class="sidebar-section">Main</div>
            <a href="{{ route('cbt.dashboard') }}" class="sidebar-item {{ request()->routeIs('cbt.dashboard') ? 'active' : '' }}">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
            
            <div class="sidebar-section">Exam Management</div>
            <a href="{{ route('cbt.exams') }}" class="sidebar-item {{ request()->routeIs('cbt.exams*') ? 'active' : '' }}">
                <i class="fas fa-clipboard-list me-2"></i> Exams
            </a>
            <a href="{{ route('cbt.monitor') }}" class="sidebar-item {{ request()->routeIs('cbt.monitor*') ? 'active' : '' }}">
                <i class="fas fa-eye me-2"></i> Monitor
            </a>
            <a href="{{ route('traffic.index') }}" class="sidebar-item {{ request()->routeIs('traffic*') ? 'active' : '' }}">
                <i class="fas fa-chart-line me-2"></i> Traffic
            </a>
            <a href="{{ route('cbt.results') }}" class="sidebar-item {{ request()->routeIs('cbt.results*') ? 'active' : '' }}">
                <i class="fas fa-chart-bar me-2"></i> Results
            </a>
            <a href="{{ route('academic-sessions.index') }}" class="sidebar-item {{ request()->routeIs('academic-sessions*') ? 'active' : '' }}">
                <i class="fas fa-calendar-alt me-2"></i> Academic Sessions
            </a>
            @can('students.manage')
            <a href="{{ route('cbt.students') }}" class="sidebar-item {{ request()->routeIs('cbt.students*') ? 'active' : '' }}">
                <i class="fas fa-users me-2"></i> Students
            </a>
            @endcan
            @can('bursary.manage')
            <a href="{{ route('cbt.payments') }}" class="sidebar-item {{ request()->routeIs('cbt.payments*') ? 'active' : '' }}">
                <i class="fas fa-money-bill-wave me-2"></i> Bursary
            </a>
            @endcan
            @can('exams.override_access')
            <a href="{{ route('cbt.overrides') }}" class="sidebar-item {{ request()->routeIs('cbt.overrides*') ? 'active' : '' }}">
                <i class="fas fa-shield-alt me-2"></i> Overrides
            </a>
            @endcan
            
            @can('student_roles.manage')
            <div class="sidebar-section">Student Roles</div>
            <a href="{{ route('student-roles.index') }}" class="sidebar-item {{ request()->routeIs('student-roles*') ? 'active' : '' }}">
                <i class="fas fa-id-badge me-2"></i> Class Roles
            </a>
            <a href="{{ route('prefect-roles.index') }}" class="sidebar-item {{ request()->routeIs('prefect-roles*') ? 'active' : '' }}">
                <i class="fas fa-user-shield me-2"></i> Prefect Roles
            </a>
            @endcan
            <a href="{{ route('cbt.profile.edit') }}" class="sidebar-item {{ request()->routeIs('cbt.profile*') ? 'active' : '' }}">
                <i class="fas fa-user-cog me-2"></i> Profile
            </a>
        @endif
            <div class="sidebar-divider"></div>
            <form action="{{ route('logout') }}" method="POST" style="display: inline;">
                @csrf
                <button type="submit" class="sidebar-item logout-btn">
                    <i class="fas fa-sign-out-alt me-2"></i> 
                    <span class="logout-text">Logout</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <div class="top-header">
            <h1 class="page-title">@yield('title', $portalTitle)</h1>
            <div class="top-actions">
                @unless(request()->routeIs('*.dashboard'))
                <button type="button" class="btn btn-outline-secondary btn-sm" data-smart-back data-fallback-url="{{ $dashboardRoute }}">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </button>
                @endunless
                <div class="user-info">
                    <span>Welcome, {{ Auth::user()->full_name }}</span>
                    <div class="user-avatar">
                        <img src="{{ Auth::user()->profile?->profile_picture_url ?? $defaultAvatar }}" alt="{{ Auth::user()->full_name }}" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                    </div>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if(session('info'))
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                {{ session('info') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if(session('status'))
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                {{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Please check the form.</strong>
                <ul class="mb-0 mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- Page Content -->
        @yield('content')
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
    const currentEntry = {
        url: window.location.href,
        path: window.location.pathname + window.location.search,
        isForm: @json($isFormPage),
        at: Date.now()
    };
    const storageKey = 'portalNavigationStack';
    const maxEntries = 30;
    let stack = [];

    try {
        stack = JSON.parse(sessionStorage.getItem(storageKey) || '[]');
    } catch (error) {
        stack = [];
    }

    const lastEntry = stack[stack.length - 1];
    if (!lastEntry || lastEntry.path !== currentEntry.path) {
        stack = stack.filter((entry) => entry && entry.path !== currentEntry.path);
        stack.push(currentEntry);
        stack = stack.slice(-maxEntries);
        sessionStorage.setItem(storageKey, JSON.stringify(stack));
    }

    document.querySelectorAll('[data-smart-back]').forEach((button) => {
        button.addEventListener('click', () => {
            const fallbackUrl = button.dataset.fallbackUrl || '{{ $dashboardRoute }}';
            let storedStack = [];

            try {
                storedStack = JSON.parse(sessionStorage.getItem(storageKey) || '[]');
            } catch (error) {
                storedStack = [];
            }

            storedStack = storedStack.filter((entry) => entry && entry.path !== currentEntry.path);
            const destinationIndex = storedStack
                .map((entry, index) => ({ entry, index }))
                .reverse()
                .find((item) => !item.entry.isForm)?.index;

            const destination = destinationIndex === undefined ? null : storedStack[destinationIndex];

            if (destinationIndex !== undefined) {
                storedStack = storedStack.slice(0, destinationIndex);
            }

            sessionStorage.setItem(storageKey, JSON.stringify(storedStack.slice(-maxEntries)));

            window.location.assign(destination?.url || fallbackUrl);
        });
    });
})();

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('active');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.querySelector('.mobile-menu-toggle');
    
    if (window.innerWidth <= 768 && 
        !sidebar.contains(event.target) && 
        !toggle.contains(event.target) && 
        sidebar.classList.contains('active')) {
        sidebar.classList.remove('active');
    }
});

// Handle window resize
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('sidebar');
    if (window.innerWidth > 768) {
        sidebar.classList.remove('active');
    }
});

// Close sidebar with Escape key
document.addEventListener('keydown', function(event) {
    const sidebar = document.getElementById('sidebar');
    if (event.key === 'Escape' && sidebar.classList.contains('active')) {
        sidebar.classList.remove('active');
    }
});

document.querySelectorAll('form[data-auto-submit="true"]').forEach((form) => {
    let timer;
    const targetId = form.dataset.liveSearchTarget;
    const target = targetId ? document.getElementById(targetId) : null;

    const setLoading = (isLoading) => {
        if (!target) return;
        target.classList.toggle('live-search-loading', isLoading);
        target.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    };

    const fetchResults = (url) => {
        if (!target) {
            form.requestSubmit();
            return;
        }

        setLoading(true);

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-Live-Search': '1',
            },
        })
            .then((response) => response.text())
            .then((html) => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const nextTarget = doc.getElementById(targetId);

                if (nextTarget) {
                    target.innerHTML = nextTarget.innerHTML;
                    window.history.replaceState({}, '', url);
                    bindLivePagination(target);
                    document.dispatchEvent(new CustomEvent('live-search:updated', {
                        detail: { target },
                    }));
                }
            })
            .finally(() => setLoading(false));
    };

    const currentUrl = () => {
        const params = new URLSearchParams(new FormData(form));
        const url = new URL(form.action, window.location.origin);

        params.forEach((value, key) => {
            if (value !== '') {
                url.searchParams.set(key, value);
            }
        });

        return url.toString();
    };

    const submitForm = () => {
        window.clearTimeout(timer);
        fetchResults(currentUrl());
    };
    const debounceSubmit = () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(submitForm, 300);
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        submitForm();
    });

    form.querySelectorAll('input[type="search"], input[type="text"]').forEach((input) => {
        input.addEventListener('input', () => {
            if (input.value === '') {
                submitForm();
                return;
            }

            debounceSubmit();
        });
    });

    form.querySelectorAll('select').forEach((select) => {
        select.addEventListener('change', submitForm);
    });

    form.querySelectorAll('[data-live-search-clear]').forEach((link) => {
        link.addEventListener('click', (event) => {
            if (!target) return;
            event.preventDefault();
            form.querySelectorAll('input[type="search"], input[type="text"]').forEach((input) => {
                input.value = '';
            });
            form.querySelectorAll('select').forEach((select) => {
                select.value = '';
            });
            fetchResults(link.href);
        });
    });

    function bindLivePagination(scope) {
        scope.querySelectorAll('.pagination a').forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                fetchResults(link.href);
            });
        });
    }

    bindLivePagination(target || document);
});

@auth
(() => {
    const timeoutMs = 5 * 60 * 1000;
    let inactivityTimer;
    let loggingOut = false;

    const logoutForInactivity = () => {
        if (loggingOut) return;
        loggingOut = true;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route('logout') }}';
        form.style.display = 'none';

        const token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        const inactive = document.createElement('input');
        inactive.type = 'hidden';
        inactive.name = 'inactive';
        inactive.value = '1';

        form.appendChild(token);
        form.appendChild(inactive);
        document.body.appendChild(form);
        form.submit();
    };

    const resetInactivityTimer = () => {
        if (loggingOut) return;
        window.clearTimeout(inactivityTimer);
        inactivityTimer = window.setTimeout(logoutForInactivity, timeoutMs);
    };

    ['mousemove', 'mousedown', 'keydown', 'click', 'scroll', 'touchstart', 'pointerdown'].forEach((eventName) => {
        document.addEventListener(eventName, resetInactivityTimer, { passive: true });
    });

    resetInactivityTimer();
})();
@endauth
</script>
</body>
</html>
