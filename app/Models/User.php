<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'portal_id',
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'student_role_id',
        'school_class_id',
        'prefect_title',
        'prefect_role_id',
        'must_change_password',
        'last_login_at',
        'password_changed_at',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'must_change_password' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function assignedClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function studentRole(): BelongsTo
    {
        return $this->belongsTo(StudentRole::class);
    }

    public function prefectRole(): BelongsTo
    {
        return $this->belongsTo(PrefectRole::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'student_subject', 'student_id', 'subject_id');
    }

    public function studentSubject(): HasMany
    {
        return $this->hasMany(\App\Models\StudentSubject::class, 'student_id');
    }

    public function teachingClasses(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'teacher_class_subject', 'teacher_id', 'school_class_id');
    }

    public function assignedClasses(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'staff_class_assignments', 'user_id', 'school_class_id');
    }

    public function teachingSubjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'teacher_class_subject', 'teacher_id', 'subject_id');
    }

    public function createdExams(): HasMany
    {
        return $this->hasMany(Exam::class, 'created_by');
    }

    public function lessonNotes(): HasMany
    {
        return $this->hasMany(LessonNote::class, 'teacher_id');
    }

    public function exerciseAttempts(): HasMany
    {
        return $this->hasMany(ExerciseAttempt::class, 'student_id');
    }

    public function examAttempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class, 'student_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'student_id');
    }

    public function feeExemptions(): HasMany
    {
        return $this->hasMany(StudentFeeExemption::class, 'student_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class, 'student_id');
    }

    public function markedAttendance(): HasMany
    {
        return $this->hasMany(Attendance::class, 'marked_by');
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class, 'student_id');
    }

    public function approvedChanges(): HasMany
    {
        return $this->hasMany(ChangeRequest::class, 'approved_by');
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(Override::class, 'student_id');
    }

    public function approvedOverrides(): HasMany
    {
        return $this->hasMany(Override::class, 'approved_by');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isHOD(): bool
    {
        return $this->role === 'hod';
    }

    public function isCBTPersonnel(): bool
    {
        return $this->role === 'cbt_personnel';
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    public function isPrefect(): bool
    {
        return $this->role === 'prefect';
    }

    public function isStudent(): bool
    {
        return in_array($this->role, ['student', 'prefect'], true);
    }

    public function canAccessAcademicFeatures(): bool
    {
        return in_array($this->role, ['admin', 'hod', 'cbt_personnel', 'teacher']);
    }

    public function canAccessFinancialFeatures(): bool
    {
        return in_array($this->role, ['admin', 'hod'], true) || $this->can('bursary.manage');
    }

    public function canOverrideExamAccess(): bool
    {
        return in_array($this->role, ['admin', 'hod'], true)
            || $this->can('exams.override_access')
            || $this->can('overrides.create');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }
}
