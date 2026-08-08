<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; color: #1f2937; font-size: 12pt; line-height: 1.5; }
        .cover { text-align: center; border-bottom: 2px solid #0a1931; padding-bottom: 18px; margin-bottom: 22px; }
        .logo { width: 90px; height: 90px; object-fit: contain; margin-bottom: 8px; }
        .school { font-size: 24pt; font-weight: bold; color: #0a1931; margin: 0; }
        .subtitle { color: #4b5563; margin: 4px 0; }
        .meta { width: 100%; border-collapse: collapse; margin: 16px 0 24px; }
        .meta td { border: 1px solid #d8dee8; padding: 8px 10px; }
        h1, h2, h3, h4 { color: #0a1931; }
        h2 { font-size: 18pt; border-bottom: 1px solid #d8dee8; padding-bottom: 6px; }
        h3 { font-size: 14pt; }
        .week { page-break-before: always; }
        .week:first-of-type { page-break-before: auto; }
        .section { margin-top: 10px; }
        .section-title { font-weight: bold; color: #0a1931; margin-bottom: 2px; }
        .content img { max-width: 100%; height: auto; }
        .exercise { border: 1px solid #d8dee8; padding: 10px; margin-top: 14px; background: #f8fafc; }
        .question { border-top: 1px solid #d8dee8; padding-top: 8px; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="cover">
        @if($logoDataUri)
            <img class="logo" src="{{ $logoDataUri }}" alt="School logo">
        @endif
        <h1 class="school">{{ $settings->school_name }}</h1>
        @if($settings->motto)<div class="subtitle">{{ $settings->motto }}</div>@endif
        <h2>Compiled Lesson Note</h2>
        <div class="subtitle">Week {{ $startWeek }} to Week {{ $endWeek }}</div>
    </div>

    <table class="meta">
        <tr><td><strong>Class:</strong> {{ $schoolClass->full_name }}</td><td><strong>Subject:</strong> {{ $subject->name }}</td></tr>
        <tr><td><strong>Session:</strong> {{ $session?->display_name ?? 'N/A' }}</td><td><strong>Teacher:</strong> {{ $teacher?->full_name ?? 'N/A' }}</td></tr>
        <tr><td><strong>Generated:</strong> {{ now()->format('M j, Y g:i A') }}</td><td><strong>Downloaded By:</strong> {{ $generatedFor->full_name }}</td></tr>
    </table>

    @foreach($notes as $note)
        <div class="week">
            <h2>Week {{ $note->week_number }}: {{ $note->topic }}</h2>
            @if($note->subtopic)<p><strong>Subtopic:</strong> {{ $note->subtopic }}</p>@endif
            @if($note->lesson_date)<p><strong>Lesson Date:</strong> {{ $note->lesson_date->format('M j, Y') }}</p>@endif

            @foreach([
                'previous_knowledge' => 'Previous Knowledge',
                'learning_objectives' => 'Learning Objectives',
                'teaching_materials' => 'Teaching Materials',
                'introduction' => 'Introduction',
            ] as $field => $label)
                @if($note->$field)
                    <div class="section"><div class="section-title">{{ $label }}</div><div class="content">{!! $note->$field !!}</div></div>
                @endif
            @endforeach

            <div class="section"><div class="section-title">Lesson Content</div><div class="content">{!! $note->main_content !!}</div></div>

            @foreach([
                'evaluation' => 'Evaluation',
                'conclusion' => 'Conclusion',
                'assignment' => 'Assignment',
            ] as $field => $label)
                @if($note->$field)
                    <div class="section"><div class="section-title">{{ $label }}</div><div class="content">{!! $note->$field !!}</div></div>
                @endif
            @endforeach

            @if($note->exercise)
                <div class="exercise">
                    <h3>Exercise: {{ $note->exercise->title }}</h3>
                    @if($note->exercise->instructions)<div>{!! $note->exercise->instructions !!}</div>@endif
                    @foreach($note->exercise->questions as $index => $question)
                        <div class="question">
                            <strong>Question {{ $index + 1 }} ({{ $question->marks }} marks)</strong>
                            <div>{!! $question->question_text !!}</div>
                            @if($question->options)
                                @foreach($question->options as $key => $option)
                                    <div><strong>{{ $key }}.</strong> {!! $option !!}</div>
                                @endforeach
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</body>
</html>
