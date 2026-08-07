<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_year',
        'term',
        'starts_at',
        'ends_at',
        'is_active',
        'activated_at',
        'promoted_at',
        'promoted_students_count',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
            'promoted_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function lessonNotes()
    {
        return $this->hasMany(LessonNote::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->academic_year} - {$this->term}";
    }
}
