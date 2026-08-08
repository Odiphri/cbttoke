<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminOversightPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_oversight_pages(): void
    {
        $admin = $this->user('admin-oversight', 'admin');
        $teacher = $this->user('teacher-oversight', 'teacher');
        $class = SchoolClass::create(['name' => 'JSS1A', 'level' => 'JSS1', 'stream' => 'A', 'is_active' => true]);
        $subject = Subject::create(['name' => 'Mathematics', 'code' => 'MTH-OV', 'school_class_id' => $class->id, 'is_active' => true]);
        AcademicSession::create(['academic_year' => '2026/2027', 'term' => 'First Term', 'is_active' => true]);

        DB::table('teacher_class_subject')->insert([
            'teacher_id' => $teacher->id,
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            route('admin.dashboard'),
            route('admin.reports'),
            route('admin.exercises'),
            route('admin.teacher-workload'),
            route('admin.lesson-note-coverage'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    private function user(string $portalId, string $role): User
    {
        return User::create([
            'portal_id' => $portalId,
            'first_name' => ucfirst(str_replace('-', ' ', $portalId)),
            'last_name' => 'User',
            'email' => "{$portalId}@example.com",
            'password' => Hash::make('password'),
            'role' => $role,
            'must_change_password' => false,
            'is_active' => true,
        ]);
    }
}
