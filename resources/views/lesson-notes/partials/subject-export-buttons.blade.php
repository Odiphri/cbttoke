@php
    $exportParams = [
        'academic_session_id' => $note->academic_session_id,
        'school_class_id' => $note->school_class_id,
        'subject_id' => $note->subject_id,
    ];

    if (!empty($includeTeacher)) {
        $exportParams['teacher_id'] = $note->teacher_id;
    }
@endphp

<div class="btn-group" role="group" aria-label="Download complete subject note">
    <a class="btn btn-outline-secondary" href="{{ route($routeName, $exportParams + ['format' => 'pdf']) }}">
        <i class="fas fa-file-pdf me-1"></i> Complete PDF
    </a>
    <a class="btn btn-outline-secondary" href="{{ route($routeName, $exportParams + ['format' => 'word']) }}">
        <i class="fas fa-file-word me-1"></i> Complete Word
    </a>
</div>
