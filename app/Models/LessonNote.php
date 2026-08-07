<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class LessonNote extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ARCHIVED = 'archived';

    public const EDITABLE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_RETURNED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'academic_session_id',
        'school_class_id',
        'subject_id',
        'teacher_id',
        'week_number',
        'title',
        'topic',
        'subtopic',
        'lesson_date',
        'previous_knowledge',
        'learning_objectives',
        'teaching_materials',
        'introduction',
        'main_content',
        'evaluation',
        'conclusion',
        'assignment',
        'status',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'lesson_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (LessonNote $note) {
            foreach ($note->attachments as $attachment) {
                Storage::disk('public')->delete($attachment->stored_path);
            }

            if ($note->exercise) {
                foreach ($note->exercise->questions as $question) {
                    if ($question->image_path) {
                        Storage::disk('public')->delete($question->image_path);
                    }
                }
            }
        });
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(LessonNoteReview::class)->latest('reviewed_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(LessonNoteAttachment::class);
    }

    public function exercise(): HasOne
    {
        return $this->hasOne(LessonExercise::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE_STATUSES, true);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function statusLabel(): string
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING => 'Pending Approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_RETURNED => 'Returned',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_ARCHIVED => 'Archived',
        ][$this->status] ?? ucfirst($this->status);
    }

    public function statusBadgeClass(): string
    {
        return [
            self::STATUS_DRAFT => 'bg-secondary',
            self::STATUS_PENDING => 'bg-warning text-dark',
            self::STATUS_APPROVED => 'bg-success',
            self::STATUS_RETURNED => 'bg-info text-dark',
            self::STATUS_REJECTED => 'bg-danger',
            self::STATUS_ARCHIVED => 'bg-dark',
        ][$this->status] ?? 'bg-secondary';
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }
}
