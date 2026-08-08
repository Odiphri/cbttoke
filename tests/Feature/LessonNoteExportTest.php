<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\LessonNote;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LessonNoteExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_download_complete_subject_note_as_word_and_pdf(): void
    {
        $session = AcademicSession::create(['academic_year' => '2026/2027', 'term' => 'First Term', 'is_active' => true]);
        $class = SchoolClass::create(['name' => 'JSS1A', 'level' => 'JSS1', 'stream' => 'A', 'is_active' => true]);
        $subject = Subject::create(['name' => 'Mathematics', 'code' => 'MTH-EXP', 'school_class_id' => $class->id, 'is_active' => true]);
        $teacher = $this->user('teacher-export', 'teacher');
        $student = $this->user('student-export', 'student', ['school_class_id' => $class->id]);
        SchoolSetting::create(['school_name' => 'Bright Future School', 'exam_duration' => 120, 'pass_mark' => 50, 'auto_grade' => true]);

        foreach ([1, 2, 3] as $week) {
            LessonNote::create([
                'academic_session_id' => $session->id,
                'school_class_id' => $class->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
                'week_number' => $week,
                'title' => "Week {$week} Fractions",
                'topic' => "Fractions Week {$week}",
                'main_content' => "<p>Week {$week} content.</p>",
                'status' => LessonNote::STATUS_APPROVED,
                'published_at' => now(),
            ]);
        }

        $wordResponse = $this->actingAs($student)
            ->get(route('student.lesson-notes.exports.subject', [
                'academic_session_id' => $session->id,
                'school_class_id' => $class->id,
                'subject_id' => $subject->id,
                'format' => 'word',
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/msword; charset=UTF-8');

        $wordResponse->assertSee('Bright Future School', false)
            ->assertSee('Week 1: Fractions Week 1', false)
            ->assertSee('Week 3: Fractions Week 3', false);

        $this->actingAs($student)
            ->get(route('student.lesson-notes.exports.subject', [
                'academic_session_id' => $session->id,
                'school_class_id' => $class->id,
                'subject_id' => $subject->id,
                'format' => 'pdf',
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_student_cannot_download_subject_notes_for_another_class(): void
    {
        $this->withoutExceptionHandling();
        $this->expectException(HttpException::class);
        $session = AcademicSession::create(['academic_year' => '2026/2027', 'term' => 'First Term', 'is_active' => true]);
        $ownClass = SchoolClass::create(['name' => 'JSS1A', 'level' => 'JSS1', 'stream' => 'A', 'is_active' => true]);
        $otherClass = SchoolClass::create(['name' => 'JSS2A', 'level' => 'JSS2', 'stream' => 'A', 'is_active' => true]);
        $subject = Subject::create(['name' => 'English', 'code' => 'ENG-EXP', 'school_class_id' => $otherClass->id, 'is_active' => true]);
        $student = $this->user('student-export-denied', 'student', ['school_class_id' => $ownClass->id]);

        $this->actingAs($student)
            ->get(route('student.lesson-notes.exports.subject', [
                'academic_session_id' => $session->id,
                'school_class_id' => $otherClass->id,
                'subject_id' => $subject->id,
                'format' => 'word',
            ]));
    }

    private function user(string $portalId, string $role, array $overrides = []): User
    {
        return User::create(array_merge([
            'portal_id' => $portalId,
            'first_name' => ucfirst(str_replace('-', ' ', $portalId)),
            'last_name' => 'User',
            'email' => "{$portalId}@example.com",
            'password' => Hash::make('password'),
            'role' => $role,
            'must_change_password' => false,
            'is_active' => true,
        ], $overrides));
    }
}
