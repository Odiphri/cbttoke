<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonNoteAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_note_id',
        'original_filename',
        'stored_path',
        'mime_type',
        'file_size',
    ];

    public function lessonNote(): BelongsTo
    {
        return $this->belongsTo(LessonNote::class);
    }
}
