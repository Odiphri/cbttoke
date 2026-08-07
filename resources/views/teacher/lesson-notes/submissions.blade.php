@extends('layouts.admin')

@section('title', 'Exercise Submissions')

@section('content')
<div class="card"><div class="card-header">{{ $lessonExercise->title }}</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Student</th><th>Attempt</th><th>Submitted</th><th>Objective</th><th>Status</th><th>Final</th><th>Counted</th><th></th></tr></thead><tbody>
@forelse($attempts as $attempt)
<tr>
    <td>{{ $attempt->student?->full_name }}</td>
    <td>{{ $attempt->attempt_number }}</td>
    <td>{{ $attempt->submitted_at }}</td>
    <td>{{ $attempt->auto_score }}</td>
    <td><span class="badge {{ $attempt->status === 'awaiting_marking' ? 'bg-warning text-dark' : 'bg-success' }}">{{ str_replace('_',' ', $attempt->status) }}</span></td>
    <td>{{ $attempt->total_score }}</td>
    <td>{{ $attempt->is_counted ? 'Yes' : 'No' }}</td>
    <td>
        <div class="d-flex gap-1 flex-wrap">
            <a class="btn btn-sm btn-primary-custom" href="{{ route('teacher.exercises.submissions.mark', [$lessonExercise, $attempt]) }}">
                <i class="fas fa-{{ $attempt->status === 'marked' ? 'edit' : 'eye' }} me-1"></i> {{ $attempt->status === 'marked' ? 'Edit Marking' : 'View' }}
            </a>
            <form method="POST" action="{{ route('teacher.exercises.submissions.destroy', [$lessonExercise, $attempt]) }}" onsubmit="return confirm('Remove this student submission from the exercise? This cannot be undone.')">
                @csrf
                @method('DELETE')
                <button class="btn btn-sm btn-outline-danger"><i class="far fa-trash-alt me-1"></i> Remove</button>
            </form>
        </div>
    </td>
</tr>
@empty
<tr><td colspan="8" class="text-center text-muted py-4">No submissions yet.</td></tr>
@endforelse
</tbody></table></div></div>
{{ $attempts->links() }}
@endsection
